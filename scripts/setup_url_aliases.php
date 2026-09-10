<?php

/**
 * @file
 * Crea URL aliases para nodos existentes.
 * Idempotente: reutiliza lo que ya existe.
 */

use Drupal\path_alias\Entity\PathAlias;

function log_($msg) { print "[$msg]\n"; }

// =====================================================
// 1. DEFINIR ALIASES
// =====================================================
$aliases = [
  // Páginas
  '/node/12' => '/acerca-de-mascolive',

  // Clínicas
  '/node/1' => '/clinicas/vetcentro-habana',
  '/node/2' => '/clinicas/mascotas-vedado',
  '/node/3' => '/clinicas/vetmiramar',
];

// =====================================================
// 2. CREAR ALIASES
// =====================================================
foreach ($aliases as $internal_path => $alias_path) {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('path_alias')
    ->loadByProperties(['alias' => $alias_path]);

  if (!$existing) {
    PathAlias::create([
      'path' => $internal_path,
      'alias' => $alias_path,
      'langcode' => 'es',
    ])->save();
    log_("Created alias: $alias_path -> $internal_path");
  } else {
    log_("Exists alias: $alias_path");
  }
}

log_("DONE");
