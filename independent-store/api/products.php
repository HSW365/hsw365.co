<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
try {
    $pdo = db();
    $products = $pdo->query("SELECT id, handle, title, description, category, currency, image_url, image_alt FROM products WHERE status = 'active' ORDER BY sort_order ASC, title ASC")->fetchAll();
    $variantStmt = $pdo->prepare("SELECT id, title, sku, price, inventory_quantity, options_json FROM product_variants WHERE product_id = ? AND status = 'active' ORDER BY id ASC");
    foreach ($products as &$product) {
        $variantStmt->execute([$product['id']]);
        $product['variants'] = array_map(static function(array $v): array {
            $v['id'] = (int)$v['id'];
            $v['price'] = (float)$v['price'];
            $v['inventory_quantity'] = $v['inventory_quantity'] === null ? null : (int)$v['inventory_quantity'];
            $v['options'] = $v['options_json'] ? json_decode($v['options_json'], true) : [];
            unset($v['options_json']);
            return $v;
        }, $variantStmt->fetchAll());
    }
    unset($product);
    json_response(['products' => $products]);
} catch (Throwable $e) {
    error_log('Catalog API: ' . $e->getMessage());
    json_response(['error' => 'Catalog temporarily unavailable.'], 503);
}