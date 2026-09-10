<?php
/**
 * Fix 4 remaining bugs from endpoint smoke test.
 * Run: ddev drush php:script scripts/fix_remaining_bugs.php
 */

$entityTypeManager = \Drupal::service('entity_type.manager');
$configFactory = \Drupal::service('config.factory');
$db = \Drupal::database();

// ============================================================
// FIX 1: Commerce order_item_type for pet_product
// ============================================================
echo "=== FIX 1: Commerce order_item_type for pet_product ===\n";

try {
  $orderItemTypeStorage = $entityTypeManager->getStorage('commerce_order_item_type');
  $existing = $orderItemTypeStorage->load('pet_product');

  if ($existing) {
    echo "  order_item_type 'pet_product' already exists, skipping.\n";
  } else {
    $orderItemType = $orderItemTypeStorage->create([
      'id' => 'pet_product',
      'label' => 'Pet Product',
      'workflow' => 'default',
      'purchases_entity_type' => 'commerce_product_variation',
    ]);
    $orderItemType->save();
    echo "  Created order_item_type 'pet_product'.\n";
  }
} catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
  echo "  Falling back to config YAML approach...\n";

  // Write the config YAML directly
  $configDir = \Drupal::service('file_system')->getTempDirectory() . '/commerce_fix';
  if (!is_dir($configDir)) {
    mkdir($configDir, 0777, true);
  }

  $yaml = <<<YAML
langcode: es
status: true
dependencies: {  }
id: pet_product
label: 'Pet Product'
workflow: default
purchases_entity_type: commerce_product_variation
YAML;

  file_put_contents($configDir . '/commerce_order.commerce_order_item_type.pet_product.yml', $yaml);
  echo "  Wrote config YAML to $configDir\n";
  echo "  You may need to run: ddev drush config:import\n";
}

// ============================================================
// FIX 2: JSON:API geolocation field
// ============================================================
echo "\n=== FIX 2: JSON:API geolocation field ===\n";

try {
  $clinicConfig = $configFactory->get('jsonapi_extras.resource.node.clinic');
  if ($clinicConfig && $clinicConfig->getRawData()) {
    $data = $clinicConfig->getRawData();
    $resourceFields = $data['resourceFields'] ?? [];

    if (isset($resourceFields['field_geolocation'])) {
      echo "  field_geolocation already in JSON:API config.\n";
      echo "  public: " . ($resourceFields['field_geolocation']['public'] ?? 'N/A') . "\n";
    } else {
      echo "  Adding field_geolocation to JSON:API clinic resource...\n";
      $resourceFields['field_geolocation'] = [
        'publicName' => 'field_geolocation',
        'fieldName' => 'field_geolocation',
        'public' => TRUE,
        'enhanced' => FALSE,
        'resourceType' => NULL,
        'groupName' => '',
      ];
      $data['resourceFields'] = $resourceFields;
      $configFactory->getEditable('jsonapi_extras.resource.node.clinic')
        ->setData($data)
        ->save();
      echo "  Added field_geolocation.\n";
    }
  } else {
    // Create full config for node--clinic
    echo "  No JSON:API config for clinic. Creating...\n";

    // Load all field configs for clinic bundle
    $fieldConfigStorage = $entityTypeManager->getStorage('field_config');
    $allFieldConfigs = $fieldConfigStorage->loadMultiple();
    $clinicFields = [];

    foreach ($allFieldConfigs as $fc) {
      if ($fc->getTargetType() === 'node' && $fc->getTargetBundle() === 'clinic') {
        $clinicFields[$fc->getName()] = [
          'publicName' => $fc->getName(),
          'fieldName' => $fc->getName(),
          'public' => TRUE,
          'enhanced' => FALSE,
          'resourceType' => NULL,
          'groupName' => '',
        ];
      }
    }
    // Add title
    $clinicFields['title'] = [
      'publicName' => 'title',
      'fieldName' => 'title',
      'public' => TRUE,
      'enhanced' => FALSE,
      'resourceType' => NULL,
      'groupName' => '',
    ];

    $configFactory->getEditable('jsonapi_extras.resource.node.clinic')
      ->setData([
        'id' => 'node--clinic',
        'resourceType' => 'node--clinic',
        'resourceFields' => $clinicFields,
      ])
      ->save();
    echo "  Created config with " . count($clinicFields) . " fields.\n";
    echo "  Fields: " . implode(', ', array_keys($clinicFields)) . "\n";
  }
} catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}

// ============================================================
// FIX 3: Create NodeHive Space
// ============================================================
echo "\n=== FIX 3: Create NodeHive Space ===\n";

try {
  // Check if nodehive_space entity type is registered
  $spaceStorageDef = $entityTypeManager->getStorageDefinition('nodehive_space');
  if (!$spaceStorageDef) {
    echo "  nodehive_space entity type not registered. Checking table...\n";
    if ($db->schema()->tableExists('nodehive_space')) {
      echo "  Table exists but entity type not in entity type manager.\n";
      echo "  Columns:\n";
      $cols = $db->query('SHOW COLUMNS FROM nodehive_space')->fetchAll();
      foreach ($cols as $col) {
        echo "    {$col->Field} ({$col->Type})\n";
      }
    } else {
      echo "  No nodehive_space table either. NodeHive CE may need further setup.\n";
    }
  } else {
    $spaceStorage = $entityTypeManager->getStorage('nodehive_space');
    $existingSpace = $spaceStorage->load('mascolive');

    if ($existingSpace) {
      echo "  Space 'mascolive' already exists, skipping.\n";
    } else {
      // Check available bundles
      $bundleStorage = $entityTypeManager->getStorage('nodehive_space');
      $bundleInfo = $entityTypeManager->getDefinition('nodehive_space');
      echo "  Entity type label: " . ($bundleInfo->getLabel() ?? 'N/A') . "\n";

      $space = $spaceStorage->create([
        'id' => 'mascolive',
        'label' => 'MascoLive',
        'description' => 'Main space for MascoLive platform',
        'status' => 1,
      ]);
      $space->save();
      echo "  Created NodeHive space 'MascoLive'.\n";
    }
  }
} catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
  echo "  NodeHive space may require setup via its own installer.\n";
}

// ============================================================
// FIX 4: Front page → /acerca-de-mascolive
// ============================================================
echo "\n=== FIX 4: Front page ===\n";

$systemSite = $configFactory->getEditable('system.site');
$currentFront = $systemSite->get('page.front');
echo "  Current front: $currentFront\n";

if ($currentFront !== '/acerca-de-mascolive') {
  $systemSite->set('page.front', '/acerca-de-mascolive')->save();
  echo "  Changed to /acerca-de-mascolive\n";
} else {
  echo "  Already set correctly.\n";
}

echo "\n=== DONE ===\n";
