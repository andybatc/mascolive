<?php
// scripts/rename_paragraph_type.php — rename paragraph bundle 'text_block' -> '_component_text_block'
// so page components follow the '_component_*' convention. Idempotent.
// Run: ddev drush php:script scripts/rename_paragraph_type.php

use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

$OLD = 'text_block';
$NEW = '_component_text_block';

function log_($msg) { print $msg . "\n"; }

// 1. New bundle + field configs (fields live in shared storage; only the
//    per-bundle FieldConfig needs to be created for the new bundle).
if (!ParagraphsType::load($NEW)) {
  ParagraphsType::create(['id' => $NEW, 'label' => 'Text block'])->save();
  log_("paragraph type $NEW created");
}
foreach (['field_title' => ['string', 'Title'], 'field_body' => ['text_long', 'Body']] as $fn => [$type, $label]) {
  if (!FieldConfig::load("paragraph.$NEW.$fn")) {
    FieldConfig::create([
      'field_name' => $fn, 'entity_type' => 'paragraph', 'bundle' => $NEW,
      'label' => $label, 'required' => FALSE, 'settings' => [],
    ])->save();
    log_("field paragraph.$NEW.$fn created");
  }
}

// 2. Migrate entities: load each old-bundle paragraph, switch bundle, save.
$ids = \Drupal::entityQuery('paragraph')->accessCheck(FALSE)->condition('type', $OLD)->execute();
if ($ids) {
  foreach (\Drupal::entityTypeManager()->getStorage('paragraph')->loadMultiple($ids) as $p) {
    $p->set('type', $NEW);
    $p->save();
  }
  log_('migrated ' . count($ids) . " paragraph(s) $OLD -> $NEW");
} else {
  log_("no $OLD paragraphs to migrate");
}

// 3. Point node.page.field_components at the new bundle only.
$fc = FieldConfig::load('node.page.field_components');
if ($fc) {
  $hs = $fc->getSetting('handler_settings');
  $hs['target_bundles'] = [$NEW => $NEW];
  $hs['target_bundles_drag_drop'] = [$NEW => ['enabled' => TRUE, 'weight' => 0]];
  $fc->setSetting('handler_settings', $hs)->save();
  log_('node.page.field_components now targets ' . $NEW);
}

// 4. Form display default paragraph type.
$fd = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('node.page.default');
if ($fd && $fd->getComponent('field_components')) {
  $comp = $fd->getComponent('field_components');
  $comp['settings']['default_paragraph_type'] = $NEW;
  $fd->setComponent('field_components', $comp)->save();
  log_('form display default paragraph type -> ' . $NEW);
}

// 5. View display: new bundle display (reuse old formatter settings if present).
$vds = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$vdp = $vds->load("paragraph.$OLD.default");
if ($vdp) {
  $settings = $vdp->getComponents();
  $status = $vdp->status();
  // create the new-bundle display with the same field formatters
  $vds->create([
    'targetEntityType' => 'paragraph', 'bundle' => $NEW, 'mode' => 'default', 'status' => $status,
  ])->save();
  $new = $vds->load("paragraph.$NEW.default");
  foreach (array_keys($settings) as $fname) {
    if ($fname !== 'langcode') {
      $new->setComponent($fname, $settings[$fname]);
    }
  }
  $new->save();
  log_("view display paragraph.$NEW.default created");
}

// 6. Remove old bundle config (after data migrated).
foreach (['field_title', 'field_body'] as $fn) {
  if (FieldConfig::load("paragraph.$OLD.$fn")) {
    FieldConfig::load("paragraph.$OLD.$fn")->delete();
    log_("field paragraph.$OLD.$fn deleted");
  }
}
if ($vdp && $vds->load("paragraph.$OLD.default")) {
  $vds->load("paragraph.$OLD.default")->delete();
  log_("view display paragraph.$OLD.default deleted");
}
if (ParagraphsType::load($OLD)) {
  ParagraphsType::load($OLD)->delete();
  log_("paragraph type $OLD deleted");
}

log_('DONE. Paragraph type is now ' . $NEW . ' (' . count($ids ?? []) . ' components migrated).');