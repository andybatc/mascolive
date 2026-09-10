<?php
/**
 * Create pet_product variation form display with proper widget settings.
 */

$em = \Drupal::entityTypeManager();

// The commerce_price_default widget needs settings + third_party_settings
$priceSettings = [
  'currency_code' => 'CUP',
  'price_editing' => 'direct',
  'user_currency' => 'CUP',
];

// Create the form display
$fd = $em->getStorage('entity_form_display')->create([
  'targetEntityType' => 'commerce_product_variation',
  'bundle' => 'pet_product',
  'mode' => 'default',
  'status' => 1,
]);

// Add title widget
$fd->setComponent('title', [
  'type' => 'string_textfield',
  'weight' => -5,
  'settings' => [
    'size' => 60,
    'placeholder' => '',
  ],
  'third_party_settings' => [],
]);

// Add SKU widget
$fd->setComponent('sku', [
  'type' => 'string_textfield',
  'weight' => -4,
  'settings' => [
    'size' => 60,
    'placeholder' => '',
  ],
  'third_party_settings' => [],
]);

// Add commerce_price widget
$fd->setComponent('commerce_price', [
  'type' => 'commerce_price_default',
  'weight' => 0,
  'settings' => $priceSettings,
  'third_party_settings' => [],
]);

// Add status widget
$fd->setComponent('status', [
  'type' => 'boolean_checkbox',
  'weight' => 10,
  'settings' => [
    'display_label' => 'Published',
  ],
  'third_party_settings' => [],
]);

$fd->save();
echo "Created pet_product variation form display\n";

// Verify
$loaded = $em->getStorage('entity_form_display')->load('commerce_product_variation.pet_product.default');
if ($loaded) {
  echo "Verification: form display loaded successfully\n";
  foreach ($loaded->getComponents() as $name => $component) {
    echo "  - $name: widget=" . ($component['type'] ?? 'null') . "\n";
  }
}
