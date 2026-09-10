<?php
/**
 * Puebla los 4 portales con UI funcional: secciones con acciones
 * (listar/crear) que cada rol realmente puede hacer, según permisos.
 * Run: ddev drush php:script scripts/setup_portal_ui.php
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

$etm = \Drupal::entityTypeManager();
$accountSwitcher = \Drupal::service('account_switcher');
$nodeStorage = $etm->getStorage('node');
$paraStorage = $etm->getStorage('paragraph');

// portal nid => [rol, bundles de contenido, rutas commerce]
$portals = [
  13 => [
    'rol' => 'veterinario',
    'title' => 'Portal Veterinario',
    'bundles' => ['clinical_history', 'appointment', 'service', 'wallet', 'veterinario'],
    'commerce' => [],
  ],
  14 => [
    'rol' => 'vendedor',
    'title' => 'Portal Vendedor',
    'bundles' => ['wallet', 'vendor'],
    'commerce' => ['/admin/commerce/products', '/admin/commerce/config/stores', '/product/add'],
  ],
  15 => [
    'rol' => 'clinica_admin',
    'title' => 'Portal Admin de Clínica',
    'bundles' => ['clinic', 'veterinario', 'service', 'appointment'],
    'commerce' => [],
  ],
  16 => [
    'rol' => 'administrator',
    'title' => 'Panel de Administración',
    'bundles' => ['clinic', 'pet', 'veterinario', 'vendor', 'transfer', 'appointment', 'clinical_history', 'wallet', 'evaluation', 'service', 'page'],
    'commerce' => ['/admin/commerce/products', '/admin/commerce/config/stores', '/admin/commerce/orders', '/admin/commerce/config/product-types'],
  ],
];

$bundleLabels = [
  'clinic' => 'Clínica',
  'pet' => 'Mascota',
  'veterinario' => 'Veterinario',
  'vendor' => 'Tienda/Vendedor',
  'transfer' => 'Traslado',
  'appointment' => 'Cita',
  'clinical_history' => 'Historia clínica',
  'wallet' => 'Billetera',
  'evaluation' => 'Evaluación',
  'service' => 'Servicio',
  'page' => 'Página',
];

foreach ($portals as $nid => $spec) {
  $node = $nodeStorage->load($nid);
  if (!$node) {
    echo "SKIP: portal $nid no existe\n";
    continue;
  }

  // Cargar usuario del rol (primer usuario con ese rol).
  if ($spec['rol'] === 'administrator') {
    $account = \Drupal\user\Entity\User::load(1);
  } else {
    $users = \Drupal::entityQuery('user')->accessCheck(FALSE)
      ->condition('roles', $spec['rol'])
      ->range(0, 1)
      ->execute();
    $account = $users ? \Drupal\user\Entity\User::load(reset($users)) : NULL;
  }

  if (!$account) {
    echo "SKIP: sin usuario con rol {$spec['rol']} para probar permisos\n";
    continue;
  }

  // Ejecutar chequeos de permiso como ese usuario.
  $accountSwitcher->switchTo($account);
  $sections = [];

  // 1. Sección "Mi trabajo": listados de contenido por bundle.
  $listItems = [];
  foreach ($spec['bundles'] as $bundle) {
    if (\Drupal::currentUser()->hasPermission('access content overview')) {
      $label = $bundleLabels[$bundle] ?? $bundle;
      $listItems[] = Link::fromTextAndUrl(
        "Listar {$label}s",
        Url::fromUri('internal:/admin/content?type=' . $bundle)
      );
    }
  }
  if ($listItems) {
    $sections[] = [
      'title' => 'Mi trabajo',
      'items' => $listItems,
    ];
  }

  // 2. Sección "Crear nuevo", solo bundles con permiso de create.
  $createItems = [];
  foreach ($spec['bundles'] as $bundle) {
    if (\Drupal::currentUser()->hasPermission("create $bundle content")) {
      $label = $bundleLabels[$bundle] ?? $bundle;
      $createItems[] = Link::fromTextAndUrl(
        "Nuevo: {$label}",
        Url::fromUri('internal:/node/add/' . $bundle)
      );
    }
  }
  if ($createItems) {
    $sections[] = [
      'title' => 'Crear nuevo',
      'items' => $createItems,
    ];
  }

  // 3. Sección "Comercio" (vendedor/admin).
  $commerceItems = [];
  foreach ($spec['commerce'] as $path) {
    $label = match ($path) {
      '/admin/commerce/products' => 'Productos',
      '/admin/commerce/config/stores' => 'Tiendas',
      '/admin/commerce/orders' => 'Órdenes',
      '/admin/commerce/config/product-types' => 'Tipos de producto',
      '/product/add' => 'Añadir producto',
      default => $path,
    };
    $commerceItems[] = Link::fromTextAndUrl($label, Url::fromUri('internal:' . $path));
  }
  if ($commerceItems) {
    $sections[] = [
      'title' => 'Comercio',
      'items' => $commerceItems,
    ];
  }

  // 4. Panel admin: secciones globales extra.
  if ($spec['rol'] === 'administrator') {
    $adminItems = [
      Link::fromTextAndUrl('Contenido', Url::fromUri('internal:/admin/content')),
      Link::fromTextAndUrl('Usuarios', Url::fromUri('internal:/admin/people')),
      Link::fromTextAndUrl('Taxonomías', Url::fromUri('internal:/admin/structure/taxonomy')),
      Link::fromTextAndUrl('Menús', Url::fromUri('internal:/admin/structure/menu')),
      Link::fromTextAndUrl('Idiomas', Url::fromUri('internal:/admin/config/regional/language')),
    ];
    $sections[] = ['title' => 'Administración global', 'items' => $adminItems];
  }

  $accountSwitcher->switchBack();

  if (empty($sections)) {
    echo "SKIP: {$spec['title']} (nid $nid): sin acciones disponibles\n";
    continue;
  }

  // Limpiar componentes viejos y construir los nuevos párrafos text_block.
  $node->field_components = [];

  foreach ($sections as $sec) {
    $bodyHtml = '<ul>';
    foreach ($sec['items'] as $item) {
      $url = $item->getUrl();
      $bodyHtml .= '<li><a href="' . $url->toString() . '">' . htmlspecialchars($item->getText()) . '</a></li>';
    }
    $bodyHtml .= '</ul>';

    $para = $paraStorage->create([
      'type' => 'text_block',
      'field_title' => $sec['title'],
      'field_body' => ['value' => $bodyHtml, 'format' => 'full_html'],
    ]);
    $para->save();
    $node->field_components->appendItem($para);
  }

  $node->set('status', 1);
  $node->save();
  echo "OK: {$spec['title']} (nid $nid) — " . count($sections) . " secciones\n";
  foreach ($sections as $sec) {
    echo "   - {$sec['title']} (" . count($sec['items']) . " acciones)\n";
  }
}

echo "\n=== Done ===\n";
echo "Correr: ddev drush cr\n";