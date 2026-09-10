<?php
/**
 * Publish portal nodes using entity API (SQL UPDATE doesn't propagate to entity cache in D10).
 * Run: ddev drush php:script scripts/publish_portals.php
 */

$etm = \Drupal::service('entity_type.manager');
$nodeStorage = $etm->getStorage('node');

$nids = [12, 13, 14, 15, 16];

foreach ($nids as $nid) {
  $node = $nodeStorage->load($nid);
  if (!$node) {
    echo "  nid $nid: NOT FOUND\n";
    continue;
  }
  $currentStatus = $node->get('status')->value;
  $title = $node->getTitle();
  echo "  nid $nid '$title': status=$currentStatus → ";
  if ($currentStatus == 1) {
    echo "already published, skip\n";
    continue;
  }
  $node->set('status', 1);
  $node->save();
  // Reload to confirm
  $nodeStorage->resetCache([$nid]);
  $reloaded = $nodeStorage->load($nid);
  echo "status=" . $reloaded->get('status')->value . " published=" . var_export($reloaded->isPublished(), true) . "\n";
}

echo "\nDone. Run 'ddev drush cr' to flush caches.\n";
