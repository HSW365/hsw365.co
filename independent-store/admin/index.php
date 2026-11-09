<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/bootstrap.php';
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Strict','path'=>'/']);
session_start();
$adminPassword=(string)getenv('ADMIN_PASSWORD');
if($adminPassword===''){http_response_code(503);exit('Admin is not configured. Set ADMIN_PASSWORD in hosting secrets.');}
if(isset($_POST['login'])) {
 if(hash_equals($adminPassword,(string)($_POST['password']??''))){session_regenerate_id(true);$_SESSION['hsw_admin']=true;$_SESSION['csrf']=bin2hex(random_bytes(32));}
 else {$error='Password not accepted.';}
}
if(isset($_POST['logout'])){session_destroy();header('Location: /admin/');exit;}
$loggedIn=($_SESSION['hsw_admin']??false)===true;
if($loggedIn && isset($_POST['save_product'])) {
 if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Security token expired. Reload and try again.');}
 $pdo=db();$id=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT);
 if($id){
  $status=($_POST['status']??'draft')==='active'?'active':'draft';
  $category=in_array($_POST['category']??'', ['merch','music','acts','apps','books','digital'],true)?$_POST['category']:'merch';
  $s=$pdo->prepare('UPDATE products SET status=?,category=? WHERE id=?');$s->execute([$status,$category,$id]);
  foreach(($_POST['variants']??[]) as $variantId=>$v){
   $vid=filter_var($variantId,FILTER_VALIDATE_INT);if(!$vid)continue;
   $vs=($v['status']??'draft')==='active'?'active':'draft';
   $provider=in_array($v['provider']??'', ['unassigned','printful','cj','manual','digital'],true)?$v['provider']:'unassigned';
   $mapping=trim((string)($v['provider_variant_id']??''));$mapping=$mapping===''?null:substr($mapping,0,255);
   $u=$pdo->prepare('UPDATE product_variants SET status=?,provider=?,provider_variant_id=? WHERE id=? AND product_id=?');
   $u->execute([$vs,$provider,$mapping,$vid,$id]);
  }
 }
 header('Location: /admin/');exit;
}
if($loggedIn && isset($_POST['save_fulfillment'])) {
 if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Security token expired. Reload and try again.');}
 $jobId=filter_var($_POST['job_id']??null,FILTER_VALIDATE_INT);
 if($jobId){
  $provider=in_array($_POST['provider']??'', ['unassigned','printful','cj','manual','digital'],true)?$_POST['provider']:'unassigned';
  $status=preg_replace('/[^a-z_]/','',strtolower((string)($_POST['job_status']??'needs_mapping')));
  $track=substr(trim((string)($_POST['tracking_number']??'')),0,255);
  $url=trim((string)($_POST['tracking_url']??''));if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))$url='';
  $u=db()->prepare('UPDATE fulfillment_jobs SET provider=?,status=?,tracking_number=?,tracking_url=? WHERE id=?');
  $u->execute([$provider,$status,$track?:null,$url?:null,$jobId]);
 }
 header('Location: /admin/#fulfillment');exit;
}
$products=[];$orders=[];$jobs=[];$byProduct=[];
if($loggedIn){
 $products=db()->query("SELECT p.id,p.title,p.category,p.status,p.image_url,COUNT(v.id) variant_count FROM products p LEFT JOIN product_variants v ON v.product_id=p.id GROUP BY p.id ORDER BY p.title")->fetchAll();
 $orders=db()->query('SELECT order_ref,status,currency,subtotal_cents,created_at,paid_at FROM orders ORDER BY created_at DESC LIMIT 50')->fetchAll();
 $variantRows=db()->query('SELECT id,product_id,title,sku,price,billing_interval,inventory_quantity,status,provider,provider_variant_id FROM product_variants ORDER BY product_id,id')->fetchAll();
 foreach($variantRows as $v)$byProduct[$v['product_id']][]=$v;
 $jobs=db()->query('SELECT id,order_ref,provider,status,tracking_number,tracking_url,created_at FROM fulfillment_jobs ORDER BY created_at DESC LIMIT 100')->fetchAll();
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HSW365 Store Admin</title><link rel="stylesheet" href="/assets/store.css"><style>body{padding:30px 5vw}.admin-wrap{max-width:1150px;margin:auto}.admin-row{background:#fff;border:1px solid #dfe8e1;padding:18px;margin:12px 0;border-radius:5px}.admin-row form{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.admin-row img{width:55px;height:65px;object-fit:cover}.admin-row select,.admin-row input{padding:8px;border:1px solid #ccd8cf;max-width:100%}.admin-row button,.login button{background:#18251f;color:white;border:0;padding:10px 16px;cursor:pointer}.variant-line{display:grid;grid-template-columns:minmax(180px,1fr) 125px 125px minmax(140px,200px);gap:10px;align-items:center;margin:10px 0 0 68px;font-size:12px}.muted{color:#68756d;font-size:12px}.login input{padding:12px;border:1px solid #ccd8cf}.admin-nav{display:flex;gap:20px;margin:20px 0}.admin-nav a{text-decoration:underline;font-size:13px}@media(max-width:750px){.variant-line{margin-left:0;grid-template-columns:1fr 1fr}.variant-line span{grid-column:1/-1}}</style></head><body><div class="admin-wrap"><a class="brand" href="/"><span>HSW</span><b>365</b></a><?php if(!$loggedIn): ?><h1>Store admin</h1><form class="login" method="post"><p><?=htmlspecialchars($error??'',ENT_QUOTES)?></p><input type="password" name="password" placeholder="Admin password" required><button name="login" value="1">Sign in</button></form><?php else: ?><div style="display:flex;justify-content:space-between;align-items:center"><h1>Store admin</h1><form method="post"><button name="logout" value="1">Sign out</button></form></div><nav class="admin-nav"><a href="#products">Products</a><a href="#orders">Orders</a><a href="#fulfillment">Fulfillment & tracking</a></nav><p class="muted">Imported listings remain hidden until reviewed. Activate each product and sellable variant only after verifying price, inventory, images, digital delivery and supplier mapping. Saving a supplier mapping does not submit an order to that supplier.</p><h2 id="products">Product review (<?=count($products)?>)</h2><?php foreach($products as $p): ?><div class="admin-row"><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES)?>"><input type="hidden" name="product_id" value="<?=$p['id']?>"><?php if($p['image_url']): ?><img src="<?=htmlspecialchars($p['image_url'],ENT_QUOTES)?>" alt=""><?php endif; ?><strong style="min-width:220px;flex:1"><?=htmlspecialchars($p['title'],ENT_QUOTES)?></strong><select name="category"><?php foreach(['merch','music','books','digital','apps'] as $c): ?><option value="<?=$c?>" <?=$p['category']===$c?'selected':''?>><?=$c?></option><?php endforeach; ?></select><select name="status"><option value="draft" <?=$p['status']==='draft'?'selected':''?>>Draft</option><option value="active" <?=$p['status']==='active'?'selected':''?>>Active</option></select><span class="muted"><?=$p['variant_count']?> variants</span><button name="save_product" value="1">Save product + variants</button><?php foreach(($byProduct[$p['id']]??[]) as $v): ?><div class="variant-line"><span><?=htmlspecialchars($v['title'],ENT_QUOTES)?> · <?=htmlspecialchars((string)$v['sku'],ENT_QUOTES)?> · $<?=htmlspecialchars((string)$v['price'],ENT_QUOTES)?> · <?=htmlspecialchars($v['billing_interval'],ENT_QUOTES)?> · stock <?=htmlspecialchars((string)($v['inventory_quantity']??'untracked'),ENT_QUOTES)?></span><select name="variants[<?=$v['id']?>][status]"><option value="draft" <?=$v['status']==='draft'?'selected':''?>>Variant draft</option><option value="active" <?=$v['status']==='active'?'selected':''?>>Variant active</option></select><select name="variants[<?=$v['id']?>][provider]"><?php foreach(['unassigned','printful','cj','manual','digital'] as $provider): ?><option value="<?=$provider?>" <?=$v['provider']===$provider?'selected':''?>><?=$provider?></option><?php endforeach; ?></select><input name="variants[<?=$v['id']?>][provider_variant_id]" placeholder="Supplier variant ID" value="<?=htmlspecialchars((string)$v['provider_variant_id'],ENT_QUOTES)?>"></div><?php endforeach; ?></form></div><?php endforeach; ?><h2 id="orders">Recent orders</h2><?php foreach($orders as $o): ?><div class="admin-row"><strong><?=htmlspecialchars($o['order_ref'],ENT_QUOTES)?></strong> · <?=htmlspecialchars($o['status'],ENT_QUOTES)?> · $<?=number_format(((int)$o['subtotal_cents'])/100,2)?> · <?=htmlspecialchars($o['created_at'],ENT_QUOTES)?></div><?php endforeach; ?><h2 id="fulfillment">Fulfillment & tracking</h2><p class="muted">Update tracking after the supplier or carrier confirms shipment. No supplier order is automatically placed by this dashboard.</p><?php foreach($jobs as $job): ?><div class="admin-row"><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES)?>"><input type="hidden" name="job_id" value="<?=$job['id']?>"><strong><?=htmlspecialchars($job['order_ref'],ENT_QUOTES)?></strong><select name="provider"><?php foreach(['unassigned','printful','cj','manual','digital'] as $provider): ?><option value="<?=$provider?>" <?=$job['provider']===$provider?'selected':''?>><?=$provider?></option><?php endforeach; ?></select><select name="job_status"><?php foreach(['needs_mapping','queued','submitted','processing','shipped','delivered','failed','manual_review'] as $status): ?><option value="<?=$status?>" <?=$job['status']===$status?'selected':''?>><?=$status?></option><?php endforeach; ?></select><input name="tracking_number" placeholder="Tracking number" value="<?=htmlspecialchars((string)$job['tracking_number'],ENT_QUOTES)?>"><input name="tracking_url" placeholder="Tracking URL" value="<?=htmlspecialchars((string)$job['tracking_url'],ENT_QUOTES)?>"><button name="save_fulfillment" value="1">Save tracking</button></form></div><?php endforeach; ?><?php endif; ?></div></body></html>