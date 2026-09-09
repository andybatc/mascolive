<?php
// scripts/fix_components.php — self-healing, idempotent.
// Ensures every page component paragraph is bundle '_component_text_block' with
// the exact original demo content (titles/bodies from setup_pages.php + setup_home.php),
// re-links page nodes 17/20 in canonical order, and cleans orphan test paragraphs.
// Run: ddev drush php:script scripts/fix_components.php

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

$NEW = '_component_text_block';
function log_($msg) { print $msg . "\n"; }

if (!ParagraphsType::load($NEW)) {
  ParagraphsType::create(['id' => $NEW, 'label' => 'Text block'])->save();
}

// Original demo content (exact, as created by setup scripts).
$CONTENT = [
  'Bienestar Animal' => ['en', 'MascoLive connects tutors, clinics and drivers for pet wellbeing in Cuba.'],
  'Upcoming modules' => ['en', 'Clinical history, transfers and the shop arrive in upcoming sprints.'],
  'Directorio de mascotas' => ['es', '<p>Consulta el listado de mascotas, filtra por clínica o tipo de mascota.</p><p><a href="/pets">Ver el directorio →</a></p>'],
  'Nuestras clínicas' => ['es', '<p>VetCentro Habana, Clínica Mascotas Vedado y VetMiramar ofrecen cardiología, vacunación y cirugía.</p><p><a href="/clinicas">Ver todas las clínicas →</a></p><p><a href="/node/9">VetCentro Habana</a> · <a href="/node/10">Vedado</a> · <a href="/node/11">VetMiramar</a></p>'],
  'Acerca de MascoLive' => ['es', '<p>Bienestar Animal: conectamos tutores, clínicas, conductores y vendedores en Cuba.</p><p><a href="/node/17">Conócenos →</a></p>'],
  'Frontend por rol' => ['es', '<p>Una vista pública con páginas por actor: visita, tutor, veterinario y admin.</p><p><a href="http://localhost:4321/publico">Abrir frontend →</a></p>'],
  'Cómo probar cada rol' => ['es', '<p>Entra con un usuario demo (password = nombre de usuario) y sigue el flujo:</p><ul><li><strong>tutor</strong> (tutor/tutor): 1. <a href="/user/login">entrar</a> → 2. <a href="/node/add/pet">crear tu mascota</a> (solo puedes editar las tuyas)</li><li><strong>veterinario</strong> (veterinario/veterinario): 1. <a href="/user/login">entrar</a> → 2. <a href="/node/add/clinic">crear una clínica</a> o editar cualquier mascota</li><li><strong>conductor</strong> y <strong>vendedor</strong>: acceso de solo lectura — ver <a href="/pets">directorio</a> y <a href="/clinicas">clínicas</a>, sin formularios</li><li><strong>admin</strong> (admin/admin): 1. <a href="/user/login">entrar</a> → 2. <a href="/admin">panel de administración</a></li></ul><p>O entra como <strong>demo</strong> (demo/demo), editor de contenido con acceso al panel admin.</p>'],
];

// 1. Self-heal paragraphs: bundle + exact values.
$em = \Drupal::entityTypeManager();
foreach ($CONTENT as $title => [$lang, $body]) {
  $existing = $em->getStorage('paragraph')->loadByProperties(['field_title' => $title]);
  $p = reset($existing);
  $saved = '';
  if (!$p) {
    $p = Paragraph::create(['type' => $NEW, 'field_title' => $title, 'field_body' => ['value' => $body, 'format' => 'basic_html']]);
    $p->save();
    $saved = 'created';
  }
  else {
    if ($p->bundle() !== $NEW) { $p->set('type', $NEW); }
    if ($p->field_title->value !== $title || $p->field_body->value !== $body) {
      $p->set('field_title', $title)->set('field_body', ['value' => $body, 'format' => 'basic_html']);
      $saved = 'restored';
    }
    if ($saved) { $p->save(); }
  }
  log_("paragraph '$title' (id {$p->id()}) " . ($saved ?: 'ok'));
}

// 2. Re-link page nodes in canonical order (paragraph order from the setup scripts).
$order = [
  'Acerca de MascoLive' => ['Bienestar Animal', 'Upcoming modules'],
  'Inicio MascoLive' => ['Directorio de mascotas', 'Nuestras clínicas', 'Acerca de MascoLive', 'Frontend por rol', 'Cómo probar cada rol'],
];
foreach ($order as $nodeTitle => $paraTitles) {
  $node = reset($em->getStorage('node')->loadByProperties(['title' => $nodeTitle]));
  if (!$node || $node->bundle() !== 'page') { log_("WARN: node '$nodeTitle' missing"); continue; }
  $refs = [];
  foreach ($paraTitles as $t) {
    $p = reset($em->getStorage('paragraph')->loadByProperties(['field_title' => $t]));
    if ($p) { $refs[] = ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()]; }
  }
  $node->set('field_components', $refs)->save();
  log_("node '{$node->getTitle()}' -> " . count($refs) . ' component(s) (nid ' . $node->id() . ')');
}

// 3. Clean up throwaway test paragraphs (created during migration testing).
$tests = \Drupal::entityQuery('paragraph')->accessCheck(FALSE)->condition('field_title', 'TEST-%', 'LIKE')->execute();
if ($tests) {
  \Drupal::entityTypeManager()->getStorage('paragraph')->delete(\Drupal::entityTypeManager()->getStorage('paragraph')->loadMultiple($tests));
  log_('deleted ' . count($tests) . ' TEST-% paragraph(s)');
}

log_('DONE');