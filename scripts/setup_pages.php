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
$vdpage = $vds->load('node.page.default'); // reload (class swaps to LayoutBuilderEntityViewDisplay when enabled)
if (!$vdpage->isLayoutBuilderEnabled()) {
  $vdpage->enableLayoutBuilder();
  log_('layout builder enabled on node.page.default');
}
$vdpage->setThirdPartySetting('layout_builder', 'allow_custom', TRUE);
$vdpage->save();

// ensure layout_builder__layout field storage/field exist (needed for per-node overrides)
$missing_storage = !\Drupal::config('field.storage.node.layout_builder__layout')->get('type');
if ($missing_storage) {
  FieldStorageConfig::create([
    'field_name' => 'layout_builder__layout',
    'entity_type' => 'node',
    'type' => 'layout_section',
    'settings' => [],
    'locked' => TRUE,
  ])->save();
  log_('field.storage.node.layout_builder__layout created');
}
if (!FieldConfig::load('node.page.layout_builder__layout')) {
  FieldConfig::create([
    'field_name' => 'layout_builder__layout',
    'entity_type' => 'node',
    'bundle' => 'page',
    'label' => 'Layout',
  ])->save();
  log_('field.field.node.page.layout_builder__layout created');
}

// custom block (block_content basic) placed in the default layout
$blocks = $em->getStorage('block_content')->loadByProperties(['info' => 'Misión']);
$block = reset($blocks);
if (!$block) {
  $block = BlockContent::create(['type' => 'basic', 'info' => 'Misión',
    'body' => ['value' => 'Every pet deserves a healthy life. MascoLive works for that.', 'format' => 'basic_html']]);
  $block->save();
  log_('block_content Misión created: ' . $block->uuid());
}

// Default layout: leave a single empty onecol (field blocks only work on overrides)
$vdpage = $vds->load('node.page.default');
if (!empty($vdpage->getSections())) {
  $vdpage->setSection(0, new \Drupal\layout_builder\Section('layout_onecol'));
  log_('default section reset to empty onecol');
}
// Ensure the default section renders field_components for ALL page nodes
// (previously only the demo node override had it; new nodes rendered blank).
$section0 = $vdpage->getSection(0);
if ($section0 && !isset($section0->getComponents()['components-field'])) {
  $section0->appendComponent(new \Drupal\layout_builder\SectionComponent('components-field', 'content', [
    'id' => 'field_block:node:page:field_components',
    'label' => 'Components',
    'label_display' => '0',
    'provider' => 'layout_builder',
    'status' => TRUE,
    'info' => '',
    'view_mode' => 'full',
  ]));
  log_('component components-field appended to default section');
}
$vdpage->save();

// Override layout on the demo node: Misión custom block + field block (paragraphs)
$manager = \Drupal::service('plugin.manager.layout_builder.section_storage');
$override = $manager->load('overrides', [
  'entity' => \Drupal\Core\Plugin\Context\EntityContext::fromEntity($node),
  'view_mode' => new \Drupal\Core\Plugin\Context\Context(new \Drupal\Core\Plugin\Context\ContextDefinition('string'), 'default'),
]);
if (empty($override->getSections())) {
  $override->appendSection(new \Drupal\layout_builder\Section('layout_onecol'));
  log_('override section created on node ' . $node->id());
}
$section = $override->getSection(0);
if (!isset($section->getComponents()['mision-block'])) {
  $section->appendComponent(new \Drupal\layout_builder\SectionComponent('mision-block', 'content', [
    'id' => 'block_content:' . $block->uuid(),
    'label' => 'Misión',
    'label_display' => '0',
    'provider' => 'block_content',
    'status' => TRUE,
    'info' => '',
    'view_mode' => 'full',
  ]));
  log_('component mision-block appended to override');
}
if (!isset($section->getComponents()['components-field'])) {
  $section->appendComponent(new \Drupal\layout_builder\SectionComponent('components-field', 'content', [
    'id' => 'field_block:node:page:field_components',
    'label' => 'Components',
    'label_display' => '0',
    'provider' => 'layout_builder',
    'formatter' => ['label' => 'hidden', 'type' => 'entity_reference_revisions_entity_view', 'settings' => ['view_mode' => 'default'], 'third_party_settings' => []],
  ]));
  log_('component components-field appended to override');
}
$override->save();
log_('override saved on node ' . $node->id());

log_("DONE. Page: /node/{$node->id()} | blocks: " . count($vdpage->getSections()));