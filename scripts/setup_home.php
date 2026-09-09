<?php
// scripts/setup_home.php — idempotent: home page (node 'page' with text_block cards)
// + populate main navigation menu. Makes MascoLive visible to anonymous users.
// Run: ddev drush php:script scripts/setup_home.php

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

function log_($msg) { print $msg . "\n"; }

$em = \Drupal::entityTypeManager();

// Reuse text_block paragraph type (created by setup_pages.php). Create if missing.
if (!\Drupal\paragraphs\Entity\ParagraphsType::load('text_block')) {
  \Drupal\paragraphs\Entity\ParagraphsType::create(['id' => 'text_block', 'label' => 'Text block'])->save();
  log_('paragraph type text_block created');
}

// create-or-reuse text_block paragraph by title
function ensure_paragraph($em, $title, $body) {
  $existing = $em->getStorage('paragraph')->loadByProperties(['field_title' => $title]);
  $p = reset($existing);
  if (!$p) {
    $p = Paragraph::create(['type' => 'text_block', 'field_title' => $title, 'field_body' => ['value' => $body, 'format' => 'basic_html']]);
    $p->save();
  }
  elseif ($p->get('field_body')->value !== $body) {
    $p->set('field_body', ['value' => $body, 'format' => 'basic_html'])->save();
  }
  return ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
}

// --- Home page node ---
$home = $em->getStorage('node')->loadByProperties(['title' => 'Inicio MascoLive']);
$node = reset($home);
$cards = [
  ['Directorio de mascotas', '<p>Consulta el listado de mascotas, filtra por clínica o tipo de mascota.</p><p><a href="/pets">Ver el directorio →</a></p>'],
  ['Nuestras clínicas', '<p>VetCentro Habana, Clínica Mascotas Vedado y VetMiramar ofrecen cardiología, vacunación y cirugía.</p><p><a href="/clinicas">Ver todas las clínicas →</a></p><p><a href="/node/9">VetCentro Habana</a> · <a href="/node/10">Vedado</a> · <a href="/node/11">VetMiramar</a></p>'],
  ['Acerca de MascoLive', '<p>Bienestar Animal: conectamos tutores, clínicas, conductores y vendedores en Cuba.</p><p><a href="/node/17">Conócenos →</a></p>'],
  ['Frontend por rol', '<p>Una vista pública con páginas por actor: visita, tutor, veterinario y admin.</p><p><a href="http://localhost:4321/publico">Abrir frontend →</a></p>'],
  ['Cómo probar cada rol', '<p>Entra con un usuario demo (password = nombre de usuario) y sigue el flujo:</p><ul><li><strong>tutor</strong> (tutor/tutor): 1. <a href="/user/login">entrar</a> → 2. <a href="/node/add/pet">crear tu mascota</a> (solo puedes editar las tuyas)</li><li><strong>veterinario</strong> (veterinario/veterinario): 1. <a href="/user/login">entrar</a> → 2. <a href="/node/add/clinic">crear una clínica</a> o editar cualquier mascota</li><li><strong>conductor</strong> y <strong>vendedor</strong>: acceso de solo lectura — ver <a href="/pets">directorio</a> y <a href="/clinicas">clínicas</a>, sin formularios</li><li><strong>admin</strong> (admin/admin): 1. <a href="/user/login">entrar</a> → 2. <a href="/admin">panel de administración</a></li></ul><p>O entra como <strong>demo</strong> (demo/demo), editor de contenido con acceso al panel admin.</p>'],
];

$paragraphs = [];
foreach ($cards as [$t, $b]) {
  $paragraphs[] = ensure_paragraph($em, $t, $b);
}

if ($node && $node->bundle() === 'page') {
  $node->set('field_components', $paragraphs);
  $node->set('status', 1);
  $node->save();
  log_('home node updated (nid ' . $node->id() . ')');
}
else {
  $values = [
    'type' => 'page',
    'title' => 'Inicio MascoLive',
    'status' => 1,
    'field_components' => $paragraphs,
  ];
  if ($node) { // exists but wrong bundle -> delete
    $node->delete();
  }
  $node = Node::create($values);
  $node->save();
  log_('home node created (nid ' . $node->id() . ')');
}

// --- Set home page to this node ---
$front = "/node/" . $node->id();
$existing = \Drupal::configFactory()->getEditable('system.site')->get('page');
\Drupal::configFactory()->getEditable('system.site')->set('page.front', $front)->save();
log_("front page set to $front");

// --- Populate main navigation menu ---
$clear = \Drupal::database()->query("DELETE FROM menu_link_content_data WHERE menu_name='main'");
$menuItems = [
  ['Inicio', $front, 0],
  ['Directorio', '/pets', 1],
  ['Clínicas', '/clinicas', 2],
  ['Acerca de', '/node/17', 3],
  ['Crear mascota', '/node/add/pet', 4],
  ['Crear clínica', '/node/add/clinic', 5],
];
$mls = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($menuItems as [$title, $uri, $weight]) {
  $existingLink = $mls->loadByProperties(['title' => $title, 'menu_name' => 'main']);
  if (reset($existingLink)) {
    continue;
  }
  $link = $mls->create([
    'title' => $title,
    'menu_name' => 'main',
    'link' => ['uri' => 'internal:' . $uri],
    'weight' => $weight,
    'enabled' => 1,
  ]);
  $link->save();
  log_("menu link '$title' -> $uri");
}

log_('done');
