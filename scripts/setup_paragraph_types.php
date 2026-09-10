<?php

/**
 * @file
 * Crea paragraph types faltantes según la arquitectura de "El Nido".
 * Idempotente: reutiliza lo que ya existe.
 *
 * Paragraph types:
 * - hero_section (sección principal)
 * - mission_vision (misión y visión)
 * - clinic_profile (perfil de clínica)
 * - service_card (tarjeta de servicio)
 */

use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

function log_($msg) { print "[$msg]\n"; }

// =====================================================
// 1. CREAR PARAGRAPH TYPES
// =====================================================
$paragraphs = [
  'hero_section' => 'Hero Section',
  'mission_vision' => 'Misión y Visión',
  'clinic_profile' => 'Perfil de Clínica',
  'service_card' => 'Tarjeta de Servicio',
];

foreach ($paragraphs as $id => $label) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label])->save();
    log_("Created paragraph type: $id");
  } else {
    log_("Paragraph type exists: $id");
  }
}

// =====================================================
// 2. CAMPOS PARA HERO_SECTION
// =====================================================
$hero_fields = [
  ['paragraph', 'hero_section', 'field_hero_title', 'Título', 'string', 255],
  ['paragraph', 'hero_section', 'field_hero_subtitle', 'Subtítulo', 'string', 255],
  ['paragraph', 'hero_section', 'field_hero_image', 'Imagen', 'image', 0],
  ['paragraph', 'hero_section', 'field_hero_link', 'Enlace', 'link', 0],
];

foreach ($hero_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = $type === 'string' ? ['max_length' => $len] : [];
  if ($type === 'image') $settings = ['uri_scheme' => 'public', 'default_image' => []];

  $storage = FieldStorageConfig::loadByName($et, $name);
  if (!$storage) {
    FieldStorageConfig::create([
      'entity_type' => $et,
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
    ])->save();
    log_("Storage created: $name");
  }
  if (!FieldConfig::loadByName($et, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $et,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
    ])->save();
    log_("Field created: $bundle.$name");
  }
}

// =====================================================
// 3. CAMPOS PARA MISSION_VISION
// =====================================================
$mv_fields = [
  ['paragraph', 'mission_vision', 'field_mission', 'Misión', 'text_long', 0],
  ['paragraph', 'mission_vision', 'field_vision', 'Visión', 'text_long', 0],
];

foreach ($mv_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $storage = FieldStorageConfig::loadByName($et, $name);
  if (!$storage) {
    FieldStorageConfig::create([
      'entity_type' => $et,
      'field_name' => $name,
      'type' => $type,
      'settings' => [],
    ])->save();
    log_("Storage created: $name");
  }
  if (!FieldConfig::loadByName($et, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $et,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
    ])->save();
    log_("Field created: $bundle.$name");
  }
}

// =====================================================
// 4. CAMPOS PARA CLINIC_PROFILE
// =====================================================
$cp_fields = [
  ['paragraph', 'clinic_profile', 'field_clinic', 'Clínica', 'entity_reference', 0],
  ['paragraph', 'clinic_profile', 'field_specialty', 'Especialidad', 'string', 255],
  ['paragraph', 'clinic_profile', 'field_address', 'Dirección', 'string', 255],
  ['paragraph', 'clinic_profile', 'field_phone', 'Teléfono', 'string', 20],
  ['paragraph', 'clinic_profile', 'field_image', 'Imagen', 'image', 0],
];

foreach ($cp_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = $type === 'string' ? ['max_length' => $len] : [];
  if ($type === 'image') $settings = ['uri_scheme' => 'public', 'default_image' => []];
  if ($name === 'field_clinic') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]];

  $storage = FieldStorageConfig::loadByName($et, $name);
  if (!$storage) {
    FieldStorageConfig::create([
      'entity_type' => $et,
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
    ])->save();
    log_("Storage created: $name");
  }
  if (!FieldConfig::loadByName($et, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $et,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
    ])->save();
    log_("Field created: $bundle.$name");
  }
}

// =====================================================
// 5. CAMPOS PARA SERVICE_CARD
// =====================================================
$sc_fields = [
  ['paragraph', 'service_card', 'field_service_name', 'Nombre del servicio', 'string', 255],
  ['paragraph', 'service_card', 'field_service_description', 'Descripción', 'text_long', 0],
  ['paragraph', 'service_card', 'field_price', 'Precio', 'string', 50],
  ['paragraph', 'service_card', 'field_image', 'Imagen', 'image', 0],
];

foreach ($sc_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = $type === 'string' ? ['max_length' => $len] : [];
  if ($type === 'image') $settings = ['uri_scheme' => 'public', 'default_image' => []];

  $storage = FieldStorageConfig::loadByName($et, $name);
  if (!$storage) {
    FieldStorageConfig::create([
      'entity_type' => $et,
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
    ])->save();
    log_("Storage created: $name");
  }
  if (!FieldConfig::loadByName($et, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $et,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
    ])->save();
    log_("Field created: $bundle.$name");
  }
}

// =====================================================
// 6. CONECTAR PARAGRAPHS A PAGE (field_components)
// =====================================================
$page_type = \Drupal\node\Entity\NodeType::load('page');
if ($page_type) {
  $storage = FieldStorageConfig::loadByName('node', 'field_components');
  if ($storage) {
    $field = FieldConfig::loadByName('node', 'page', 'field_components');
    if ($field) {
      $handler_settings = $field->getSetting('handler_settings');
      $target_bundles = $handler_settings['target_bundles'] ?? [];

      $new_bundles = ['hero_section', 'mission_vision', 'clinic_profile', 'service_card'];
      foreach ($new_bundles as $bundle) {
        if (!isset($target_bundles[$bundle])) {
          $target_bundles[$bundle] = $bundle;
        }
      }

      $field->setSetting('handler_settings', array_merge($handler_settings, ['target_bundles' => $target_bundles]));
      $field->save();
      log_("Updated field_components with new paragraph types");
    }
  }
}

log_("DONE: All paragraph types and fields created");
