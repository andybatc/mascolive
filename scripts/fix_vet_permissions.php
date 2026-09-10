<?php
/**
 * Trim veterinario role: remove excess clinic/pet "any" permissions.
 * Run: ddev drush php:script scripts/fix_vet_permissions.php
 */

$role = \Drupal::service('entity_type.manager')->getStorage('user_role')->load('veterinario');
if (!$role) { echo "ERROR: role veterinario not found\n"; exit(1); }

$toRemove = [
  'create clinic content',
  'create pet content',
  'edit any clinic content',
  'delete any clinic content',
  'edit any pet content',
  'delete any pet content',
];

$removed = [];
foreach ($toRemove as $perm) {
  if ($role->hasPermission($perm)) {
    $role->revokePermission($perm);
    $removed[] = $perm;
  }
}
$role->save();

echo "=== veterinario permissions AFTER ===\n";
$perms = $role->getPermissions();
sort($perms);
echo "  " . implode("\n  ", $perms) . "\n";
echo "\nRemoved: " . (count($removed) ? implode(', ', $removed) : 'none') . "\n";

echo "\n=== DONE ===\n";