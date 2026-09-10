<?php
/**
 * Fix: content language coherence + store type + publish node 12.
 * - Set all nodes langcode=es (base), restore original Spanish titles.
 * - Delete leftover 'en' translation rows.
 * - Fix store 1 bundle: default -> online (config exists), keep CUP.
 * - Publish node 12 (Acerca de MascoLive).
 */
use Drupal\node\Entity\Node;
use Drupal\commerce_store\Entity\CommerceStore;

$titles = [
  1 => 'VetCentro Habana',
  2 => 'Clínica Mascotas Vedado',
  3 => 'VetMiramar',
  4 => 'Rex',
  5 => 'Luna',
  6 => 'Milo',
  7 => 'Kiara',
  8 => 'Toby',
  9 => 'Dr. Carlos Pérez',
  10 => 'Dra. María López',
  11 => 'PetShop Habana',
  12 => 'Acerca de MascoLive',
];

// 1) Store: type default -> online (SQL-safe; do NOT save entity afterwards).
$conn = \Drupal::database();
$row = $conn->query("SELECT type FROM {commerce_store_field_data} WHERE store_id = 1")->fetchField();
echo "Store 1 type before: $row\n";
\Drupal::service('cache_tags.invalidator')->invalidateTags(['config:commerce_store.commerce_store_type.online']);
if ($row !== 'online') {
  $conn->update('commerce_store_field_data')
    ->fields(['type' => 'online'])
    ->condition('store_id', 1)
    ->execute();
  $conn->update('commerce_store')
    ->fields(['type' => 'online'])
    ->condition('store_id', 1)
    ->execute();
  echo "Store 1 type set to online (field table + base table)\n";
}

// 2) Nodes: langcode es + restore titles.
$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($titles as $nid => $title) {
  /** @var \Drupal\node\Entity\Node $node */
  $node = $storage->load($nid);
  if (!$node) { echo "node $nid NOT FOUND\n"; continue; }
  // Delete any en translation rows (default_langcode=0) for this node.
  $conn->delete('node_field_data')
    ->condition('nid', $nid)
    ->condition('langcode', 'en')
    ->condition('default_langcode', 0)
    ->execute();
  $node->set('langcode', 'es');
  $node->setTitle($title);
  if ($nid == 12) {
    $node->setPublished(TRUE);
    echo "node $nid: published, ";
  }
  $node->save();
  echo "node $nid: lang=" . $node->get('langcode')->value . " title='$title'\n";
}
echo "DONE\n";