<?php

/**
 * @file
 * Crea los view displays (default + full) para los content types clinic y pet.
 *
 * Idempotente: reusa los displays si ya existen (EntityViewDisplay::load).
 * Uso: ddev drush php:script scripts/setup_displays.php
 * Demuestra: la configuración de cómo se renderiza cada node type (qué campos
 * y en qué view mode). Definición de Done del sprint: cada content type debe
 * poder mostrarse en su página /node/NID.
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

function log_($msg) {
  print $msg . "\n";
}

// Observable de vista por bundle: lista de [campo, label_mostrar].
$fields = [
  'clinic' => [
    'field_specialty' => 'Especialidad',
    'field_address'  => 'Dirección',
  ],
  'pet' => [
    'field_clinic'   => 'Clínica de referencia',
    'field_pet_type' => 'Tipo de mascota',
  ],
];

foreach ($fields as $bundle => $map) {
  foreach (['default', 'full'] as $mode) {
    $id = 'node.' . $bundle . '.' . $mode;
    $display = EntityViewDisplay::load($id);

    if (!$display) {
      // Crea el display con el label '(hidden)' por defecto para el title.
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => $mode,
        'status' => TRUE,
      ]);
      log_("  creado display $id");
    }
    else {
      log_("  display existente $id (reusado)");
    }

    // Muestra el título del nodo en el body (referencia a entity).
    $display->setComponent('title', [
      'type' => 'entity_reference_label',
      'label' => 'hidden',
    ]);

    // Añade/actualiza cada campo con su label 'above' (visible).
    foreach ($map as $field => $label) {
      $component['label'] = 'above';
      if ($field === 'field_specialty' || $field === 'field_address') {
        $component['type'] = 'string';
      }
      elseif ($field === 'field_clinic') {
        $component['type'] = 'entity_reference_label';
        $component['settings']['link'] = TRUE;
      }
      elseif ($field === 'field_pet_type') {
        $component['type'] = 'entity_reference_label';
        $component['settings']['link'] = FALSE;
      }
      $display->setComponent($field, $component);
    }

    $display->save();
    log_("  guardado $id");
  }
}

/**
 * Crea el form display (widgets del formulario) para un bundle de node.
 * Idempotente: reusa el form display si ya existe.
 */
function ensure_form_display($bundle, $fields) {
  $id = 'node.' . $bundle . '.default';
  $form = EntityFormDisplay::load($id);

  if (!$form) {
    $form = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
    log_("  creado form display $id");
  }
  else {
    log_("  form display existente $id (reusado)");
  }

  foreach ($fields as $field => $label) {
    // Widget por tipo de campo.
    if ($field === 'field_specialty' || $field === 'field_address') {
      $widget = ['type' => 'string_textfield'];
    }
    else {
      // entity reference: clínica y tipo de mascota → selector desplegable.
      $widget = [
        'type' => 'options_select',
        'settings' => [],
      ];
    }
    $form->setComponent($field, $widget);
  }

  $form->save();
  log_("  guardado $id");
}

foreach ($fields as $bundle => $map) {
  ensure_form_display($bundle, $map);
}

log_('Displays y form displays de clinic y pet listos.');
