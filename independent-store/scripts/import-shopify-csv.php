<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
if (PHP_SAPI !== 'cli' || empty($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/import-shopify-csv.php /path/to/products_export.csv\n");
    exit(2);
}
$handle = fopen($argv[1], 'rb');
$headers = fgetcsv($handle);
if (!$headers) throw new RuntimeException('CSV header row is missing.');
$headers = array_map(static fn($v) => strtolower(trim((string)$v)), $headers);
$index = array_flip($headers);
$required = ['handle','title','variant price'];
foreach ($required as $field) if (!isset($index[$field])) throw new RuntimeException("Missing Shopify CSV column: $field");
$pdo = db();
$sql = "INSERT INTO products (shopify_product_id, handle, title, description, category, price, currency, image_url, sku, inventory_quantity, status)
VALUES (:shopify_id,:handle,:title,:description,:category,:price,'USD',:image_url,:sku,:inventory,'draft')
ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), price=VALUES(price), image_url=COALESCE(VALUES(image_url),image_url), sku=COALESCE(VALUES(sku),sku), inventory_quantity=VALUES(inventory_quantity)";
$stmt = $pdo->prepare($sql);
$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    $r = [];
    foreach ($index as $key => $i) $r[$key] = $row[$i] ?? '';
    if (trim($r['handle'] ?? '') === '' || trim($r['title'] ?? '') === '') continue;
    $image = trim($r['image src'] ?? '');
    $inventory = is_numeric($r['variant inventory qty'] ?? '') ? (int)$r['variant inventory qty'] : null;
    $category = strtolower(trim($r['type'] ?? 'merch'));
    if (!in_array($category, ['merch','music','books','digital'], true)) $category = 'merch';
    $stmt->execute([
        ':shopify_id' => $r['id'] ?? null,
        ':handle' => $r['handle'],
        ':title' => $r['title'],
        ':description' => strip_tags($r['body (html)'] ?? ''),
        ':category' => $category,
        ':price' => is_numeric($r['variant price'] ?? '') ? $r['variant price'] : '0.00',
        ':image_url' => $image ?: null,
        ':sku' => ($r['variant sku'] ?? '') ?: null,
        ':inventory' => $inventory,
    ]);
    $count++;
}
fclose($handle);
fwrite(STDOUT, "Imported/updated {$count} product rows as DRAFT. Review variants, images, category and stock; activate only verified listings.\n");
