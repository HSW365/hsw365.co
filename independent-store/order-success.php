<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
$ref=preg_replace('/[^a-f0-9]/','',strtolower((string)($_GET['ref']??'')));
$sessionId=(string)($_GET['session_id']??'');
$paid=false;$order=null;
if(strlen($ref)===32 && str_starts_with($sessionId,'cs_')) {
 try {
  $pdo=db();$stmt=$pdo->prepare('SELECT * FROM orders WHERE order_ref=? AND stripe_session_id=? LIMIT 1');$stmt->execute([$ref,$sessionId]);$order=$stmt->fetch();
  if($order) {
   $session=stripe_request('GET','checkout/sessions/'.rawurlencode($sessionId));
   if(($session['client_reference_id']??'')===$ref && ($session['payment_status']??'')==='paid') {
    $stmt=$pdo->prepare("UPDATE orders SET status='paid',stripe_payment_intent=?,customer_email=?,shipping_json=?,paid_at=COALESCE(paid_at,NOW()) WHERE order_ref=?");
    $stmt->execute([$session['payment_intent']??null,$session['customer_details']['email']??null,json_encode($session['shipping_details']??[]),$ref]);
    queue_fulfillment_jobs($pdo,$ref);
    $paid=true;
   } else {$paid=($order['status']==='paid');}
  }
 } catch(Throwable $e){error_log('Order confirmation: '.$e->getMessage());}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HSW365 Order</title><link rel="stylesheet" href="/assets/store.css"></head><body><main style="max-width:680px;margin:12vh auto;padding:32px"><a class="brand" href="/"><span>HSW</span><b>365</b></a><?php if($paid): ?><p class="eyebrow" style="margin-top:55px">ORDER CONFIRMED</p><h1 style="font-size:48px">You're in. Thank you.</h1><p>Your payment was confirmed. Your order reference is <strong><?=htmlspecialchars($ref,ENT_QUOTES)?></strong>. <a href="/track.php?ref=<?=htmlspecialchars($ref,ENT_QUOTES)?>">Track your order here</a>. Shipping updates appear when your order is processed.</p><?php elseif($order): ?><p class="eyebrow" style="margin-top:55px">PAYMENT PROCESSING</p><h1 style="font-size:42px">We're confirming your payment.</h1><p>Your order reference is <strong><?=htmlspecialchars($ref,ENT_QUOTES)?></strong>. Refresh this page in a moment. If payment was not completed, no order will be sent for fulfillment.</p><?php else: ?><p class="eyebrow" style="margin-top:55px">ORDER STATUS</p><h1 style="font-size:42px">We couldn't verify this order.</h1><p>Check your email for a Stripe receipt or contact HSW365 support before placing another order.</p><?php endif; ?><a class="primary-link" href="/">Return to HSW365 <span>↗</span></a></main></body></html>