<?php

/**
 * @file
 * Portales por rol + rol Admin de Clínica (arquitectura "El Nido").
 *
 * Crear:
 *  1. Rol clinica_admin + usuario admin_clinica
 *  2. Content type "service" (Servicio) con precio/duración + refs clinic/vet
 *  3. field_clinic en veterinario (vincula vet <-> clínica)
 *  4. Permisos por rol (veterinario, vendedor, conductor, clinica_admin)
 *  5. 4 páginas portal (page nodes)
 *  6. 4 menús de portal con links a secciones
 *
 * Uso: ddev drush php:script scripts/setup_portales.php
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\path_alias\Entity\PathAlias;

function log_($m) { print($m . "\n"); }

$em = \Drupal::entityTypeManager();

// ============================================================
// 1. Rol clinica_admin + usuario demo
// ============================================================
log_("=== 1. Rol Admin de Clínica ===");
$role = Role::load('clinica_admin');
if (!$role) {
  $role = Role::create(['id' => 'clinica_admin', 'label' => 'Admin de Clínica']);
  $role->save();
  log_("  rol creado: clinica_admin");
}

$user = $em->getStorage('user')->loadByProperties(['name' => 'admin_clinica']);
if ($user) {
  $user = reset($user);
  $user->setPassword('admin_clinica')->set('roles', ['clinica_admin'])->save();
  log_("  usuario actualizado: admin_clinica");
}
else {
  User::create([
    'name' => 'admin_clinica',
    'mail' => 'admin_clinica@mascolive.local',
    'pass' => 'admin_clinica',
    'status' => 1,
    'roles' => ['clinica_admin'],
  ])->save();
  log_("  usuario creado: admin_clinica");
}

// ============================================================
// 2. Content type "service"
// ============================================================
log_("\n=== 2. Content type service ===");
$nodeTypeStorage = $em->getStorage('node_type');
$serviceType = $nodeTypeStorage->load('service');
if (!$serviceType) {
  $serviceType = $nodeTypeStorage->create([
    'type' => 'service',
    'name' => 'Servicio',
    'description' => 'Servicio que ofrece una clínica o veterinario (nombre, descripción, precio, duración).',
  ]);
  $serviceType->save();
  log_("  content type creado: service");
}

// Fields: definición por instancia (bundle.field)
$fieldStorage = $em->getStorage('field_storage_config');
$fieldConfig = $em->getStorage('field_config');

// Helper: crear storage + instancia idempotente
function ensure_field($fieldStorage, $fieldConfig, $fieldName, $entityType, $bundle, $type, $label, $required, $settings = [], $cardinality = 1) {
  if (!$fieldStorage->load("$entityType.$fieldName")) {
    $storageData = [
      'field_name' => $fieldName,
      'entity_type' => $entityType,
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $type === 'entity_reference' ? ['target_type' => $settings['target_type']] : $settings,
    ];
    $fieldStorage->create($storageData)->save();
    log_("  field storage: $entityType.$fieldName");
  }
  if (!$fieldConfig->load("$entityType.$bundle.$fieldName")) {
    $instanceData = [
      'field_name' => $fieldName,
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => $type === 'entity_reference'
        ? ['handler' => 'default:node', 'handler_settings' => $settings['handler_settings'] ?? []]
        : [],
    ];
    $fieldConfig->create($instanceData)->save();
    log_("  field instance: $bundle.$fieldName");
  }
}

$nodeRef = ['target_type' => 'node'];

// service: campos descriptivos
ensure_field($fieldStorage, $fieldConfig, 'field_description', 'node', 'service', 'text_long', 'Descripción', TRUE, []);
ensure_field($fieldStorage, $fieldConfig, 'field_price', 'node', 'service', 'string', 'Precio (CUP)', TRUE, ['max_length' => 32]);
ensure_field($fieldStorage, $fieldConfig, 'field_duration', 'node', 'service', 'string', 'Duración', TRUE, ['max_length' => 32]);

// field_clinic: storage compartido, instancia en service y veterinario
ensure_field($fieldStorage, $fieldConfig, 'field_clinic', 'node', 'service', 'entity_reference', 'Clínica', FALSE, $nodeRef + ['handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]], -1);
ensure_field($fieldStorage, $fieldConfig, 'field_clinic', 'node', 'veterinario', 'entity_reference', 'Clínica', FALSE, $nodeRef + ['handler_settings' => ['target_bundles' => ['clinic' => 'clinic']]], -1);

// field_veterinario: storage compartido, instancia en service
ensure_field($fieldStorage, $fieldConfig, 'field_veterinario', 'node', 'service', 'entity_reference', 'Veterinario', FALSE, $nodeRef + ['handler_settings' => ['target_bundles' => ['veterinario' => 'veterinario']]], -1);

// Form display node.service.default
$formDisplay = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'service', 'default');
$formDisplay->setComponent('title', ['type' => 'string_textfield']);
foreach ([
  'field_description' => ['type' => 'text_textarea', 'weight' => 1],
  'field_price' => ['type' => 'string_textfield', 'weight' => 2],
  'field_duration' => ['type' => 'string_textfield', 'weight' => 3],
  'field_clinic' => ['type' => 'entity_reference_autocomplete', 'weight' => 4, 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']],
  'field_veterinario' => ['type' => 'entity_reference_autocomplete', 'weight' => 5, 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']],
] as $name => $comp) {
  $formDisplay->setComponent($name, $comp);
}
$formDisplay->setComponent('status', ['type' => 'boolean_checkbox', 'weight' => 6]);
$formDisplay->setComponent('path', ['type' => 'path', 'weight' => 7]);
$formDisplay->save();
log_("  form display: node.service.default");

// ============================================================
// 4. Permisos por rol
// ============================================================
log_("\n=== 4. Permisos por rol ===");
$rolePerms = [
  'veterinario' => [
    'access administration pages', 'access content overview',
    'access content',
    'create clinical_history content', 'edit own clinical_history content', 'delete own clinical_history content',
    'create appointment content', 'edit own appointment content',
    'create service content', 'edit own service content', 'delete own service content',
    'create wallet content', 'edit own wallet content',
    'view own unpublished content',
  ],
  'vendedor' => [
    'access administration pages', 'access content overview',
    'access content',
    'create pet_product commerce_product', 'update any pet_product commerce_product',
    'delete any pet_product commerce_product', 'view pet_product commerce_product',
    'edit own vendor content',
    'create wallet content', 'edit own wallet content',
    'view own unpublished content',
  ],
  'conductor' => [
    'access content',
    'create transfer content', 'edit own transfer content',
    'create wallet content', 'edit own wallet content',
    'view own unpublished content',
  ],
  'clinica_admin' => [
    'access administration pages', 'access content overview',
    'access content',
    'edit any clinic content',
    'create service content', 'edit any service content', 'delete any service content',
    'edit any veterinario content',
    'view any unpublished content',
  ],
];

foreach ($rolePerms as $rid => $perms) {
  $r = Role::load($rid);
  if (!$r) { log_("  !! rol no existe: $rid — saltando"); continue; }
  foreach ($perms as $perm) {
    if (!$r->hasPermission($perm)) {
      $r->grantPermission($perm)->save();
      log_("  $rid += $perm");
    }
  }
}

// ============================================================
// 5. Páginas portal
// ============================================================
log_("\n=== 5. Páginas portal ===");
$nodeStorage = $em->getStorage('node');

$portalPages = [
  'portal-veterinario' => [
    'title' => 'Portal Veterinario',
    'body' => "Espacio operativo diario del veterinario: agenda de turnos, historia clínica de sus pacientes, mis servicios (nombre, descripción, precio, duración), evaluaciones recibidas y facturación.",
  ],
  'portal-vendedor' => [
    'title' => 'Portal Vendedor',
    'body' => "Gestión comercial: alta y edición de productos, ficha de mi tienda (información, métodos de pago, envío, horarios), órdenes recibidas y transacciones.",
  ],
  'portal-clinica' => [
    'title' => 'Portal Admin de Clínica',
    'body' => "Administración de la clínica: información y estado de los veterinarios, ficha pública de la clínica, servicios de la institución y evaluaciones recibidas.",
  ],
  'portal-admin' => [
    'title' => 'Panel de Administración',
    'body' => "Centro de control: aprobaciones (clínicas, veterinarios, conductores, productos), gestión de traslados, billetera global, moderación de evaluaciones y reportes.",
  ],
];

foreach ($portalPages as $alias => $def) {
  $existing = $nodeStorage->loadByProperties(['title' => $def['title']]);
  $existingNode = $existing ? reset($existing) : NULL;
  if (!$existingNode) {
    $node = $nodeStorage->create([
      'type' => 'page',
      'title' => $def['title'],
      'body' => ['value' => $def['body'], 'format' => 'basic_html'],
      'status' => 1,
      'langcode' => 'es',
    ]);
    $node->save();
    log_("  página creada: {$def['title']} (nid {$node->id()}) alias /$alias");
    $source = '/node/' . $node->id();
    $aliasExists = $em->getStorage('path_alias')->loadByProperties(['alias' => '/' . $alias]);
    if (!$aliasExists) {
      PathAlias::create(['path' => $source, 'alias' => '/' . $alias, 'langcode' => 'es'])->save();
    }
  }
  else {
    log_("  ya existe: {$def['title']}");
  }
}

// ============================================================
// 6. Menús de portal
// ============================================================
log_("\n=== 6. Menús de portal ===");
$menuStorage = $em->getStorage('menu');

$menus = [
  'portal_veterinario' => 'Portal Veterinario',
  'portal_vendedor' => 'Portal Vendedor',
  'portal_clinica' => 'Portal Admin de Clínica',
  'portal_admin' => 'Panel de Administración',
];

foreach ($menus as $id => $label) {
  if (!$menuStorage->load($id)) {
    $menuStorage->create(['id' => $id, 'label' => $label, 'description' => 'Menú del ' . $label])->save();
    log_("  menú creado: $id");
  }
}

$menuLinks = [
  'portal_veterinario' => [
    ['Inicio', 'internal:/es/portal-veterinario', 0],
    ['Agenda', 'internal:/es/admin/content?type=appointment', 1],
    ['Historia Clínica', 'internal:/es/admin/content?type=clinical_history', 2],
    ['Mis Servicios', 'internal:/es/admin/content?type=service', 3],
    ['Evaluaciones recibidas', 'internal:/es/admin/content?type=evaluation', 4],
    ['Facturación y Cobros', 'internal:/es/admin/commerce/orders', 5],
  ],
  'portal_vendedor' => [
    ['Inicio', 'internal:/es/portal-vendedor', 0],
    ['Gestión de productos', 'internal:/es/admin/commerce/products', 1],
    ['Mi tienda', 'internal:/es/admin/commerce/config/stores', 2],
    ['Gestión de órdenes', 'internal:/es/admin/commerce/orders', 3],
  ],
  'portal_clinica' => [
    ['Inicio', 'internal:/es/portal-clinica', 0],
    ['Veterinarios', 'internal:/es/admin/content?type=veterinario', 1],
    ['Ficha de la clínica', 'internal:/es/admin/content?type=clinic', 2],
    ['Mis servicios', 'internal:/es/admin/content?type=service', 3],
    ['Evaluaciones recibidas', 'internal:/es/admin/content?type=evaluation', 4],
  ],
  'portal_admin' => [
    ['Inicio', 'internal:/es/portal-admin', 0],
    ['Aprobaciones', 'internal:/es/admin/people', 1],
    ['Clínicas', 'internal:/es/admin/content?type=clinic', 2],
    ['Conductores', 'internal:/es/admin/content?type=transfer', 3],
    ['Traslados', 'internal:/es/admin/content?type=transfer', 4],
    ['Billetera global', 'internal:/es/admin/content?type=wallet', 5],
    ['Evaluaciones', 'internal:/es/admin/content?type=evaluation', 6],
  ],
];

$linkStorage = $em->getStorage('menu_link_content');
foreach ($menuLinks as $menuId => $links) {
  foreach ($links as [$title, $uri, $weight]) {
    $exists = $linkStorage->loadByProperties(['title' => $title, 'menu_name' => $menuId]);
    if ($exists) { continue; }
    $linkStorage->create([
      'title' => $title,
      'link' => ['uri' => $uri],
      'menu_name' => $menuId,
      'weight' => $weight,
      'expanded' => TRUE,
      'enabled' => TRUE,
    ])->save();
    log_("  link: [$menuId] $title");
  }
}

// ============================================================
// 7. Data demo: servicios + vincular veterinarios a clínicas
// ============================================================
log_("\n=== 7. Data demo ===");

// Vincular vets (nid 9, 10) a clínicas
$vetLinks = [
  9 => [1],   // Dr. Carlos Pérez -> VetCentro Habana
  10 => [2],  // Dra. María López -> Clínica Mascotas Vedado
];
foreach ($vetLinks as $nid => $clinics) {
  $node = $nodeStorage->load($nid);
  if ($node && $node->bundle() === 'veterinario') {
    $node->set('field_clinic', $clinics)->save();
    log_("  vet nid $nid vinculado a clínica(s): " . implode(',', $clinics));
  }
}

$services = [
  'Consulta General' => ['VetCentro Habana', 500, '30 min', 'Examen general, diagnóstico y orientación.'],
  'Urgencias' => ['VetCentro Habana', 800, '15 min', 'Atención prioritaria para casos urgentes.'],
  'Vacunación' => ['Clínica Mascotas Vedado', 400, '20 min', 'Esquema de vacunación básico.'],
  'Cirugía Menor' => ['VetMiramar', 2500, '60 min', 'Procedimientos quirúrgicos menores.'],
];

$clinicNodes = [];
foreach ($nodeStorage->loadMultiple([1, 2, 3]) as $n) {
  $clinicNodes[$n->label()] = $n->id();
}

foreach ($services as $title => [$clinicName, $price, $duration, $desc]) {
  $existingSvc = $nodeStorage->loadByProperties(['title' => $title, 'type' => 'service']);
  if ($existingSvc) { continue; }
  $nodeStorage->create([
    'type' => 'service',
    'title' => $title,
    'status' => 1,
    'langcode' => 'es',
    'field_description' => ['value' => $desc, 'format' => 'basic_html'],
    'field_price' => (string) $price,
    'field_duration' => $duration,
    'field_clinic' => $clinicNodes[$clinicName] ?? NULL,
  ])->save();
  log_("  servicio creado: $title ($price CUP)");
}

log_("\nsetup_portales done");