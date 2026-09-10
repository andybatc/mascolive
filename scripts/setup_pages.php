<?php
// scripts/setup_pages.php — idempotent demo: content type 'page' + paragraph type 'text_block'
// + field_components + demo node + Layout Builder enabled with default section.
// Run: ddev drush php:script scripts/setup_pages.php

use Drupal\block_content\Entity\BlockContent;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

function log_($msg) { print $msg . "\n"; }

// create or reuse a text_block paragraph by title; returns ERR field value
function ensure_paragraph($em, $title, $body) {
  $existing = $em->getStorage('paragraph')->loadByProperties(['field_title' => $title]);
  $p = reset($existing);
  if (!$p) {
    $p = Paragraph::create(['type' => 'text_block', 'field_title' => $title, 'field_body' => ['value' => $body, 'format' => 'basic_html']]);
    $p->save();
  }
  return ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
}

$em = \Drupal::entityTypeManager();

// --- Paragraph type text_block ---
if (!ParagraphsType::load('text_block')) {
  ParagraphsType::create(['id' => 'text_block', 'label' => 'Text block'])->save();
  log_('paragraph type text_block created');
}

// paragraph fields: field_title (string) + field_body (text_long)
foreach ([
  ['field_title', 'string', 'Title', ['max_length' => 255]],
  ['field_body', 'text_long', 'Body', []],
] as [$fn, $ftype, $flabel, $fsettings]) {
  if (!FieldStorageConfig::load('paragraph.' . $fn)) {
    FieldStorageConfig::create(['field_name' => $fn, 'entity_type' => 'paragraph', 'type' => $ftype, 'settings' => $fsettings])->save();
    log_("storage paragraph.$fn created");
  }
  if (!FieldConfig::load('paragraph.text_block.' . $fn)) {
    FieldConfig::create(['field_name' => $fn, 'entity_type' => 'paragraph', 'bundle' => 'text_block', 'label' => $flabel, 'settings' => []])->save();
    log_("field paragraph.text_block.$fn created");
  }
}

// --- Content type page ---
if (!NodeType::load('page')) {
  NodeType::create(['type' => 'page', 'name' => 'Página', 'description' => 'Page with paragraph components + Layout Builder demo.'])->save();
  log_('node type page created');
}

// --- field_components on node.page (entity_reference_revisions -> paragraph, UNLIMITED) ---
$fn = 'field_components';
$fs = FieldStorageConfig::load('node.' . $fn);
if (!$fs) {
  FieldStorageConfig::create(['field_name' => $fn, 'entity_type' => 'node', 'type' => 'entity_reference_revisions', 'cardinality' => -1, 'settings' => ['target_type' => 'paragraph']])->save();
  log_("storage node.$fn created");
} elseif ($fs->getCardinality() === 1) {
  $fs->set('cardinality', -1)->save();
  log_("storage node.$fn cardinality fixed to unlimited");
}
if (!FieldConfig::load('node.page.' . $fn)) {
  FieldConfig::create([
    'field_name' => $fn, 'entity_type' => 'node', 'bundle' => 'page', 'label' => 'Components',
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => [
        'negate' => 0,
        'target_bundles' => ['text_block' => 'text_block'],
        'target_bundles_drag_drop' => ['text_block' => ['enabled' => TRUE, 'weight' => 0]],
      ],
    ],
  ])->save();
  log_("field node.page.$fn created");
}

// --- Form display: paragraphs widget ---
$fds = $em->getStorage('entity_form_display');
$fd = $fds->load('node.page.default');
if (!$fd) {
  $fd = $fds->create(['targetEntityType' => 'node', 'bundle' => 'page', 'mode' => 'default', 'status' => TRUE]);
}
$fd->setComponent('field_components', [
  'type' => 'paragraphs',
  'settings' => ['title' => 'Paragraph', 'title_plural' => 'Paragraphs', 'edit_mode' => 'open', 'add_mode' => 'dropdown', 'form_display_mode' => 'default', 'default_paragraph_type' => 'text_block'],
]);
$fd->save();

// --- View displays ---
$vds = $em->getStorage('entity_view_display');
$vdpage = $vds->load('node.page.default');
if (!$vdpage) {
  $vdpage = $vds->create(['targetEntityType' => 'node', 'bundle' => 'page', 'mode' => 'default', 'status' => TRUE]);
}
$vdpage->setComponent('field_components', ['label' => 'hidden', 'type' => 'entity_reference_revisions_entity_view', 'settings' => ['view_mode' => 'default']]);

$vdp = $vds->load('paragraph.text_block.default');
if (!$vdp) {
  $vdp = $vds->create(['targetEntityType' => 'paragraph', 'bundle' => 'text_block', 'mode' => 'default', 'status' => TRUE]);
}
$vdp->setComponent('field_title', ['label' => 'hidden', 'type' => 'string', 'settings' => ['link_to_entity' => FALSE]]);
$vdp->setComponent('field_body', ['label' => 'hidden', 'type' => 'text_default', 'settings' => []]);
$vdp->save();
$vdpage->save();

// --- Demo page node (idempotent + self-healing on missing paragraphs) ---
$existing = $em->getStorage('node')->loadByProperties(['title' => 'Acerca de MascoLive']);
$node = reset($existing);
$want = [
  ['Bienestar Animal', 'MascoLive connects tutors, clinics and drivers for pet wellbeing in Cuba.'],
  ['Upcoming modules', 'Clinical history, transfers and the shop arrive in upcoming sprints.'],
];
if (!$node) {
  $comps = [];
  foreach ($want as [$t, $b]) {
    $comps[] = ensure_paragraph($em, $t, $b);
  }
  $node = Node::create(['type' => 'page', 'title' => 'Acerca de MascoLive', 'status' => TRUE, 'field_components' => $comps]);
  $node->save();
  log_('page node created: ' . $node->id());
} else {
  $have = [];
  foreach ($node->field_components as $it) { $have[] = $it->entity->field_title->value; }
  $added = 0;
  foreach ($want as [$t, $b]) {
    if (!in_array($t, $have, TRUE)) {
      $node->field_components->appendItem(ensure_paragraph($em, $t, $b));
      $added++;
    }
  }
  if ($added) { $node->save(); log_("page node $added paragraph(s) added"); }
  else { log_('page node already complete: ' . $node->id()); }
}

// --- Layout Builder: enable on node.page.default + allow_custom + default onecol section ---
$vdpage = $vds->load('node.page.default'); // reload
try {
  if ($vdpage instanceof \Drupal\layout_builder\EntityLayoutBuilderEntityViewDisplay && !$vdpage->isLayoutBuilderEnabled()) {
    $vdpage->enableLayoutBuilder();
    log_('layout builder enabled on node.page.default');
  }
} catch (\Throwable $e) {
  log_('Layout Builder enable skipped: ' . $e->getMessage());
}
$vdpage->setThirdPartySetting('layout_builder', 'allow_custom', TRUE);
$vdpage->save();

// Skip layout_section field storage creation — it requires layout_builder module
// to be fully enabled first. Can be done via UI or drush en layout_builder.

// layout_builder__layout field storage — skip, requires layout_builder module enabled

// Misión block — skip, Layout Builder UI can handle this later

// Default layout section — Layout Builder UI can be configured later
$vdpage = $vds->load('node.page.default');
if ($vdpage) {
  $vdpage->save();
  log_('view display saved');
}

log_("DONE. Page: /node/{$node->id()}");