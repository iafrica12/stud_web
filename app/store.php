<?php
declare(strict_types=1);
function saveStore(array $db): void {
    $path=__DIR__.'/../data/store.json';
    $temp=tempnam(dirname($path),'store-');
    if ($temp===false) throw new RuntimeException('Cannot create temporary data file');
    try {
        $json=json_encode($db,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if (file_put_contents($temp,$json)!==strlen($json)) throw new RuntimeException('Cannot write data');
        if (!rename($temp,$path)) throw new RuntimeException('Cannot replace data');
    } finally { if (is_file($temp)) unlink($temp); }
}
function loadStore(): array {
    $path=__DIR__.'/../data/store.json';
    if (!is_file($path)) {
        $db=['users'=>[
            ['id'=>'admin-1','name'=>'Administrator','email'=>'admin@example.com','role'=>'admin','password'=>password_hash('Admin123!',PASSWORD_DEFAULT)],
            ['id'=>'user-1','name'=>'Demo User','email'=>'user@example.com','role'=>'user','password'=>password_hash('User123!',PASSWORD_DEFAULT)]
        ],'records'=>[]];
        saveStore($db);
        return $db;
    }
    $db=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($db) || !isset($db['users'],$db['records']) || !is_array($db['users']) || !is_array($db['records'])) throw new RuntimeException('Invalid store structure');
    return $db;
}
