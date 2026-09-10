<?php

/**
 * @file
 * Crea taxonomía de provincias y municipios de Cuba.
 * Idempotente: reutiliza lo que ya existe.
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

function log_($msg) { print "[$msg]\n"; }

// =====================================================
// 1. CREAR VOCABULARIOS
// =====================================================
if (!Vocabulary::load('provinces')) {
  Vocabulary::create(['vid' => 'provinces', 'name' => 'Provincias'])->save();
  log_("Created vocabulary: provinces");
} else {
  log_("Vocabulary exists: provinces");
}

if (!Vocabulary::load('municipalities')) {
  Vocabulary::create(['vid' => 'municipalities', 'name' => 'Municipios'])->save();
  log_("Created vocabulary: municipalities");
} else {
  log_("Vocabulary exists: municipalities");
}

// =====================================================
// 2. DATOS DE PROVINCIAS Y MUNICIPIOS
// =====================================================
$provinces = [
  'Pinar del Río' => ['Consolación del Sur', 'Guane', 'Mantua', 'Minas de Matahambre', 'San Juan y Martínez', 'San Luis', 'Sandino', 'Viñales'],
  'Artemisa' => ['Alquízar', 'Bauta', 'Caimito', 'Guanajay', 'Güira de Melena', 'Mariel', 'San Antonio de los Baños', 'San Antonio de las Vegas'],
  'Mayabeque' => ['Batabanó', 'Bejucal', 'Güines', 'Jaruco', 'La Paz', 'Madruga', 'Melena del Sur', 'Nueva Paz', 'San José de las Lajas', 'Santa Cruz del Norte', 'Quivicán'],
  'La Habana' => ['Arroyo Naranjo', 'Boyeros', 'Centro Habana', 'Cerro', 'Cotorro', 'Diez de Octubre', 'Guanabacoa', 'Habana del Este', 'Habana Vieja', 'La Lisa', 'Marianao', 'Playa', 'Plaza de la Revolución', 'Regla', 'San Miguel del Padrón'],
  'Cienfuegos' => ['Abreus', 'Aguada de Pasajeros', 'Cienfuegos', 'Cruces', 'Lajas', 'Palmira', 'Rodas'],
  'Villa Clara' => ['Caibarién', 'Camajuaní', 'Cifuentés', 'Corralillo', 'Encrucijada', 'Manicaragua', 'Placetas', 'Quemado de Güines', 'Rancho Veloz', 'Remedios', 'Sagua la Grande', 'Santa Clara', 'Santo Domingo'],
  'Sancti Spíritus' => ['Cabaiguán', 'Fomento', 'Jatibonico', 'La Sierpe', 'Sancti Spíritus', 'Taguasco', 'Trinidad', 'Yaguajay'],
  'Ciego de Ávila' => ['Chambas', 'Ciego de Ávila', 'Ciro Redondo', 'Florencia', 'Morón', 'Primero de Enero', 'Venezia'],
  'Camagüey' => ['Camagüey', 'Carlos Manuel de Céspedes', 'Esmeralda', 'Guáimaro', 'Jimaguayú', 'Minas', 'Najasa', 'Nuevitas', 'Santa Cruz del Sur', 'Siboney'],
  'Las Tunas' => ['Colón', 'Jesús Menéndez', 'Jobabo', 'Las Tunas', 'Majibacoa', 'Manatí', 'Puerto Padre'],
  'Holguín' => ['Antilla', 'Báguanos', 'Banes', 'Cacocum', 'Calixto García', 'Cueto', 'Gibara', 'Holguín', 'Jobabo', 'Los Banos', 'Mayarí', 'Rafael Freyre', 'Sagua de Tánamo', 'Urbano Noris'],
  'Granma' => ['Baracoa', 'Bayamo', 'Buey Arriba', 'Cauto Cristo', 'Guisa', 'Jiguaní', 'Manzanillo', 'Media Luna', 'Niquero', 'Pilón', 'Río Cauto', 'Yara'],
  'Santiago de Cuba' => ['Contramaestre', 'Guamá', 'Mella', 'Palma Soriano', 'San Luis', 'Santiago de Cuba', 'Songo - La Maya'],
  'Guantánamo' => ['Baracoa', 'Caimanera', 'El Salvador', 'Guantánamo', 'Imías', 'Maisí', 'San Antonio del Sur', 'Yateras'],
  'Isla de la Juventud' => ['Nueva Gerona'],
];

// =====================================================
// 3. FIELD_STORAGE for municipalities.field_province
// =====================================================
$fs = \Drupal\field\Entity\FieldStorageConfig::loadByName('taxonomy_term', 'field_province');
if (!$fs) {
  \Drupal\field\Entity\FieldStorageConfig::create([
    'entity_type' => 'taxonomy_term',
    'field_name' => 'field_province',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
  log_('Storage created: field_province');
}
if (!\Drupal\field\Entity\FieldConfig::loadByName('taxonomy_term', 'municipalities', 'field_province')) {
  \Drupal\field\Entity\FieldConfig::create([
    'entity_type' => 'taxonomy_term',
    'bundle' => 'municipalities',
    'field_name' => 'field_province',
    'label' => 'Provincia',
  ])->save();
  log_('Field created: municipalities.field_province');
}

// =====================================================
// 4. CREAR PROVINCIAS Y MUNICIPIOS
// =====================================================
$total_provinces = 0;
$total_municipalities = 0;

foreach ($provinces as $province_name => $municipalities) {
  // Crear provincia
  $existing = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'provinces', 'name' => $province_name]);

  if (!$existing) {
    $term = Term::create(['vid' => 'provinces', 'name' => $province_name]);
    $term->save();
    log_("Created province: $province_name");
    $total_provinces++;
  } else {
    $term = reset($existing);
    log_("Exists province: $province_name");
  }

  // Crear municipios
  foreach ($municipalities as $municipality_name) {
    $existing = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'municipalities',
        'name' => $municipality_name,
      ]);

    if (!$existing) {
      Term::create([
        'vid' => 'municipalities',
        'name' => $municipality_name,
        'field_province' => ['target_id' => $term->id()],
      ])->save();
      $total_municipalities++;
    }
  }
}

log_("Created: $total_provinces provinces, $total_municipalities municipalities");
log_("DONE");
