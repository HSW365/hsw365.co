<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
if (PHP_SAPI !== 'cli' || empty($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/import-shopify-json.php data/shopify-products-export.json\n");
    exit(2);
}
$data=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if (($data['store']??'')!=='hsw365.co' || !is_array($data['products']??null)) throw new RuntimeException('Unexpected product export file.');
$pdo=db();
$productSql=$pdo->prepare("INSERT INTO products (shopify_product_id,handle,title,category,currency,image_url,image_alt,status) VALUES (?,?,?,?, 'USD',?,?, 'draft') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),title=VALUES(title),category=VALUES(category),image_url=VALUES(image_url),image_alt=VALUES(image_alt)");
$variantSql=$pdo->prepare("INSERT INTO product_variants (product_id,shopify_variant_id,title,sku,price,billing_interval,inventory_quantity,options_json,provider,status) VALUES (?,?,?,?,?,?,?,?,?, 'draft') ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),title=VALUES(title),sku=VALUES(sku),price=VALUES(price),billing_interval=VALUES(billing_interval),inventory_quantity=VALUES(inventory_quantity),options_json=VALUES(options_json)");
$products=0;$variants=0;
$pdo->beginTransaction();
try {
 foreach($data['products'] as $p) {
  $productSql->execute([$p['shopify_product_id'],$p['handle'],$p['title'],$p['category'],$p['image_url']??null,$p['image_alt']??null]);
  $productId=(int)$pdo->lastInsertId();
  if($productId<1){$s=$pdo->prepare('SELECT id FROM products WHERE handle=?');$s->execute([$p['handle']]);$productId=(int)$s->fetchColumn();}
  foreach(($p['variants']??[]) as $v) {
   $category=$p['category']??'merch';
   $provider=in_array($category,['music','digital'],true)?'digital':'unassigned';
   $interval=$category==='apps'?'month':'one_time';\n   $interval=$category==='apps'?'month':'one_time';
   $inventory=$v['inventory_quantity']??null;
   $variantSql->execute([$productId,$v['shopify_variant_id'],$v['title'],$v['sku']??null,$v['price'],$interval,$inventory,json_encode($v['options']??[]),$provider]);
   $variants++;
  }
  $products++;
 }
 $pdo->commit();
} catch(Throwable $e) {$pdo->rollBack();throw $e;}
fwrite(STDOUT,"Imported {$products} products and {$variants} variants as DRAFT. Nothing was published or activated. Review product category, stock, prices, photos and fulfillment mapping in the admin area before activating listings.\n");
