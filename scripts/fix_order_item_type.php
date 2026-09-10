<?php
/**
 * Fix commerce_order_item_type pet_product properly.
 * Run: ddev drush php:script scripts/fix_order_item_type.php
 */

$etm = \Drupal::service('entity_type.manager');
$configFactory = \Drupal::service('config.factory');

echo "=== Fixing order_item_type pet_product ===\n";

// Load via entity storage
$storage = $etm->getStorage('commerce_order_item_type');
$orderItemType = $storage->load('pet_product');

if (!$orderItemType) {
  echo "  Creating new order_item_type...\n";
  $orderItemType = $storage->create([
    'id' => 'pet_product',
    'label' => 'Pet Product',
    'workflow' => 'default',
    'purchasableEntityType' => 'commerce_product_variation',
    'orderType' => 'default',
  ]);
} else {
  echo "  Updating existing order_item_type...\n";
  $orderItemType->set('purchasableEntityType', 'commerce_product_variation');
  $orderItemType->set('orderType', 'default');
  $orderItemType->set('workflow', 'default');
  $orderItemType->set('label', 'Pet Product');
}

$orderItemType->save();
echo "  Saved. Verifying...\n";

// Verify via config
$config = $configFactory->get('commerce_order.commerce_order_item_type.pet_product');
echo "  id: " . $config->get('id') . "\n";
echo "  label: " . $config->get('label') . "\n";
echo "  purchasableEntityType: " . ($config->get('purchasableEntityType') ?: 'EMPTY') . "\n";
echo "  orderType: " . ($config->get('orderType') ?: 'EMPTY') . "\n";
echo "  workflow: " . ($config->get('workflow') ?: 'EMPTY') . "\n";

// Now check if purchased_entity field exists on commerce_order_item for this bundle
echo "\n=== Checking field instances on pet_product order item ===\n";
$fieldConfigStorage = $etm->getStorage('field_config');
$allFields = $fieldConfigStorage->loadMultiple();

foreach ($allFields as $fc) {
  if ($fc->getTargetEntityTypeId() === 'commerce_order_item' && $fc->getTargetBundle() === 'pet_product') {
    echo "  Field: " . $fc->getName() . " (type: " . $fc->getFieldStorage()->getType() . ")\n";
  }
}

// Create purchased_entity field if missing
$hasPurchasedEntity = FALSE;
foreach ($allFields as $fc) {
  if ($fc->getTargetEntityTypeId() === 'commerce_order_item' && $fc->getName() === 'purchased_entity') {
    $hasPurchasedEntity = TRUE;
    break;
  }
}

if (!$hasPurchasedEntity) {
  echo "\n  purchased_entity field missing from order_item. Creating...\n";

  // Check if field storage exists
  $storageDef = $etm->getFieldStorageDefinitions('commerce_order_item');
  if (!isset($storageDef['purchased_entity'])) {
    // Create field storage
    $fs = $etm->getStorage('field_storage_config')->create([
      'field_name' => 'purchased_entity',
      'entity_type' => 'commerce_order_item',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'commerce_product_variation'],
      'cardinality' => 1,
    ]);
    $fs->save();
    echo "  Created field storage purchased_entity.\n";
  }

  // Create field instance on pet_product bundle
  $fi = $fieldConfigStorage->create([
    'field_name' => 'purchased_entity',
    'entity_type' => 'commerce_order_item',
    'bundle' => 'pet_product',
    'label' => 'Purchased entity',
    'required' => TRUE,
    'settings' => [
      'handler' => 'default:commerce_product_variation',
      'handler_settings' => [
        'target_bundles' => ['pet_product' => 'pet_product'],
        'sort' => ['field' => '_none'],
        'auto_create' => FALSE,
      ],
    ],
  ]);
  $fi->save();
  echo "  Created field instance purchased_entity on pet_product.\n";
}

// Create quantity field if missing
$hasQuantity = FALSE;
foreach ($allFields as $fc) {
  if ($fc->getTargetEntityTypeId() === 'commerce_order_item' && $fc->getName() === 'quantity' && $fc->getTargetBundle() === 'pet_product') {
    $hasQuantity = TRUE;
    break;
  }
}

if (!$hasQuantity) {
  echo "  quantity field missing from pet_product. Creating...\n";

  $storageDef = $etm->getFieldStorageDefinitions('commerce_order_item');
  if (!isset($storageDef['quantity'])) {
    $fs = $etm->getStorage('field_storage_config')->create([
      'field_name' => 'quantity',
      'entity_type' => 'commerce_order_item',
      'type' => 'commerce_quantity',
      'settings' => [],
      'cardinality' => 1,
    ]);
    $fs->save();
    echo "  Created field storage quantity.\n";
  }

  $fi = $fieldConfigStorage->create([
    'field_name' => 'quantity',
    'entity_type' => 'commerce_order_item',
    'bundle' => 'pet_product',
    'label' => 'Quantity',
    'required' => TRUE,
    'settings' => [],
  ]);
  $fi->save();
  echo "  Created field instance quantity on pet_product.\n";
}

echo "\n=== Done ===\n";
