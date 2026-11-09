<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
try {
    $stmt = db()->query("SELECT id, handle, title, description, category, price, currency, image_url, sku, inventory_quantity FROM products WHERE status = 'active' AND (inventory_quantity IS NULL OR inventory_quantity > 0) ORDER BY sort_order ASC, title ASC");
    json_response(['products' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Catalog API: ' . $e->getMessage());
    json_response(['error' => 'Catalog temporarily unavailable.'], 503);
}