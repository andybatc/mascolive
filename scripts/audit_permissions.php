<?php
/**
 * Permissions audit + login redirect check.
 * Run: ddev drush php:script scripts/audit_permissions.php
 */

$etm = \Drupal::service('entity_type.manager');
$roleStorage = $etm->getStorage('user_role');

echo "=== ROLES Y PERMISOS ===\n";
foreach ($roleStorage->loadMultiple() as $rid => $role) {
  $perms = $role->getPermissions();
  sort($perms);
  echo "\n[$rid] " . $role->label() . " (" . count($perms) . " perms)\n";
  echo "  " . implode("\n  ", $perms) . "\n";
}

echo "\n=== ANONYMOUS KEY ACCESS ===\n";
$anon = $roleStorage->load('anonymous');
$anonPerms = $anon ? $anon->getPermissions() : [];
$checks = [
  'view published content' => 'access content',
  'view node JSON:API' => 'access jsonapi resource list',
  'access content overview (admin/content)' => 'access content overview',
  'commerce view products' => 'view pet_product commerce_product',
];
foreach ($checks as $label => $perm) {
  echo "  " . ($anon && in_array($perm, $anonPerms) ? 'YES' : 'no ') . "  $label ($perm)\n";
}

echo "\n=== LOGIN REDIRECT CONFIG ===\n";
$userSettings = \Drupal::config('user.settings');
echo "  user.settings keys: " . implode(', ', array_keys($userSettings->getRawData())) . "\n";
echo "  login_redirect: " . var_export($userSettings->get('login_redirect'), TRUE) . "\n";

echo "\n=== USERS BY ROLE ===\n";
$userStorage = $etm->getStorage('user');
$query = $userStorage->getQuery()->accessCheck(FALSE)->condition('status', 1);
$uids = $query->execute();
foreach ($userStorage->loadMultiple($uids) as $u) {
  if ($u->id() == 1) { echo "  uid1 superadmin\n"; continue; }
  $roles = $u->getRoles(TRUE);
  echo "  {$u->getAccountName()} (" . ($u->getEmail() ?? 'no-email') . ") roles: " . implode(',', $roles) . "\n";
}
echo "\n=== DONE ===\n";