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
if($loggedIn && isset($_POST['save'])) {
 if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Security token expired. Reload and try again.');}
 $pdo=db();$id=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT);
 if($id){
  $status=($_POST['status']??'draft')==='active'?'active':'draft';
  $category=in_array($_POST['category']??'', ['merch','music','books','digital'],true)?$_POST['category']:'merch';
  $s=$pdo->prepare('UPDATE products SET status=?,category=? WHERE id=?');$s->execute([$status,$category,$id]);
  foreach(($_POST['variants']??[]) as $variantId=>$variantStatus){
   $vid=filter_var($variantId,FILTER_VALIDATE_INT);
   if($vid){$vs=$variantStatus==='active'?'active':'draft';$u=$pdo->prepare('UPDATE product_variants SET status=? WHERE id=? AND product_id=?');$u->execute([$vs,$vid,$id]);}
  }
 }
 header('Location: /admin/');exit;
}
$products=[];$orders=[];$variantRows=[];
if($loggedIn){
 $products=db()->query("SELECT p.id,p.title,p.category,p.status,p.image_url,COUNT(v.id) variant_count FROM products p LEFT JOIN product_variants v ON v.product_id=p.id GROUP BY p.id ORDER BY p.title")->fetchAll();
 $orders=db()->query('SELECT order_ref,status,currency,subtotal_cents,created_at,paid_at FROM orders ORDER BY created_at DESC LIMIT 50')->fetchAll();
 $variantRows=db()->query('SELECT id,product_id,title,sku,price,inventory_quantity,status FROM product_variants ORDER BY product_id,id')->fetchAll();
 $byProduct=[];foreach($variantRows as $v)$byProduct[$v['product_id']][]=$v;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HSW365 Store Admin</title><link rel="stylesheet" href="/assets/store.css"><style>body{padding:30px 5vw}.admin-wrap{max-width:1100px;margin:auto}.admin-row{background:#fff;border:1px solid #dfe8e1;padding:18px;margin:12px 0;border-radius:5px}.admin-row form{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.admin-row img{width:55px;height:65px;object-fit:cover}.admin-row select,.admin-row input{padding:8px;border:1px solid #ccd8cf}.admin-row button,.login button{background:#18251f;color:white;border:0;padding:10px 16px;cursor:pointer}.variant-line{display:grid;grid-template-columns:1fr 120px;gap:12px;align-items:center;margin:8px 0 0 68px;font-size:12px}.muted{color:#68756d;font-size:12px}.login input{padding:12px;border:1px solid #ccd8cf}</style></head><body><div class="admin-wrap"><a class="brand" href="/"><span>HSW</span><b>365</b></a><?php if(!$loggedIn): ?><h1>Store admin</h1><form class="login" method="post"><p><?=htmlspecialchars($error??'',ENT_QUOTES)?></p><input type="password" name="password" placeholder="Admin password" required><button name="login" value="1">Sign in</button></form><?php else: ?><div style="display:flex;justify-content:space-between;align-items:center"><h1>Store admin</h1><form method="post"><button name="logout" value="1">Sign out</button></form></div><p class="muted">Imported listings remain hidden until reviewed. Activate each product and its sellable variants only after verifying price, inventory, image rights, delivery method and supplier mapping.</p><h2>Product review (<?=count($products)?>)</h2><?php foreach($products as $p): ?><div class="admin-row"><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES)?>"><input type="hidden" name="product_id" value="<?=$p['id']?>"><?php if($p['image_url']): ?><img src="<?=htmlspecialchars($p['image_url'],ENT_QUOTES)?>" alt=""><?php endif; ?><strong style="min-width:220px;flex:1"><?=htmlspecialchars($p['title'],ENT_QUOTES)?></strong><select name="category"><?php foreach(['merch','music','books','digital'] as $c): ?><option value="<?=$c?>" <?=$p['category']===$c?'selected':''?>><?=$c?></option><?php endforeach; ?></select><select name="status"><option value="draft" <?=$p['status']==='draft'?'selected':''?>>Draft</option><option value="active" <?=$p['status']==='active'?'selected':''?>>Active</option></select><span class="muted"><?=$p['variant_count']?> variants</span><button name="save" value="1">Save product + variants</button><?php foreach(($byProduct[$p['id']]??[]) as $v): ?><div class="variant-line"><span><?=htmlspecialchars($v['title'],ENT_QUOTES)?> · <?=htmlspecialchars((string)$v['sku'],ENT_QUOTES)?> · $<?=htmlspecialchars((string)$v['price'],ENT_QUOTES)?> · stock <?=htmlspecialchars((string)($v['inventory_quantity']??'untracked'),ENT_QUOTES)?></span><select name="variants[<?=$v['id']?>]"><option value="draft" <?=$v['status']==='draft'?'selected':''?>>Variant draft</option><option value="active" <?=$v['status']==='active'?'selected':''?>>Variant active</option></select></div><?php endforeach; ?></form></div><?php endforeach; ?><h2>Recent orders</h2><?php foreach($orders as $o): ?><div class="admin-row"><strong><?=htmlspecialchars($o['order_ref'],ENT_QUOTES)?></strong> · <?=htmlspecialchars($o['status'],ENT_QUOTES)?> · $<?=number_format(((int)$o['subtotal_cents'])/100,2)?> · <?=htmlspecialchars($o['created_at'],ENT_QUOTES)?></div><?php endforeach; ?><?php endif; ?></div></body></html>