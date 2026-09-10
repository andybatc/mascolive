<?php

/**
 * @file
 * Crea los content types faltantes según la arquitectura de "El Nido".
 * Idempotente: reutiliza lo que ya existe.
 *
 * Content types:
 * - veterinario (perfil profesional)
 * - vendor (vendedor/tienda)
 * - transfer (transporte de mascotas)
 * - appointment (citas)
 * - clinical_history (historial clínico)
 * - wallet (billetera/pagos)
 * - evaluation (evaluaciones)
 */

use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

function log_($msg) { print "[$msg]\n"; }

// =====================================================
// 1. CREAR CONTENT TYPES
// =====================================================
$types = [
  'veterinario' => 'Veterinario',
  'vendor' => 'Vendedor',
  'transfer' => 'Transfer',
  'appointment' => 'Cita',
  'clinical_history' => 'Historial Clínico',
  'wallet' => 'Billetera',
  'evaluation' => 'Evaluación',
];

foreach ($types as $machine => $label) {
  if (!NodeType::load($machine)) {
    NodeType::create(['type' => $machine, 'name' => $label])->save();
    log_("Created content type: $machine");
  } else {
    log_("Content type exists: $machine");
  }
}

// =====================================================
// 2. CAMPOS PARA VETERINARIO
// =====================================================
$vet_fields = [
  ['node', 'veterinario', 'field_full_name', 'Nombre completo', 'string', 255],
  ['node', 'veterinario', 'field_vet_specialty', 'Especialidad', 'string', 255],
  ['node', 'veterinario', 'field_phone', 'Teléfono', 'string', 20],
  ['node', 'veterinario', 'field_user', 'Usuario asociado', 'entity_reference', 0],
];

foreach ($vet_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = $type === 'string' ? ['max_length' => $len] : ['target_type' => 'user'];
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
// 3. CAMPOS PARA VENDOR
// =====================================================
$vendor_fields = [
  ['node', 'vendor', 'field_shop_name', 'Nombre de tienda', 'string', 255],
  ['node', 'vendor', 'field_description', 'Descripción', 'text_long', 0],
  ['node', 'vendor', 'field_phone', 'Teléfono', 'string', 20],
  ['node', 'vendor', 'field_user', 'Usuario asociado', 'entity_reference', 0],
];

foreach ($vendor_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = $type === 'string' ? ['max_length' => $len] : ['target_type' => 'user'];
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
// 4. CAMPOS PARA TRANSFER
// =====================================================
$transfer_fields = [
  ['node', 'transfer', 'field_pet', 'Mascota', 'entity_reference', 0],
  ['node', 'transfer', 'field_clinic_from', 'Clínica origen', 'entity_reference', 0],
  ['node', 'transfer', 'field_clinic_to', 'Clínica destino', 'entity_reference', 0],
  ['node', 'transfer', 'field_driver', 'Conductor', 'entity_reference', 0],
  ['node', 'transfer', 'field_transfer_date', 'Fecha y hora', 'datetime', 0],
  ['node', 'transfer', 'field_status', 'Estado', 'list_string', 0],
];

foreach ($transfer_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = [];
  if ($type === 'entity_reference') {
    if ($name === 'field_pet') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['pet' => 'pet']]];
    elseif ($name === 'field_clinic_from' || $name === 'field_clinic_to') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]];
    elseif ($name === 'field_driver') $settings = ['target_type' => 'user'];
  }
  if ($type === 'list_string') {
    $settings = [
      'allowed_values' => [
        'pending' => 'Pendiente',
        'in_progress' => 'En curso',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
      ],
    ];
  }
  if ($type === 'datetime') {
    $settings = ['datetime_type' => 'datetime'];
  }

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
// 5. CAMPOS PARA APPOINTMENT
// =====================================================
$appt_fields = [
  ['node', 'appointment', 'field_pet', 'Mascota', 'entity_reference', 0],
  ['node', 'appointment', 'field_clinic', 'Clínica', 'entity_reference', 0],
  ['node', 'appointment', 'field_veterinario', 'Veterinario', 'entity_reference', 0],
  ['node', 'appointment', 'field_appointment_date', 'Fecha y hora', 'datetime', 0],
  ['node', 'appointment', 'field_reason', 'Motivo de consulta', 'string_long', 0],
  ['node', 'appointment', 'field_status', 'Estado', 'list_string', 0],
];

foreach ($appt_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = [];
  if ($type === 'entity_reference') {
    if ($name === 'field_pet') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['pet' => 'pet']]];
    elseif ($name === 'field_clinic') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]];
    elseif ($name === 'field_veterinario') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['veterinario' => 'veterinario']]];
  }
  if ($type === 'list_string') {
    $settings = [
      'allowed_values' => [
        'scheduled' => 'Programada',
        'in_progress' => 'En curso',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
      ],
    ];
  }
  if ($type === 'datetime') {
    $settings = ['datetime_type' => 'datetime'];
  }

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
// 6. CAMPOS PARA CLINICAL_HISTORY
// =====================================================
$ch_fields = [
  ['node', 'clinical_history', 'field_pet', 'Mascota', 'entity_reference', 0],
  ['node', 'clinical_history', 'field_clinic', 'Clínica', 'entity_reference', 0],
  ['node', 'clinical_history', 'field_veterinario', 'Veterinario', 'entity_reference', 0],
  ['node', 'clinical_history', 'field_diagnosis', 'Diagnóstico', 'string_long', 0],
  ['node', 'clinical_history', 'field_treatment', 'Tratamiento', 'string_long', 0],
  ['node', 'clinical_history', 'field_notes', 'Notas adicionales', 'text_long', 0],
  ['node', 'clinical_history', 'field_visit_date', 'Fecha de visita', 'datetime', 0],
];

foreach ($ch_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = [];
  if ($type === 'entity_reference') {
    if ($name === 'field_pet') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['pet' => 'pet']]];
    elseif ($name === 'field_clinic') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]];
    elseif ($name === 'field_veterinario') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['veterinario' => 'veterinario']]];
  }
  if ($type === 'datetime') {
    $settings = ['datetime_type' => 'date'];
  }

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
// 7. CAMPOS PARA WALLET
// =====================================================
$wallet_fields = [
  ['node', 'wallet', 'field_user', 'Usuario', 'entity_reference', 0],
  ['node', 'wallet', 'field_balance', 'Saldo', 'string', 50],
  ['node', 'wallet', 'field_currency', 'Moneda', 'list_string', 0],
];

foreach ($wallet_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = [];
  if ($name === 'field_user') $settings = ['target_type' => 'user'];
  if ($name === 'field_currency') {
    $settings = [
      'allowed_values' => [
        'CUP' => 'Peso Cubano (CUP)',
        'USD' => 'Dólar (USD)',
      ],
    ];
  }

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
// 8. CAMPOS PARA EVALUATION
// =====================================================
$eval_fields = [
  ['node', 'evaluation', 'field_pet', 'Mascota', 'entity_reference', 0],
  ['node', 'evaluation', 'field_clinic', 'Clínica', 'entity_reference', 0],
  ['node', 'evaluation', 'field_veterinario', 'Veterinario', 'entity_reference', 0],
  ['node', 'evaluation', 'field_rating', 'Calificación', 'list_integer', 0],
  ['node', 'evaluation', 'field_comment', 'Comentario', 'string_long', 0],
  ['node', 'evaluation', 'field_user', 'Evaluador', 'entity_reference', 0],
];

foreach ($eval_fields as [$et, $bundle, $name, $label, $type, $len]) {
  $settings = [];
  if ($type === 'entity_reference') {
    if ($name === 'field_pet') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['pet' => 'pet']]];
    elseif ($name === 'field_clinic') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]];
    elseif ($name === 'field_veterinario') $settings = ['target_type' => 'node', 'handler_settings' => ['target_bundles' => ['veterinario' => 'veterinario']]];
    elseif ($name === 'field_user') $settings = ['target_type' => 'user'];
  }
  if ($name === 'field_rating') {
    $settings = [
      'allowed_values' => [
        1 => '1 - Malo',
        2 => '2 - Regular',
        3 => '3 - Bueno',
        4 => '4 - Muy bueno',
        5 => '5 - Excelente',
      ],
    ];
  }

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
// 9. CREAR NODOS DEMO
// =====================================================
use Drupal\node\Entity\Node;

$demo = [
  'veterinario' => [
    ['Dr. Carlos Pérez', ['field_full_name' => 'Dr. Carlos Pérez', 'field_vet_specialty' => 'Cardiología', 'field_phone' => '+53 5555 1234']],
    ['Dra. María López', ['field_full_name' => 'Dra. María López', 'field_vet_specialty' => 'Vacunación', 'field_phone' => '+53 5555 5678']],
  ],
  'vendor' => [
    ['PetShop Habana', ['field_shop_name' => 'PetShop Habana', 'field_description' => 'Tienda de productos para mascotas', 'field_phone' => '+53 5555 9999']],
  ],
];

foreach ($demo as $bundle => $nodes) {
  foreach ($nodes as [$title, $data]) {
    $existing = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => $bundle, 'title' => $title]);
    if (!$existing) {
      $node = Node::create(array_merge(['type' => $bundle, 'title' => $title, 'status' => 1], $data));
      $node->save();
      log_("Demo node created: $title (nid {$node->id()})");
    } else {
      log_("Demo node exists: $title");
    }
  }
}

log_("DONE: All content types and fields created");
