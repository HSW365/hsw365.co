<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = app_config()['stripe_webhook_secret'];
$timestamp = null; $v1 = [];
foreach (explode(',', $signature) as $part) {
    $pair = explode('=', trim($part), 2);
    if (count($pair) !== 2) continue;
    if ($pair[0] === 't') $timestamp = $pair[1];
    if ($pair[0] === 'v1') $v1[] = $pair[1];
}
if (!$secret || !$timestamp || abs(time() - (int)$timestamp) > 300) { http_response_code(400); exit; }
$expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
$valid = false;
foreach ($v1 as $candidate) if (hash_equals($expected, $candidate)) $valid = true;
if (!$valid) { http_response_code(400); exit; }
$event = json_decode($raw, true);
if (!is_array($event) || empty($event['id'])) { http_response_code(400); exit; }
try {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT IGNORE INTO stripe_webhook_events (event_id, event_type, received_at) VALUES (?, ?, NOW())');
    $stmt->execute([$event['id'], $event['type'] ?? 'unknown']);
    if ($stmt->rowCount() === 0) { http_response_code(200); echo 'duplicate'; exit; }
    if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
        $session = $event['data']['object'] ?? [];
        if (($session['payment_status'] ?? '') === 'paid') {
            $ref = $session['metadata']['order_ref'] ?? $session['client_reference_id'] ?? '';
            if ($ref !== '') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', stripe_payment_intent = ?, customer_email = ?, shipping_json = ?, paid_at = NOW() WHERE order_ref = ? AND status = 'pending_payment'");
                $stmt->execute([$session['payment_intent'] ?? null, $session['customer_details']['email'] ?? null, json_encode($session['shipping_details'] ?? []), $ref]);
                $stmt = $pdo->prepare("INSERT IGNORE INTO fulfillment_jobs (order_ref, provider, status, created_at) VALUES (?, 'unassigned', 'needs_mapping', NOW())");
                $stmt->execute([$ref]);
            }
        }
    }
    http_response_code(200); echo 'ok';
} catch (Throwable $e) {
    error_log('Stripe webhook: ' . $e->getMessage());
    http_response_code(500);
}