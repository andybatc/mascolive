<?php
/**
 * Fix page nodes forced into draft by editorial workflow.
 * 1. Remove 'page' from editorial workflow entity_types (keep article only)
 * 2. Delete test node
 * 3. Publish portal nodes via entity API
 * Run: ddev drush php:script scripts/fix_portals_moderation.php
 */

$configFactory = \Drupal::service('config.factory');

echo "=== 1. Remove 'page' from editorial workflow ===\n";
$workflow = $configFactory->getEditable('workflows.workflow.editorial');
$data = $workflow->getRawData();
$entityTypes = $data['type_settings']['entity_types']['node'] ?? [];
echo "  Before: " . implode(', ', $entityTypes) . "\n";

if (in_array('page', $entityTypes)) {
  $entityTypes = array_values(array_filter($entityTypes, fn($t) => $t !== 'page'));
  $workflow->set('type_settings.entity_types.node', $entityTypes)->save();
  echo "  Removed 'page'. After: " . implode(', ', $entityTypes) . "\n";
} else {
  echo "  'page' not in workflow, nothing to do.\n";
}

echo "\n=== 2. Delete test node 21 ===\n";
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
$testNode = $nodeStorage->load(21);
if ($testNode) {
  $testNode->delete();
  echo "  Deleted test node 21.\n";
} else {
  echo "  Node 21 not found.\n";
}

echo "\n=== 3. Publish portal nodes via entity API ===\n";
foreach ([12, 13, 14, 15, 16] as $nid) {
  $node = $nodeStorage->load($nid);
  if (!$node) {
    echo "  nid $nid: NOT FOUND\n";
    continue;
  }
  $title = $node->getTitle();
  $node->set('status', 1);
  $node->save();
  $nodeStorage->resetCache([$nid]);
  $reloaded = $nodeStorage->load($nid);
  echo "  nid $nid '$title': " . ($reloaded->isPublished() ? "PUBLISHED ✓" : "status=" . $reloaded->get('status')->value . " ✗") . "\n";
}

echo "\nDone. Run 'ddev drush cr'.\n";