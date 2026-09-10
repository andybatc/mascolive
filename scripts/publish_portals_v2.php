<?php
/**
 * Publish portal nodes 13-16 via moderation-aware save.
 * Run: ddev drush php:script scripts/publish_portals_v2.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('node');

foreach ([12, 13, 14, 15, 16] as $nid) {
  $storage->resetCache([$nid]);
  $node = $storage->load($nid);
  if (!$node) {
    echo "  nid $nid: NOT FOUND\n";
    continue;
  }
  if ($node->hasField('moderation_state')) {
    $node->set('moderation_state', 'published');
  }
  $node->set('status', 1);
  $node->save();
  $storage->resetCache([$nid]);
  $fresh = $storage->load($nid);
  $status = $fresh->get('status')->value;
  echo "  nid $nid '" . $fresh->getTitle() . "': status=$status " . ($fresh->isPublished() ? "PUBLISHED ✓" : "✗") . "\n";
}

echo "\nDone. Run 'ddev drush cr' then verify via curl.\n";