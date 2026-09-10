<?php
/**
 * Create purchased_entity + quantity fields on commerce_order_item pet_product bundle.
 * Run: ddev drush php:script scripts/fix_order_item_fields.php
 */

$etm = \Drupal::service('entity_type.manager');

echo "=== Creating fields on commerce_order_item:pet_product ===\n";

$fieldsToCreate = [
  'purchased_entity' => [
    'type' => 'entity_reference',
    'label' => 'Purchased entity',
    'required' => TRUE,
    'settings' => [
      'target_type' => 'commerce_product_variation',
    ],
    'instance_settings' => [
      'handler' => 'default:commerce_product_variation',
      'handler_settings' => [
        'target_bundles' => ['pet_product' => 'pet_product'],
        'sort' => ['field' => '_none'],
        'auto_create' => FALSE,
      ],
    ],
  ],
  'quantity' => [
    'type' => 'commerce_quantity',
    'label' => 'Quantity',
    'required' => TRUE,
    'settings' => [],
    'instance_settings' => [],
  ],
];

$fieldStorageManager = $etm->getStorage('field_storage_config');
$fieldConfigManager = $etm->getStorage('field_config');

foreach ($fieldsToCreate as $fieldName => $spec) {
  echo "\n  Field: $fieldName\n";

  // Create field storage if not exists
  $existingStorage = $fieldStorageManager->loadByProperties([
    'field_name' => $fieldName,
    'entity_type' => 'commerce_order_item',
  ]);
  $existingStorage = $existingStorage ? reset($existingStorage) : NULL;

  if (!$existingStorage) {
    try {
      $fs = $fieldStorageManager->create([
        'field_name' => $fieldName,
        'entity_type' => 'commerce_order_item',
        'type' => $spec['type'],
        'settings' => $spec['settings'],
        'cardinality' => 1,
      ]);
      $fs->save();
      echo "    Field storage created.\n";
    } catch (\Exception $e) {
      echo "    Field storage error: " . $e->getMessage() . "\n";
    }
  } else {
    echo "    Field storage exists.\n";
  }

  // Create field instance on pet_product bundle
  $existingInstance = $fieldConfigManager->loadByProperties([
    'field_name' => $fieldName,
    'entity_type' => 'commerce_order_item',
    'bundle' => 'pet_product',
  ]);
  $existingInstance = $existingInstance ? reset($existingInstance) : NULL;

  if (!$existingInstance) {
    try {
      $fi = $fieldConfigManager->create([
        'field_name' => $fieldName,
        'entity_type' => 'commerce_order_item',
        'bundle' => 'pet_product',
        'label' => $spec['label'],
        'required' => $spec['required'],
        'settings' => $spec['instance_settings'],
      ]);
      $fi->save();
      echo "    Field instance created.\n";
    } catch (\Exception $e) {
      echo "    Field instance error: " . $e->getMessage() . "\n";
    }
  } else {
    echo "    Field instance exists.\n";
  }
}

// Also need unit_price field for order items
echo "\n  Field: unit_price\n";
$existingUnitPrice = $fieldStorageManager->loadByProperties([
  'field_name' => 'unit_price',
  'entity_type' => 'commerce_order_item',
]);
$existingUnitPrice = $existingUnitPrice ? reset($existingUnitPrice) : NULL;
if (!$existingUnitPrice) {
  try {
    $fs = $fieldStorageManager->create([
      'field_name' => 'unit_price',
      'entity_type' => 'commerce_order_item',
      'type' => 'commerce_price',
      'settings' => ['currency_code' => 'CUP'],
      'cardinality' => 1,
    ]);
    $fs->save();
    echo "    Field storage created.\n";
  } catch (\Exception $e) {
    echo "    Field storage error: " . $e->getMessage() . "\n";
  }
} else {
  echo "    Field storage exists.\n";
}

$existingUnitPriceInst = $fieldConfigManager->loadByProperties([
  'field_name' => 'unit_price',
  'entity_type' => 'commerce_order_item',
  'bundle' => 'pet_product',
]);
$existingUnitPriceInst = $existingUnitPriceInst ? reset($existingUnitPriceInst) : NULL;
if (!$existingUnitPriceInst) {
  try {
    $fi = $fieldConfigManager->create([
      'field_name' => 'unit_price',
      'entity_type' => 'commerce_order_item',
      'bundle' => 'pet_product',
      'label' => 'Unit price',
      'required' => FALSE,
      'settings' => [],
    ]);
    $fi->save();
    echo "    Field instance created.\n";
  } catch (\Exception $e) {
    echo "    Field instance error: " . $e->getMessage() . "\n";
  }
} else {
  echo "    Field instance exists.\n";
}

echo "\n=== Done ===\n";
