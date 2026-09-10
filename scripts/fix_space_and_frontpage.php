<?php
/**
 * FIX 3: NodeHive Space + FIX 4: Front page.
 * Run: ddev drush php:script scripts/fix_space_and_frontpage.php
 */

// FIX 3: NodeHive Space
echo "=== FIX 3: NodeHive Space ===\n";
try {
  $etm = \Drupal::service('entity_type.manager');
  $def = $etm->getDefinition('nodehive_space', FALSE);
  echo "  Entity type: " . $def->getLabel() . "\n";

  $storage = $etm->getStorage('nodehive_space');
  $existing = $storage->load('mascolive');
  if ($existing) {
    echo "  Space 'mascolive' already exists.\n";
  } else {
    $space = $storage->create([
      'id' => 'mascolive',
      'label' => 'MascoLive',
      'description' => 'Main space for MascoLive',
      'status' => 1,
    ]);
    $space->save();
    echo "  Created space 'MascoLive'.\n";
  }
} catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}

// FIX 4: Front page
echo "\n=== FIX 4: Front page ===\n";
$site = \Drupal::configFactory()->getEditable('system.site');
$current = $site->get('page.front');
echo "  Current: $current\n";
if ($current !== '/acerca-de-mascolive') {
  $site->set('page.front', '/acerca-de-mascolive')->save();
  echo "  Changed to /acerca-de-mascolive\n";
} else {
  echo "  Already correct.\n";
}

echo "\n=== Done ===\n";
