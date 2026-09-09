<?php

/**
 * @file
 * Idempotent provisioning: demo users + roles por nivel de acceso.
 *
 * Niveles de endpoint: anon (sin cuenta), api_consumer (solo APIs),
 * content_editor (demo, ya existe), administrator (admin, ya existe).
 * Actores del documento: tutor, veterinario, conductor, vendedor.
 *
 * Uso: ddev drush php:script scripts/setup_users.php
 * Credenciales de prueba: password = username (solo demo local).
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

function log_($m) {
  print($m . "\n");
}

$roles = [
  'api_consumer' => [
    'label' => 'API consumer',
    'perms' => ['execute default arbitrary graphql requests'],
  ],
  'tutor' => [
    'label' => 'Tutor',
    'perms' => ['create pet content', 'edit own pet content', 'delete own pet content'],
  ],
  'veterinario' => [
    'label' => 'Veterinario',
    'perms' => [
      'create pet content',
      'edit any pet content',
      'delete any pet content',
      'create clinic content',
      'edit any clinic content',
      'delete any clinic content',
    ],
  ],
  'conductor' => ['label' => 'Conductor', 'perms' => []],
  'vendedor' => ['label' => 'Vendedor', 'perms' => []],
];

$em = \Drupal::entityTypeManager();

foreach ($roles as $id => $def) {
  $role = Role::load($id);
  if (!$role) {
    $role = Role::create(['id' => $id, 'label' => $def['label']]);
    $role->save();
    log_("role created: $id");
  }
  foreach ($def['perms'] as $perm) {
    if (!$role->hasPermission($perm)) {
      $role->grantPermission($perm)->save();
      log_("  granted $perm -> $id");
    }
  }
}

$users = [
  'apiuser' => ['roles' => ['api_consumer']],
  'tutor' => ['roles' => ['tutor']],
  'veterinario' => ['roles' => ['veterinario']],
  'conductor' => ['roles' => ['conductor']],
  'vendedor' => ['roles' => ['vendedor']],
];

foreach ($users as $name => $def) {
  $existing = $em->getStorage('user')->loadByProperties(['name' => $name]);
  if ($existing) {
    $user = reset($existing);
    $user->setPassword($name)->set('roles', $def['roles'])->save();
    log_("user updated: $name");
  }
  else {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@mascolive.local',
      'pass' => $name,
      'status' => 1,
      'roles' => $def['roles'],
    ]);
    $user->save();
    log_("user created: $name");
  }
}

log_('setup_users done');