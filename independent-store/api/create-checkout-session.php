<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed.'],405);
if (!str_contains($_SERVER['CONTENT_TYPE']??'','application/json')) json_response(['error'=>'JSON required.'],415);
$input=json_decode(file_get_contents('php://input'),true);$items=$input['items']??null;
if(!is_array($items)||count($items)<1||count($items)>40)json_response(['error'=>'Your bag is empty or invalid.'],422);
try {
 $pdo=db();$normalized=[];$lineItems=[];$subtotal=0;$isSubscription=false;$hasPhysical=false;$hasNonApp=false;
 foreach($items as $item){
  $variantId=filter_var($item['variant_id']??null,FILTER_VALIDATE_INT);$qty=filter_var($item['quantity']??null,FILTER_VALIDATE_INT);
  if(!$variantId||!$qty||$qty<1||$qty>20)throw new InvalidArgumentException('Invalid item quantity.');
  $stmt=$pdo->prepare("SELECT v.id,v.title AS variant_title,v.sku,v.price,v.billing_interval,v.inventory_quantity,v.provider,v.provider_variant_id,p.title,p.category,p.currency FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? AND v.status='active' AND p.status='active' LIMIT 1");
  $stmt->execute([$variantId]);$v=$stmt->fetch();
  if(!$v)throw new InvalidArgumentException('A product option in your bag is no longer available.');
  $subscription=$v['billing_interval']!=='one_time';$isSubscription=$isSubscription||$subscription;
  if(!in_array($v['category'],['digital','music','apps'],true))$hasPhysical=true;
  if($v['category']!=='apps')$hasNonApp=true;
  $untracked=in_array($v['category'],['music','digital','apps'],true);
  if(!$untracked&&$v['inventory_quantity']!==null&&(int)$v['inventory_quantity']<$qty)throw new InvalidArgumentException('Not enough inventory for '.$v['title'].'.');
  $cents=(int)round((float)$v['price']*100);if($cents<50)throw new InvalidArgumentException('A product price is invalid.');
  $name=$v['title'].(($v['variant_title']!=='Default Title')?' — '.$v['variant_title']:'');
  $lineItems[]=['currency'=>strtolower($v['currency']?:'USD'),'unit_amount'=>$cents,'name'=>$name,'interval'=>$v['billing_interval'],'quantity'=>$qty];
  $normalized[]=['variant_id'=>$variantId,'title'=>$v['title'],'variant_title'=>$v['variant_title'],'sku'=>$v['sku'],'provider'=>$v['provider'],'provider_variant_id'=>$v['provider_variant_id'],'billing_interval'=>$v['billing_interval'],'quantity'=>$qty,'unit_price'=>$v['price']];
  $subtotal+=$cents*$qty;
 }
 if($isSubscription&&$hasNonApp)throw new InvalidArgumentException('App memberships must be checked out separately from merch, books and music.');
 $mode=$isSubscription?'subscription':'payment';$config=app_config();
 if($hasPhysical&&empty($config['stripe_shipping_rate_id']))throw new InvalidArgumentException('Physical-product shipping is not configured yet. HSW365 needs its verified Stripe shipping rate before this item can be purchased.');
 $orderRef=bin2hex(random_bytes(16));
 $stmt=$pdo->prepare("INSERT INTO orders (order_ref,status,currency,subtotal_cents,items_json,created_at) VALUES (?, 'pending_payment','USD',?,?,NOW())");
 $stmt->execute([$orderRef,$subtotal,json_encode($normalized)]);
 $base=$config['app_base_url'];if($base==='')throw new RuntimeException('APP_BASE_URL is not configured.');
 $payload=['mode'=>$mode,'success_url'=>$base.'/order-success.php?ref='.$orderRef.'&session_id={CHECKOUT_SESSION_ID}','cancel_url'=>$base.'/?checkout=cancelled','client_reference_id'=>$orderRef,'billing_address_collection'=>'required','phone_number_collection[enabled]'=>'true','metadata[order_ref]'=>$orderRef];
 foreach($lineItems as $i=>$line){
  $payload["line_items[$i][price_data][currency]"]=$line['currency'];
  $payload["line_items[$i][price_data][unit_amount]"]=$line['unit_amount'];
  $payload["line_items[$i][price_data][product_data][name]"]=$line['name'];
  $payload["line_items[$i][quantity]"]=$line['quantity'];
  if($line['interval']!=='one_time')$payload["line_items[$i][price_data][recurring][interval]"]=$line['interval'];
 }
 if($hasPhysical){$payload['shipping_address_collection[allowed_countries][0]']='US';$payload['shipping_address_collection[allowed_countries][1]']='CA';$payload['shipping_options[0][shipping_rate]']=$config['stripe_shipping_rate_id'];}
 if(!empty($config['stripe_automatic_tax']))$payload['automatic_tax[enabled]']='true';
 if($isSubscription)$payload['subscription_data[metadata][order_ref]']=$orderRef;
 $session=stripe_request('POST','checkout/sessions',$payload);
 $stmt=$pdo->prepare('UPDATE orders SET stripe_session_id=? WHERE order_ref=?');$stmt->execute([$session['id'],$orderRef]);
 json_response(['url'=>$session['url']]);
} catch(InvalidArgumentException $e){json_response(['error'=>$e->getMessage()],422);}
catch(Throwable $e){error_log('Checkout creation: '.$e->getMessage());json_response(['error'=>'Secure checkout is temporarily unavailable. Please try again.'],503);}
