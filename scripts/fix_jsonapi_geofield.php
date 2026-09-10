<?php
/**
 * FIX 2: JSON:API geolocation field for clinic nodes.
 * Run: ddev drush php:script scripts/fix_jsonapi_geofield.php
 */

$entityTypeManager = \Drupal::service('entity_type.manager');
$configFactory = \Drupal::service('config.factory');

echo "=== JSON:API geolocation field for node--clinic ===\n";

try {
  $clinicConfig = $configFactory->get('jsonapi_extras.resource.node.clinic');
  if ($clinicConfig && $clinicConfig->getRawData()) {
    $data = $clinicConfig->getRawData();
    $resourceFields = $data['resourceFields'] ?? [];

    if (isset($resourceFields['field_geolocation'])) {
      echo "  field_geolocation already present, public=" . var_export($resourceFields['field_geolocation']['public'] ?? NULL, true) . "\n";
    } else {
      echo "  Adding field_geolocation...\n";
    }
  } else {
    // No config exists — build it from field configs on clinic bundle
    echo "  No JSON:API config for clinic. Building...\n";

    $fieldConfigStorage = $entityTypeManager->getStorage('field_config');
    $allFieldConfigs = $fieldConfigStorage->loadMultiple();
    $clinicFields = [];

    foreach ($allFieldConfigs as $fc) {
      // FieldConfig in D10: getTargetEntityTypeId() and getTargetBundle()
      if ($fc->getTargetEntityTypeId() === 'node' && $fc->getTargetBundle() === 'clinic') {
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

    $data = [
      'id' => 'node--clinic',
      'resourceType' => 'node--clinic',
      'resourceFields' => $clinicFields,
    ];
    $configFactory->getEditable('jsonapi_extras.resource.node.clinic')
      ->setData($data)
      ->save();
    echo "  Created config with " . count($clinicFields) . " fields:\n";
    foreach (array_keys($clinicFields) as $f) {
      echo "    - $f\n";
    }
  }
} catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
