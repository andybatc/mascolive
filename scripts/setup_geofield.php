<?php

/**
 * @file
 * Configures geofield on clinic content type with Leaflet widget.
 * Adds demo coordinates for existing clinics.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

function log_($msg) { print "[$msg]\n"; }

// 1. Create geofield storage
$fs = FieldStorageConfig::loadByName('node', 'field_geolocation');
if (!$fs) {
  FieldStorageConfig::create([
    'entity_type' => 'node',
    'field_name' => 'field_geolocation',
    'type' => 'geofield',
    'settings' => [
      'backend' => 'geofield_backend_default',
      'default_format' => 'wkt',
    ],
  ])->save();
  log_('Storage created: field_geolocation');
} else {
  log_('Storage exists: field_geolocation');
}

// 2. Attach to clinic bundle
$fc = FieldConfig::loadByName('node', 'clinic', 'field_geolocation');
if (!$fc) {
  FieldConfig::create([
    'entity_type' => 'node',
    'bundle' => 'clinic',
    'field_name' => 'field_geolocation',
    'label' => 'Ubicación',
    'description' => 'Coordenadas GPS de la clínica',
  ])->save();
  log_('Field created: clinic.field_geolocation');
} else {
  log_('Field exists: clinic.field_geolocation');
}

// 3. Form display: Leaflet widget
$em = \Drupal::entityTypeManager();
$fd = $em->getStorage('entity_form_display')->load('node.clinic.default');
if (!$fd) {
  $fd = $em->getStorage('entity_form_display')->create([
    'targetEntityType' => 'node',
    'bundle' => 'clinic',
    'mode' => 'default',
    'status' => TRUE,
  ]);
  log_('Form display created');
}
$fd->setComponent('field_geolocation', [
  'type' => 'leaflet_widget_default',
  'settings' => [
    'leaflet_map' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'height' => '400px',
    'center' => ['lat' => 23.1136, 'lon' => -82.3666], // Havana
    'zoom' => 12,
  ],
  'weight' => 20,
]);
$fd->save();
log_('Form display configured: leaflet_widget_default');

// 4. View display: Leaflet formatter
$vd = $em->getStorage('entity_view_display')->load('node.clinic.default');
if (!$vd) {
  $vd = $em->getStorage('entity_view_display')->create([
    'targetEntityType' => 'node',
    'bundle' => 'clinic',
    'mode' => 'default',
    'status' => TRUE,
  ]);
  log_('View display created');
}
$vd->setComponent('field_geolocation', [
  'type' => 'leaflet_formatter_default',
  'label' => 'hidden',
  'settings' => [
    'leaflet_map' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'height' => '300px',
    'zoom' => 14,
  ],
]);
$vd->save();
log_('View display configured: leaflet_formatter_default');

// 5. Add demo coordinates for existing clinics
$clinic_coords = [
  1 => ['lat' => 23.1373, 'lon' => -82.3806], // VetCentro Habana (Vedado)
  2 => ['lat' => 23.1350, 'lon' => -82.3950], // Mascotas Vedado
  3 => ['lat' => 23.1050, 'lon' => -82.4250], // VetMiramar
];

foreach ($clinic_coords as $nid => $coords) {
  $node = Node::load($nid);
  if ($node) {
    $wkt = "POINT ({$coords['lon']} {$coords['lat']})";
    $node->set('field_geolocation', [
      'value' => $wkt,
      'geo_type' => 'point',
      'lat' => $coords['lat'],
      'lon' => $coords['lon'],
      'left' => $coords['lon'],
      'right' => $coords['lon'],
      'top' => $coords['lat'],
      'bottom' => $coords['lat'],
    ]);
    $node->save();
    log_("Coordinates set for clinic nid $nid: {$coords['lat']}, {$coords['lon']}");
  }
}

log_('DONE');
