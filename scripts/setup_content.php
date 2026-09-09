<?php

function log_($msg, $status = "ok") { print("[$status] $msg\n"); }

use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

$types = [
  'clinic' => 'Clínica',
  'pet' => 'Mascota',
];
foreach ($types as $machine => $label) {
  if (!NodeType::load($machine)) {
    $t = NodeType::create(['type' => $machine, 'name' => $label]);
    $t->save();
    log_("Created content type: $machine", 'success');
  }
  else {
    log_("Content type already exists: $machine", 'ok');
  }
}

// Clinic fields
$fields = [
  ['node', 'clinic', 'field_specialty', 'Especialidad', 'string', 50],
  ['node', 'clinic', 'field_address', 'Dirección', 'string', 255],
];
foreach ($fields as [$et, $bundle, $name, $label, $type, $len]) {
  $storage = FieldStorageConfig::loadByName($et, $name);
  if (!$storage) {
    FieldStorageConfig::create([
      'entity_type' => $et,
      'field_name' => $name,
      'type' => $type,
      'settings' => ['max_length' => $len],
    ])->save();
  }
  if (!FieldConfig::loadByName($et, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $et,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
    ])->save();
  }
}
log_('Clinic fields ready', 'success');

// Pet -> Clinic entity reference (interconnection)
$ref = 'field_clinic';
if (!FieldStorageConfig::loadByName('node', $ref)) {
  FieldStorageConfig::create([
    'entity_type' => 'node',
    'field_name' => $ref,
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'node'],
  ])->save();
}
if (!FieldConfig::loadByName('node', 'pet', $ref)) {
  FieldConfig::create([
    'entity_type' => 'node',
    'bundle' => 'pet',
    'field_name' => $ref,
    'label' => 'Clínica de referencia',
    'settings' => ['handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]],
  ])->save();
}
log_('field_clinic (pet -> clinic) ready', 'success');

// Taxonomy: pet_type vocabulary (Tipo de mascota)
if (!Vocabulary::load('pet_type')) {
  Vocabulary::create(['vid' => 'pet_type', 'name' => 'Tipo de mascota'])->save();
  log_('Created vocabulary: pet_type', 'success');
}
else {
  log_('Vocabulary already exists: pet_type', 'ok');
}

$term_ids = [];
foreach (['Perro', 'Gato'] as $term_name) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'pet_type', 'name' => $term_name]);
  if ($existing) {
    $term_ids[$term_name] = reset($existing)->id();
    log_("Term already exists: $term_name (tid {$term_ids[$term_name]})", 'ok');
  }
  else {
    $t = Term::create(['vid' => 'pet_type', 'name' => $term_name]);
    $t->save();
    $term_ids[$term_name] = $t->id();
    log_("Created term: $term_name (tid {$term_ids[$term_name]})", 'success');
  }
}

// Pet -> pet_type taxonomy reference
$ref2 = 'field_pet_type';
if (!FieldStorageConfig::loadByName('node', $ref2)) {
  FieldStorageConfig::create([
    'entity_type' => 'node',
    'field_name' => $ref2,
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
}
if (!FieldConfig::loadByName('node', 'pet', $ref2)) {
  FieldConfig::create([
    'entity_type' => 'node',
    'bundle' => 'pet',
    'field_name' => $ref2,
    'label' => 'Tipo de mascota',
    'settings' => ['handler_settings' => ['target_bundles' => ['pet_type' => 'pet_type']]],
  ])->save();
}
log_('field_pet_type (pet -> taxonomy term) ready', 'success');

// Demo nodes (idempotent: reuse existing nodes by title + type)
$node_exists = function ($type, $title) {
  return \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => $type, 'title' => $title]);
};

$clinics = [
  'VetCentro Habana' => ['Cardiología', 'Calle 23 esq. M, Vedado'],
  'Clínica Mascotas Vedado' => ['Vacunación', 'Calle Linea y 12, Vedado'],
  'VetMiramar' => ['Cirugía', '5ta Avenida esq. 80, Miramar'],
];
$nids = [];
foreach ($clinics as $title => [$esp, $dir]) {
  $existing = $node_exists('clinic', $title);
  if ($existing) {
    $nids[$title] = reset($existing)->id();
    log_("Clinic already exists: $title (nid {$nids[$title]})", 'ok');
    continue;
  }
  $n = Node::create([
    'type' => 'clinic',
    'title' => $title,
    'status' => 1,
    'field_specialty' => $esp,
    'field_address' => $dir,
  ]);
  $n->save();
  $nids[$title] = $n->id();
  log_("Created clinic: $title (nid {$nids[$title]})", 'success');
}

$pets = [
  'Rex' => ['VetCentro Habana', 'Perro'],
  'Luna' => ['VetCentro Habana', 'Perro'],
  'Milo' => ['Clínica Mascotas Vedado', 'Gato'],
  'Kiara' => ['VetMiramar', 'Gato'],
  'Toby' => ['VetMiramar', 'Perro'],
];
foreach ($pets as $title => [$clinic, $ptype]) {
  $existing = $node_exists('pet', $title);
  if ($existing) {
    $n = reset($existing);
    if ($n->get('field_pet_type')->isEmpty()) {
      $n->set('field_pet_type', ['target_id' => $term_ids[$ptype]]);
      $n->save();
      log_("Pet $title: assigned pet_type $ptype", 'ok');
    }
    else {
      log_("Pet already exists with pet_type: $title", 'ok');
    }
    continue;
  }
  $n = Node::create([
    'type' => 'pet',
    'title' => $title,
    'status' => 1,
    'field_clinic' => ['target_id' => $nids[$clinic]],
    'field_pet_type' => ['target_id' => $term_ids[$ptype]],
  ]);
  $n->save();
  log_("Created pet: $title (nid {$n->id()})", 'success');
}
log_('5 demo pets ready', 'success');