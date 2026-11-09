<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$raw=file_get_contents('php://input');$signature=$_SERVER['HTTP_STRIPE_SIGNATURE']??'';$secret=app_config()['stripe_webhook_secret'];
$timestamp=null;$v1=[];foreach(explode(',',$signature) as $part){$pair=explode('=',trim($part),2);if(count($pair)!==2)continue;if($pair[0]==='t')$timestamp=$pair[1];if($pair[0]==='v1')$v1[]=$pair[1];}
if(!$secret||!$timestamp||abs(time()-(int)$timestamp)>300){http_response_code(400);exit;}
$expected=hash_hmac('sha256',$timestamp.'.'.$raw,$secret);$valid=false;foreach($v1 as $candidate)if(hash_equals($expected,$candidate))$valid=true;
if(!$valid){http_response_code(400);exit;}
$event=json_decode($raw,true);if(!is_array($event)||empty($event['id'])){http_response_code(400);exit;}
try{
 $pdo=db();$stmt=$pdo->prepare('INSERT IGNORE INTO stripe_webhook_events (event_id,event_type,received_at) VALUES (?,?,NOW())');$stmt->execute([$event['id'],$event['type']??'unknown']);
 if($stmt->rowCount()===0){http_response_code(200);echo 'duplicate';exit;}
 $type=$event['type']??'';$object=$event['data']['object']??[];
 if(in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)&&($object['payment_status']??'')==='paid'){
  $ref=$object['metadata']['order_ref']??$object['client_reference_id']??'';
  if($ref!==''){
   $stmt=$pdo->prepare("UPDATE orders SET status='paid',stripe_payment_intent=?,customer_email=?,shipping_json=?,paid_at=NOW() WHERE order_ref=? AND status='pending_payment'");
   $stmt->execute([$object['payment_intent']??null,$object['customer_details']['email']??null,json_encode($object['shipping_details']??[]),$ref]);
   $stmt=$pdo->prepare("INSERT IGNORE INTO fulfillment_jobs (order_ref,provider,status,created_at) VALUES (?,'unassigned','needs_mapping',NOW())");$stmt->execute([$ref]);
   if(!empty($object['subscription'])){
    $stmt=$pdo->prepare("INSERT INTO subscriptions (stripe_subscription_id,order_ref,customer_email,status,created_at) VALUES (?,?,?,'active',NOW()) ON DUPLICATE KEY UPDATE customer_email=VALUES(customer_email),status='active'");
    $stmt->execute([$object['subscription'],$ref,$object['customer_details']['email']??null]);
   }
  }
 }
 if($type==='invoice.paid'&&!empty($object['subscription'])){
  $period=$object['lines']['data'][0]['period']['end']??null;
  if($period){$stmt=$pdo->prepare("UPDATE subscriptions SET status='active',current_period_end=FROM_UNIXTIME(?) WHERE stripe_subscription_id=?");$stmt->execute([$period,$object['subscription']]);}
 }
 if(in_array($type,['customer.subscription.updated','customer.subscription.deleted'],true)&&!empty($object['id'])){
  $period=$object['current_period_end']??null;
  $stmt=$pdo->prepare('UPDATE subscriptions SET status=?,current_period_end=IF(? IS NULL,current_period_end,FROM_UNIXTIME(?)) WHERE stripe_subscription_id=?');
  $stmt->execute([$object['status']??'canceled',$period,$period,$object['id']]);
 }
 http_response_code(200);echo 'ok';
}catch(Throwable $e){error_log('Stripe webhook: '.$e->getMessage());http_response_code(500);}
