<?php
// JSON API: authorization is enforced here, never only in JavaScript.
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Strict', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require __DIR__.'/../app/store.php';
function fail(string $message, int $code=400): void { http_response_code($code); echo json_encode(['error'=>$message]); exit; }
function field(array $input, string $key, int $max=120): string {
    if (!isset($input[$key]) || !is_string($input[$key])) fail('Invalid '.$key.'.');
    $value=trim($input[$key]);
    if ($value==='' || strlen($value)>$max) fail($key.' is required (maximum '.$max.' characters).');
    return $value;
}
function safeUser(array $user): array { unset($user['password']); return $user; }
try {
    $method=$_SERVER['REQUEST_METHOD'];
    if (!in_array($method,['GET','POST'],true)) fail('Method not allowed.',405);
    $_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(32));
    $input=[];
    if ($method==='POST') {
        if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) fail('Refresh the page and try again.',403);
        $input=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($input)) fail('Invalid request.');
    }
    $action=$_GET['action']??'session';
    // An exclusive lock covers the full read/validate/write transaction.
    $lock=fopen(__DIR__.'/../data/store.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Storage lock failed');
    $db=loadStore();
    $me=null;
    foreach ($db['users'] as $user) if ($user['id']===($_SESSION['user_id']??'')) $me=$user;
    $result=[]; $changed=false;
    if ($action==='session' && $method==='GET') {
        $result=['user'=>$me?safeUser($me):null,'csrf'=>$_SESSION['csrf']];
    } elseif ($action==='login' && $method==='POST') {
        $email=strtolower(field($input,'email',254)); $password=$input['password']??'';
        if (!is_string($password) || $password==='' || strlen($password)>200) fail('Invalid email or password.',401);
        $found=null;
        foreach ($db['users'] as $user) if ($user['email']===$email && password_verify($password,$user['password'])) $found=$user;
        if (!$found) fail('Invalid email or password.',401);
        session_regenerate_id(true); $_SESSION['user_id']=$found['id'];
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        $result=['user'=>safeUser($found),'csrf'=>$_SESSION['csrf']];
    } elseif ($action==='logout' && $method==='POST') {
        $_SESSION=[]; session_destroy(); $result=['ok'=>true];
    } else {
        if (!$me) fail('Please log in.',401);
        $admin=$me['role']==='admin';
        if ($action==='records' && $method==='GET') {
            $result=['records'=>array_values(array_filter($db['records'],fn($r)=>$admin || $r['owner_id']===$me['id']))];
        } elseif ($action==='save_record' && $method==='POST') {
            $title=field($input,'title'); $description=field($input,'description',2000);
            $status=field($input,'status');
            if (!in_array($status,['Pending','In progress','Completed'],true)) fail('Invalid status.');
            $id=$input['id']??''; $index=null;
            foreach ($db['records'] as $i=>$r) if ($r['id']===$id) $index=$i;
            if ($id!=='' && $index===null) fail('Record not found.',404);
            if ($index!==null && !$admin && $db['records'][$index]['owner_id']!==$me['id']) fail('Access denied.',403);
            $record=['id'=>$id?:bin2hex(random_bytes(8)),'title'=>$title,'description'=>$description,'status'=>$status,'owner_id'=>$index!==null?$db['records'][$index]['owner_id']:$me['id'],'updated_at'=>gmdate('c')];
            if ($index===null) $db['records'][]=$record; else $db['records'][$index]=$record;
            $changed=true; $result=['ok'=>true];
        } elseif ($action==='delete_record' && $method==='POST') {
            $id=field($input,'id'); $index=null;
            foreach ($db['records'] as $i=>$r) if ($r['id']===$id) $index=$i;
            if ($index===null) fail('Record not found.',404);
            if (!$admin && $db['records'][$index]['owner_id']!==$me['id']) fail('Access denied.',403);
            array_splice($db['records'],$index,1); $changed=true; $result=['ok'=>true];
        } elseif ($action==='users' && $method==='GET' && $admin) {
            $result=['users'=>array_map('safeUser',$db['users'])];
        } elseif ($action==='save_user' && $method==='POST' && $admin) {
            $id=$input['id']??''; $name=field($input,'name'); $email=strtolower(field($input,'email',254)); $role=field($input,'role');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid email.');
            if (!in_array($role,['admin','user'],true)) fail('Invalid role.');
            $index=null;
            foreach ($db['users'] as $i=>$u) { if ($u['id']===$id) $index=$i; if ($u['email']===$email && $u['id']!==$id) fail('Email already exists.'); }
            if ($id!=='' && $index===null) fail('User not found.',404);
            if ($id===$me['id'] && $role!=='admin') fail('You cannot demote your own account.');
            $password=$input['password']??'';
            if (!is_string($password) || strlen($password)>200 || (($index===null || $password!=='') && strlen($password)<8)) fail('Password must be 8–200 characters.');
            $hash=$password!==''?password_hash($password,PASSWORD_DEFAULT):$db['users'][$index]['password'];
            $user=['id'=>$id?:bin2hex(random_bytes(8)),'name'=>$name,'email'=>$email,'role'=>$role,'password'=>$hash];
            if ($index===null) $db['users'][]=$user; else $db['users'][$index]=$user;
            $changed=true; $result=['ok'=>true];
        } elseif ($action==='delete_user' && $method==='POST' && $admin) {
            $id=field($input,'id');
            if ($id===$me['id']) fail('You cannot delete your own account.');
            $before=count($db['users']); $db['users']=array_values(array_filter($db['users'],fn($u)=>$u['id']!==$id));
            if (count($db['users'])===$before) fail('User not found.',404);
            $db['records']=array_values(array_filter($db['records'],fn($r)=>$r['owner_id']!==$id));
            $changed=true; $result=['ok'=>true];
        } else fail('Action unavailable or access denied.',403);
    }
    if ($changed) saveStore($db);
    flock($lock,LOCK_UN); fclose($lock);
    echo json_encode($result,JSON_THROW_ON_ERROR);
} catch (JsonException $e) { fail('Invalid JSON data.',400); }
catch (Throwable $e) { error_log((string)$e); fail('Storage error. Check the data folder permissions and server log.',500); }
