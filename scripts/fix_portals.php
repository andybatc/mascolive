<?php
/**
 * Publish portal nodes + inspect commerce products route requirements.
 * Run: ddev drush php:script scripts/fix_portals.php
 */

$db = \Drupal::database();

// 1. Publish portal nodes 13-16
echo "=== Publicar nodos portal ===\n";
$rows = $db->query("SELECT nid, title, status FROM node_field_data WHERE nid IN (13,14,15,16)")->fetchAll();
foreach ($rows as $r) {
  if ((int) $r->status === 0) {
    $db->update('node_field_data')->fields(['status' => 1])->condition('nid', $r->nid)->execute();
    echo "  Publicado: {$r->nid} {$r->title}\n";
  } else {
    echo "  Ya publicado: {$r->nid} {$r->title}\n";
  }
}

// 2. Commerce products route requirements
echo "\n=== Ruta entity.commerce_product.collection ===\n";
try {
  $provider = \Drupal::service('router.route_provider');
  $route = $provider->getRouteByName('entity.commerce_product.collection');
  echo "  path: " . $route->getPath() . "\n";
  echo "  requirements: " . json_encode($route->getRequirements()) . "\n";
} catch (\Exception $e) {
  echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";