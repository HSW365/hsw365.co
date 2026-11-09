<?php
declare(strict_types=1);

function app_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config.php';
    }
    return $config;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = app_config()['db'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset']);
    $pdo = new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function stripe_request(string $method, string $path, array $payload = []): array {
    $key = app_config()['stripe_secret_key'];
    if ($key === '') throw new RuntimeException('Payments are not configured yet.');
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/'));
    $headers = ['Authorization: Bearer ' . $key];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 25];
    if ($payload) {
        $options[CURLOPT_POSTFIELDS] = http_build_query($payload);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) { $error = curl_error($ch); curl_close($ch); throw new RuntimeException('Payment provider connection failed: ' . $error); }
    curl_close($ch);
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException($data['error']['message'] ?? 'Payment provider request failed.');
    }
    return $data;
}
function queue_fulfillment_jobs(PDO $pdo, string $orderRef): void {
    $stmt=$pdo->prepare('SELECT items_json FROM orders WHERE order_ref=? LIMIT 1');
    $stmt->execute([$orderRef]);
    $items=json_decode((string)$stmt->fetchColumn(),true);
    $providers=[];
    foreach((is_array($items)?$items:[]) as $item){
        $provider=$item['provider']??'unassigned';
        if(!in_array($provider,['unassigned','printful','cj','manual','digital'],true))$provider='unassigned';
        $providers[$provider]=true;
    }
    if(!$providers)$providers=['unassigned'=>true];
    $insert=$pdo->prepare("INSERT IGNORE INTO fulfillment_jobs (order_ref,provider,status,created_at) VALUES (?,?, 'needs_mapping', NOW())");
    foreach(array_keys($providers) as $provider)$insert->execute([$orderRef,$provider]);
}
