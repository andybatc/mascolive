<?php
/**
 * Link variation type pet_product -> order item type pet_product.
 * Root cause of 'Missing bundle for entity type commerce_order_item'.
 * Run: ddev drush php:script scripts/fix_variation_order_item_link.php
 */

$configFactory = \Drupal::service('config.factory');

echo "=== Linking variation type pet_product -> order_item_type pet_product ===\n";

$config = $configFactory->getEditable('commerce_product.commerce_product_variation_type.pet_product');
$data = $config->getRawData();
echo "  orderItemType before: " . var_export($data['orderItemType'] ?? NULL, TRUE) . "\n";

$data['orderItemType'] = 'pet_product';
$config->setData($data)->save();

// Verify
$verify = $configFactory->get('commerce_product.commerce_product_variation_type.pet_product');
echo "  orderItemType after: " . var_export($verify->get('orderItemType'), TRUE) . "\n";

echo "\n=== Done ===\n";