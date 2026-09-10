<?php
/**
 * Differential access debug: node 13 vs 12, anon vs veterinario.
 * Run: ddev drush php:script scripts/debug_access.php
 */

$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
$userStorage = \Drupal::entityTypeManager()->getStorage('user');
$users = [
  'anon' => $userStorage->load(0),
];
$vet = $userStorage->loadByProperties(['name' => 'veterinario']);
$users['veterinario'] = $vet ? reset($vet) : NULL;

$nodes = [12, 13, 14];

foreach ($users as $ulabel => $u) {
  if (!$u) { echo "$ulabel: user not found\n"; continue; }
  foreach ($nodes as $nid) {
    $node = $nodeStorage->load($nid);
    // access with return_as_object=TRUE to read the reason
    $res = $node ? $node->access('view', $u, TRUE) : NULL;
    $resU = $node ? $node->access('update', $u, TRUE) : NULL;
    $allowed = $res ? $res->isAllowed() : 'n/a';
    $reason = method_exists($res, 'getReason') ? (string) $res->getReason() : '(no reason)';
    echo "$ulabel node/$nid  view=" . var_export($allowed, TRUE)
      . " reason='$reason'"
      . " update=" . var_export($resU && $resU->isAllowed(), TRUE)
      . "\n";
  }
}

// Also node_access grants for current user context
echo "\n=== node_access table (all realms) ===\n";
$db = \Drupal::database();
$rows = $db->query("SELECT nid, realm, gid, grant_view FROM node_access ORDER BY realm, gid")->fetchAll();
foreach ($rows as $r) {
  echo "  nid={$r->nid} realm={$r->realm} gid={$r->gid} view={$r->grant_view}\n";
}

// Which modules implement node_access / grants
echo "\n=== module .install / services with node access ===\n";
$mod = \Drupal::moduleHandler()->getModuleList();
foreach (array_keys($mod) as $name) {
  foreach (['hook_node_access', 'hook_node_grants', '_node_access_records', 'node_access_rebuild'] as $pat) {
    $file = DRUPAL_ROOT . '/modules/contrib/' . $name . '/' . $name . '.module';
    if (is_file($file)) {
      $c = file_get_contents($file);
      if (strpos($c, $pat) !== false) {
        echo "  FOUND $pat in $name\n";
      }
    }
  }
}
echo "\n=== DONE ===\n";