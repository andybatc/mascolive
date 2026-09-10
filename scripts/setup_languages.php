<?php

/**
 * @file
 * Adds Spanish and English languages, sets Spanish as default.
 */

use Drupal\language\Entity\ConfigurableLanguage;

function log_($msg) { print "[$msg]\n"; }

// Add Spanish
$languages = \Drupal::languageManager()->getLanguages();

if (!isset($languages['es'])) {
  $lang = ConfigurableLanguage::create(['id' => 'es', 'label' => 'Spanish']);
  $lang->save();
  log_('Created language: Spanish');
} else {
  log_('Language Spanish already exists');
}

// Add English
if (!isset($languages['en'])) {
  $lang = ConfigurableLanguage::create(['id' => 'en', 'label' => 'English']);
  $lang->save();
  log_('Created language: English');
} else {
  log_('Language English already exists');
}

// Set Spanish as default
\Drupal::configFactory()->getEditable('system.site')->set('default_langcode', 'es')->save();
log_('Default language set to Spanish');

// Enable content translation for all content types
$entity_types = ['node', 'paragraph', 'taxonomy_term', 'media', 'block_content'];
foreach ($entity_types as $entity_type) {
  $config = \Drupal::configFactory()->getEditable("content_translation.entity_config.$entity_type");
  $bundles = \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple();
  // For node, only enable on specific types
  if ($entity_type === 'node') {
    $node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $type) {
      $config_name = "content_translation.node." . $type->id();
      $edit = \Drupal::configFactory()->getEditable($config_name);
      if (!$edit->get('translatable')) {
        $edit->set('translatable', TRUE)->save();
        log_("Enabled translation for node type: " . $type->id());
      }
    }
  }
}

log_('DONE');
