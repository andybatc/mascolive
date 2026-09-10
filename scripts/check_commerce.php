<?php
$etm = \Drupal::entityTypeManager();

echo "=== STORES ===\n";
$stores = $etm->getStorage('commerce_store')->loadMultiple();
foreach ($stores as $s) echo "  id={$s->id()} name={$s->getName()} currency={$s->getDefaultCurrencyCode()}\n";

echo "\n=== PRODUCT TYPES ===\n";
$pts = $etm->getStorage('commerce_product_type')->loadMultiple();
foreach ($pts as $pt) echo "  machine={$pt->id()} label={$pt->label()}\n";

echo "\n=== VARIATION TYPES ===\n";
$vts = $etm->getStorage('commerce_product_variation_type')->loadMultiple();
foreach ($vts as $vt) echo "  machine={$vt->id()} label={$vt->label()}\n";

echo "\n=== PRODUCTS ===\n";
$prods = $etm->getStorage('commerce_product')->loadMultiple();
if (empty($prods)) echo "  (ninguno)\n";
foreach ($prods as $p) {
  echo "  id={$p->id()} title={$p->getTitle()} type={$p->bundle()}\n";
  $vars = $p->getVariations();
  foreach ($vars as $v) {
    $price = $v->getPrice() ? $v->getPrice()->getNumber() : 'NULL';
    echo "    var_id={$v->id()} sku={$v->getSku()} price={$price} title={$v->getTitle()}\n";
  }
}

echo "\n=== FORM DISPLAYS (commerce_product) ===\n";
$fds = $etm->getStorage('entity_form_display')->getQuery()->accessCheck(FALSE)->execute();
foreach ($fds as $fid) if (str_contains($fid, 'commerce_product')) echo "  $fid\n";

echo "\n=== VIEW DISPLAYS (commerce_product) ===\n";
$vds = $etm->getStorage('entity_view_display')->getQuery()->accessCheck(FALSE)->execute();
foreach ($vds as $vid) if (str_contains($vid, 'commerce_product')) echo "  $vid\n";

echo "\n=== COMMERCE PRICE FIELDS ON PET_PRODUCT VARIATION ===\n";
$priceFields = $etm->getStorage('field_config')->loadByProperties(['field_name' => 'price', 'entity_type' => 'commerce_product_variation', 'bundle' => 'pet_product']);
foreach ($priceFields as $f) echo "  field={$f->getFieldName()} type={$f->getType()}\n";
