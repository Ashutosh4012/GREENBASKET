<?php
declare(strict_types=1);

// JSON-file PHP backend compatible with the browser app's existing /api/* routes.
const USER_TTL = 604800000;
const ADMIN_TTL = 43200000;
const STORE_FILE = __DIR__ . '/data/store.json';

function json_out(int $status, mixed $body = null): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if ($body !== null) echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function body_json(): array { $v = json_decode(file_get_contents('php://input') ?: '{}', true); return is_array($v) ? $v : []; }
function load_store(): array {
    $fp = fopen(STORE_FILE, 'c+'); if (!$fp) json_out(500, ['error'=>'Could not open data store']);
    flock($fp, LOCK_EX); $raw = stream_get_contents($fp); $s = json_decode($raw ?: '', true);
    if (!is_array($s)) json_out(500, ['error'=>'Invalid data/store.json']);
    $GLOBALS['store_fp'] = $fp; return $s;
}
function save_store(array $s): void {
    $fp = $GLOBALS['store_fp']; rewind($fp); ftruncate($fp, 0);
    fwrite($fp, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
}
function done(array $s, int $status = 200, mixed $payload = null): never { save_store($s); json_out($status, $payload); }
function fail(int $status, string $message): never { json_out($status, ['error'=>$message]); }
function token(): string { $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ''; return preg_match('/^Bearer\s+(.+)$/i', $h, $m) ? trim($m[1]) : ''; }
function clean_text(mixed $v, int $max=160): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v)) ?? ''), 0, $max); }
function user_public(array $u): array { unset($u['passwordHash'], $u['passwordSalt']); return $u; }
function prune(array &$s): void { $n=(int)(microtime(true)*1000); $s['sessions']=array_values(array_filter($s['sessions']??[],fn($x)=>($x['expiresAt']??0)>$n)); $s['adminSessions']=array_values(array_filter($s['adminSessions']??[],fn($x)=>($x['expiresAt']??0)>$n)); }
function current_user(array $s): ?array { $t=token(); foreach($s['sessions']??[] as $ss) if(($ss['token']??'')===$t) foreach($s['users'] as $u) if(($u['id']??'')===($ss['userId']??'')) return $u; return null; }
function admin_access(array $s): ?array { $t=token(); foreach($s['adminSessions']??[] as $ss) if(($ss['token']??'')===$t) { if(($ss['role']??'')==='store') { foreach($s['catalogStores'] as $st) if((int)$st['id']===(int)$ss['storeId']) return ['role'=>'store','storeId'=>(int)$st['id'],'storeName'=>$st['name']]; return null; } return ['role'=>'super','storeId'=>null,'storeName'=>'']; } return null; }
function access_payload(array $a): array { return ['role'=>$a['role'],'storeId'=>$a['storeId'],'storeName'=>$a['storeName'],'canManageAllStores'=>$a['role']==='super','defaultStorePasswordPattern'=>'Store<StoreId>@QB']; }
function password_hash_php(string $password, string $salt): string { return hash('sha512', hash('sha256', $password . $salt)); }
function summary(array $stores): array { $r=['totalStores'=>count($stores),'totalProducts'=>0,'lowStockProducts'=>0,'outOfStockProducts'=>0,'inventoryValue'=>0]; foreach($stores as $st) foreach($st['menu']??[] as $p) { $r['totalProducts']++; if(($p['stock']??0)<=0)$r['outOfStockProducts']++; if(($p['stock']??0)<=($p['reorderLevel']??0))$r['lowStockProducts']++; $r['inventoryValue']+=($p['stock']??0)*($p['price']??0); } return $r; }
function all_stores(array $s): array { return $s['catalogStores']??[]; }
function scoped_stores(array $stores, array $a): array { return $a['role']==='store'?array_values(array_filter($stores,fn($st)=>(int)$st['id']===(int)$a['storeId'])):$stores; }
function api_main(): never {
    $method=$_SERVER['REQUEST_METHOD']??'GET'; $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'; $b=body_json();
    if($method==='OPTIONS'){http_response_code(204);exit;}
    header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type, Authorization'); header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
    $s=load_store(); prune($s); $now=(int)(microtime(true)*1000); $tok=token();
    if($method==='GET'&&$path==='/api/health') done($s,200,['ok'=>true,'storage'=>'json']);
    if($method==='GET'&&$path==='/api/catalog') { $stores=all_stores($s); done($s,200,['location'=>clean_text($_GET['location']??'All service zones',120),'source'=>'QuickBite Grocery Catalog','fetchedAt'=>$now,'stores'=>$stores,'summary'=>summary($stores)]); }
    if($method==='GET'&&$path==='/api/restaurants') done($s,200,['restaurants'=>[],'source'=>'QuickBite']);
    if($method==='POST'&&$path==='/api/auth/signup') {
        $email=strtolower(trim((string)($b['email']??'')));$phone=preg_replace('/\D/','',(string)($b['phone']??''));$pw=(string)($b['password']??'');$name=clean_text($b['name']??'',80);
        if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($phone)!==10||strlen($pw)<6) fail(400,'Please enter valid signup details');
        foreach($s['users'] as $u) if(strtolower($u['email'])===$email) fail(409,'An account with this email already exists');
        $salt=bin2hex(random_bytes(16));$u=['id'=>bin2hex(random_bytes(16)),'name'=>$name,'email'=>$email,'phone'=>$phone,'address'=>'','favourites'=>[],'passwordSalt'=>$salt,'passwordHash'=>hash('sha512',hash('sha256',$pw.$salt)),'createdAt'=>$now,'updatedAt'=>$now];
        $t=bin2hex(random_bytes(32));$s['users'][]=$u;$s['sessions'][]=['token'=>$t,'userId'=>$u['id'],'createdAt'=>$now,'expiresAt'=>$now+USER_TTL];done($s,201,['token'=>$t,'user'=>user_public($u)]);
    }
    if($method==='POST'&&$path==='/api/auth/login') {
        $email=strtolower(trim((string)($b['email']??'')));$pw=(string)($b['password']??'');foreach($s['users'] as &$u) if(strtolower($u['email'])===$email) { if(hash_equals($u['passwordHash'],password_hash_php($pw,(string)$u['passwordSalt']))) { $t=bin2hex(random_bytes(32));$s['sessions'][]=['token'=>$t,'userId'=>$u['id'],'createdAt'=>$now,'expiresAt'=>$now+USER_TTL];$out=user_public($u);unset($u);done($s,200,['token'=>$t,'user'=>$out]); } } unset($u); fail(401,'Account not found or password is incorrect');
    }
    if($method==='GET'&&$path==='/api/auth/me') { $u=current_user($s);if(!$u)fail(401,'Your session has expired. Please sign in again.');done($s,200,['user'=>user_public($u)]); }
    if($method==='POST'&&$path==='/api/auth/logout') { $s['sessions']=array_values(array_filter($s['sessions'],fn($x)=>$x['token']!==$tok));done($s,204); }
    if($method==='PUT'&&$path==='/api/users/me/profile') { $u=current_user($s);if(!$u)fail(401,'Your session has expired. Please sign in again.');foreach($s['users'] as &$row)if($row['id']===$u['id']){foreach(['name'=>80,'phone'=>20,'address'=>240] as $k=>$max)if(isset($b[$k]))$row[$k]=clean_text($b[$k],$max);if(isset($b['favourites'])&&is_array($b['favourites']))$row['favourites']=array_values(array_unique(array_map('intval',$b['favourites'])));$row['updatedAt']=$now;$out=user_public($row);}unset($row);done($s,200,['user'=>$out]); }
    if($method==='GET'&&$path==='/api/orders/me') { $u=current_user($s);if(!$u)fail(401,'Your session has expired. Please sign in again.');$orders=array_values(array_filter($s['orders'],fn($o)=>($o['userId']??'')===$u['id']));done($s,200,['orders'=>$orders]); }
    if($method==='POST'&&$path==='/api/orders') {
        $u=current_user($s);if(!$u)fail(401,'Your session has expired. Please sign in again.');$items=[];$subtotal=0;
        foreach(($b['items']??[]) as $line){$pid=(int)($line['id']??0);$qty=max(1,(int)($line['qty']??1));$found=false;foreach($s['catalogStores'] as &$st)foreach($st['menu'] as &$p)if((int)$p['id']===$pid){if((int)$p['stock']<$qty)fail(409,$p['name'].' has only '.$p['stock'].' left in stock.');$p['stock']-=$qty;$items[]=['id'=>$p['id'],'restId'=>$st['id'],'name'=>$p['name'],'price'=>$p['price'],'img'=>$p['img'],'qty'=>$qty,'sku'=>$p['sku']??'','unit'=>$p['unit']??'unit','category'=>$p['category']??''];$subtotal+=(int)$p['price']*$qty;$found=true;}unset($p,$st);if(!$found)fail(400,'A product in your cart is no longer available.');}
        if(!$items)fail(400,'Your cart is empty');$customer=$b['customer']??[];$name=clean_text($customer['name']??$u['name'],80);$phone=preg_replace('/\D/','',(string)($customer['phone']??$u['phone']));$address=clean_text($customer['address']??$u['address'],240);if(strlen($phone)!==10||!$address)fail(400,'Please enter a valid phone number and delivery address');
        $sid=(int)$items[0]['restId'];$stName='QuickBite Grocery';foreach($s['catalogStores'] as $st)if((int)$st['id']===$sid)$stName=$st['name'];$order=['orderId'=>'QB'.$now.random_int(100,999),'restId'=>$sid,'restName'=>$stName,'restImg'=>'','customer'=>['name'=>$name,'phone'=>$phone,'address'=>$address,'note'=>clean_text($customer['note']??'',160)],'userId'=>$u['id'],'userEmail'=>$u['email'],'items'=>$items,'subtotal'=>$subtotal,'tax'=>(int)round($subtotal*.05),'delivery'=>$subtotal>=499?0:35,'total'=>(int)round($subtotal*1.05)+($subtotal>=499?0:35),'couponCode'=>'','discount'=>0,'payment'=>clean_text($b['payment']??'Cash on Delivery',40),'status'=>'Pending','date'=>date('j M Y, g:i:s A'),'createdAt'=>$now,'etaMinutes'=>max(10,min(90,(int)($b['etaMinutes']??30))),'stockReverted'=>false];$s['orders']=array_merge([$order],$s['orders']);foreach($s['users'] as &$row)if($row['id']===$u['id']){$row['name']=$name;$row['phone']=$phone;$row['address']=$address;$row['updatedAt']=$now;$public=user_public($row);}unset($row);done($s,201,['order'=>$order,'user'=>$public]);
    }
    if($method==='POST'&&$path==='/api/admin/login') { $type=($b['accessType']??'')==='store'?'store':'super';$role='super';$sid=null;if($type==='store'){ $sid=(int)($b['storeId']??0);$mgr=null;foreach($s['storeManagers'] as $m)if((int)$m['storeId']===$sid)$mgr=$m;$pw=(string)($b['password']??'');$valid=$mgr&&(hash_equals($mgr['passwordHash'],password_hash_php($pw,(string)$mgr['passwordSalt']))||hash_equals('Store'.$sid.'@QB',$pw));if(!$valid)fail(401,'Incorrect store password');$role='store';}elseif((string)($b['password']??'')!==(getenv('QB_ADMIN_PASSWORD')?:'QuickBite@2026'))fail(401,'Incorrect admin password');$t=bin2hex(random_bytes(32));$s['adminSessions'][]=['token'=>$t,'role'=>$role,'storeId'=>$sid,'createdAt'=>$now,'expiresAt'=>$now+ADMIN_TTL];$storeName='';foreach($s['catalogStores'] as $st)if((int)$st['id']===(int)$sid)$storeName=$st['name'];done($s,200,['token'=>$t,'access'=>access_payload(['role'=>$role,'storeId'=>$sid,'storeName'=>$storeName])]); }
    if(str_starts_with($path,'/api/admin/')) {
        $a=admin_access($s); if($path==='/api/admin/logout'&&$method==='POST'){$s['adminSessions']=array_values(array_filter($s['adminSessions'],fn($x)=>$x['token']!==$tok));done($s,204);} if(!$a)fail(401,'Admin session expired. Please unlock the dashboard again.');
        $stores=scoped_stores(all_stores($s),$a);
        if($method==='POST'&&$path==='/api/admin/stores') { if($a['role']!=='super')fail(403,'Store managers cannot create new stores.');$name=clean_text($b['name']??'',120);if(!$name)fail(400,'Store name is required');$id=300;foreach($s['catalogStores'] as $st)$id=max($id,(int)$st['id']);$id++;$pw=clean_text($b['managerPassword']??('Store'.$id.'@QB'),120);$store=['id'=>$id,'name'=>$name,'cuisine'=>clean_text($b['cuisine']??'Daily Grocery Essentials',120),'rating'=>(float)($b['rating']??4.5),'deliveryTime'=>(int)($b['deliveryTime']??30),'minOrder'=>(int)($b['minOrder']??199),'avgCost'=>(int)($b['avgCost']??500),'category'=>clean_text($b['category']??'essentials',40),'promo'=>clean_text($b['promo']??'Fresh grocery picks available daily',90),'tags'=>$b['tags']??['New Store'],'img'=>clean_text($b['img']??'',500),'address'=>clean_text($b['address']??'',180),'menu'=>[]];$s['catalogStores'][]=$store;$salt=bin2hex(random_bytes(16));$s['storeManagers'][]=['storeId'=>$id,'passwordSalt'=>$salt,'passwordHash'=>password_hash_php($pw,$salt),'createdAt'=>$now,'updatedAt'=>$now];done($s,201,['store'=>$store,'managerPassword'=>$pw,'summary'=>summary($s['catalogStores']),'access'=>access_payload($a)]); }
        if($method==='POST'&&$path==='/api/admin/products') { $name=clean_text($b['name']??'',120);$price=(int)($b['price']??0);$targets=[];if($a['role']==='super'&&!empty($b['applyToAll']))$targets=array_keys($s['catalogStores']);else{$target=(int)($a['role']==='store'?$a['storeId']:($b['storeId']??0));foreach($s['catalogStores'] as $i=>$st)if((int)$st['id']===$target)$targets[]=$i;}if(!$name||$price<1||!$targets)fail(400,'Enter valid product details and choose a store');$pid=30000;foreach($s['catalogStores'] as $st)foreach($st['menu']??[] as $p)$pid=max($pid,(int)$p['id']);$created=[];foreach($targets as $i){$pid++;$p=['id'=>$pid,'restId'=>$s['catalogStores'][$i]['id'],'name'=>$name,'desc'=>clean_text($b['desc']??'',220),'price'=>$price,'img'=>clean_text($b['img']??'',500),'veg'=>($b['veg']??true)!==false,'bestseller'=>(bool)($b['bestseller']??false),'stock'=>max(0,(int)($b['stock']??0)),'reorderLevel'=>max(1,(int)($b['reorderLevel']??8)),'category'=>clean_text($b['category']??'essentials',40),'unit'=>clean_text($b['unit']??'unit',24),'sku'=>clean_text($b['sku']??('QB-'.$pid),40),'tags'=>[]];array_unshift($s['catalogStores'][$i]['menu'],$p);$created[]=$p;}done($s,201,['product'=>$created[0],'products'=>$created,'createdCount'=>count($created),'storeId'=>$created[0]['restId'],'storeIds'=>array_values(array_unique(array_column($created,'restId'))),'scope'=>count($created)>1?'all-stores':'single-store','summary'=>summary(scoped_stores($s['catalogStores'],$a)),'access'=>access_payload($a)]); }
        if($method==='GET'&&$path==='/api/admin/me')done($s,200,['access'=>access_payload($a)]);
        if($method==='GET'&&$path==='/api/admin/catalog')done($s,200,['stores'=>$stores,'summary'=>summary($stores),'globalSummary'=>summary(all_stores($s)),'access'=>access_payload($a)]);
        if($method==='GET'&&$path==='/api/admin/orders'){$orders=$a['role']==='store'?array_values(array_filter($s['orders'],fn($o)=>(int)($o['restId']??0)===(int)$a['storeId'])):$s['orders'];done($s,200,['orders'=>$orders,'access'=>access_payload($a)]);}
        if(preg_match('#^/api/admin/orders/(.+)/status$#',$path,$m)&&$method==='PATCH'){$id=rawurldecode($m[1]);foreach($s['orders'] as &$o)if($o['orderId']===$id){$o['status']=clean_text($b['status']??'',40);unset($o);done($s,200,['order'=>$o,'access'=>access_payload($a)]);}unset($o);fail(404,'Order not found');}
        if(preg_match('#^/api/admin/products/(\d+)(/stock)?$#',$path,$m)&&$method==='PATCH'){$pid=(int)$m[1];foreach($s['catalogStores'] as &$st)foreach($st['menu'] as &$p)if((int)$p['id']===$pid){if($a['role']==='store'&&(int)$st['id']!==(int)$a['storeId'])fail(403,'Store managers can only manage products in their assigned store.');if(isset($m[2])&&$m[2]==='/stock')$p['stock']=max(0,(int)($b['stock']??(($p['stock']??0)+(int)($b['delta']??0))));else foreach(['name','desc','price','img','veg','bestseller','reorderLevel','category','unit','sku'] as $k)if(array_key_exists($k,$b))$p[$k]=$b[$k];$product=$p;$sid=$st['id'];unset($p,$st);done($s,200,['product'=>$product,'storeId'=>$sid,'summary'=>summary(scoped_stores(all_stores($s),$a)),'access'=>access_payload($a)]);}unset($p,$st);fail(404,'Product not found');}
        fail(404,'API route not found');
    }
    fail(404,'API route not found');
}
api_main();
