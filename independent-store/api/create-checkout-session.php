<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed.'], 405);
if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) json_response(['error' => 'JSON required.'], 415);
$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? null;
if (!is_array($items) || count($items) < 1 || count($items) > 40) json_response(['error' => 'Your bag is empty or invalid.'], 422);
try {
    $pdo = db();
    $normalized = [];
    $lineItems = [];
    $subtotal = 0;
    foreach ($items as $item) {
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !$qty || $qty < 1 || $qty > 20) throw new InvalidArgumentException('Invalid item quantity.');
        $stmt = $pdo->prepare("SELECT id, title, price, currency, sku, inventory_quantity FROM products WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) throw new InvalidArgumentException('A product in your bag is no longer available.');
        if ($product['inventory_quantity'] !== null && (int)$product['inventory_quantity'] < $qty) throw new InvalidArgumentException('Not enough inventory for ' . $product['title'] . '.');
        $cents = (int) round((float)$product['price'] * 100);
        if ($cents < 50) throw new InvalidArgumentException('A product price is invalid.');
        $currency = strtolower($product['currency'] ?: 'USD');
        $lineItems[] = [
            'price_data' => ['currency' => $currency, 'unit_amount' => $cents, 'product_data' => ['name' => $product['title']]],
            'quantity' => $qty,
        ];
        $normalized[] = ['product_id' => (int)$product['id'], 'title' => $product['title'], 'sku' => $product['sku'], 'quantity' => $qty, 'unit_price' => $product['price']];
        $subtotal += $cents * $qty;
    }
    $orderRef = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO orders (order_ref, status, currency, subtotal_cents, items_json, created_at) VALUES (?, 'pending_payment', 'USD', ?, ?, NOW())");
    $stmt->execute([$orderRef, $subtotal, json_encode($normalized)]);
    $base = app_config()['app_base_url'];
    if ($base === '') throw new RuntimeException('APP_BASE_URL is not configured.');
    $payload = [
        'mode' => 'payment',
        'success_url' => $base . '/order-success.php?ref=' . $orderRef . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $base . '/?checkout=cancelled',
        'client_reference_id' => $orderRef,
        'line_items' => $lineItems,
        'billing_address_collection' => 'required',
        'shipping_address_collection[allowed_countries][0]' => 'US',
        'shipping_address_collection[allowed_countries][1]' => 'CA',
        'phone_number_collection[enabled]' => 'true',
        'metadata[order_ref]' => $orderRef,
    ];
    foreach ($lineItems as $i => $line) {
        $payload["line_items[$i][price_data][currency]"] = $line['price_data']['currency'];
        $payload["line_items[$i][price_data][unit_amount]"] = $line['price_data']['unit_amount'];
        $payload["line_items[$i][price_data][product_data][name]"] = $line['price_data']['product_data']['name'];
        $payload["line_items[$i][quantity]"] = $line['quantity'];
    }
    unset($payload['line_items']);
    $session = stripe_request('POST', 'checkout/sessions', $payload);
    $stmt = $pdo->prepare('UPDATE orders SET stripe_session_id = ? WHERE order_ref = ?');
    $stmt->execute([$session['id'], $orderRef]);
    json_response(['url' => $session['url']]);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Checkout creation: ' . $e->getMessage());
    json_response(['error' => 'Secure checkout is temporarily unavailable. Please try again.'], 503);
}