<?php
/**
 * Dump current site structure for architecture comparison.
 * Run: ddev drush php:script scripts/audit_structure.php
 */

$etm = \Drupal::service('entity_type.manager');

echo "=== CONTENT TYPES (node bundles) ===\n";
$nodeTypes = $etm->getStorage('node_type')->loadMultiple();
foreach ($nodeTypes as $type) {
  echo "  - {$type->id()} ({$type->label()})\n";
}

echo "\n=== FIELDS PER NODE TYPE ===\n";
$fieldConfigs = $etm->getStorage('field_config')->loadMultiple();
$byBundle = [];
foreach ($fieldConfigs as $fc) {
  if ($fc->getTargetEntityTypeId() === 'node') {
    $byBundle[$fc->getTargetBundle()][] = $fc->getName() . ' (' . $fc->getType() . ')';
  }
}
foreach ($byBundle as $bundle => $fields) {
  echo "  $bundle:\n";
  foreach ($fields as $f) {
    echo "    - $f\n";
  }
}

echo "\n=== TAXONOMIES ===\n";
$vocabs = $etm->getStorage('taxonomy_vocabulary')->loadMultiple();
foreach ($vocabs as $v) {
  echo "  - {$v->id()} ({$v->label()})\n";
}

echo "\n=== PARAGRAPH TYPES ===\n";
if ($etm->hasHandler('paragraph', 'storage')) {
  $pTypes = $etm->getStorage('paragraphs_type')->loadMultiple();
  foreach ($pTypes as $pt) {
    echo "  - {$pt->id()} ({$pt->label()})\n";
  }
}

echo "\n=== ROLES ===\n";
$roles = $etm->getStorage('user_role')->loadMultiple();
foreach ($roles as $r) {
  echo "  - {$r->id()} ({$r->label()})\n";
}

echo "\n=== COMMERCE ===\n";
echo "  Product types: ";
$pt = $etm->getStorage('commerce_product_type')->loadMultiple();
echo implode(', ', array_keys($pt)) . "\n";
echo "  Stores: ";
$stores = $etm->getStorage('commerce_store')->loadMultiple();
foreach ($stores as $s) {
  echo $s->id() . '=' . $s->label() . ' (' . $s->bundle() . ', ' . $s->get('default_currency')->value . '), ';
}
echo "\n  Products: ";
$products = $etm->getStorage('commerce_product')->loadMultiple();
echo count($products) . "\n";

echo "\n=== LANGUAGES ===\n";
$langs = $etm->getStorage('configurable_language')->loadMultiple();
foreach ($langs as $l) {
  echo "  - {$l->id()} ({$l->label()})\n";
}

echo "\n=== USERS ===\n";
$users = $etm->getStorage('user')->loadMultiple();
foreach ($users as $u) {
  if ($u->id() > 0) {
    echo "  - {$u->getAccountName()} (uid {$u->id()})\n";
  }
}

echo "\n=== Done ===\n";