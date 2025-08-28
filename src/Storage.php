<?php
require_once __DIR__.'/Config.php';

class Storage {
  static array $allowed = ['stl','step','iges','pdf','png','jpg','jpeg'];
  static function saveUpload(array $file, string $quoteRef): array {
    $cfg = Config::load();
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload error');
    if ($file['size'] > $cfg['upload_max_mb'] * 1024 * 1024) throw new RuntimeException('File too large');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, self::$allowed)) throw new RuntimeException('File type not allowed');

    $dir = rtrim($cfg['upload_dir'],'/'); if (!is_dir($dir)) mkdir($dir,0775,true);
    $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', $file['name']);
    $name = uniqid('f_',true).'_'.$safe;
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Save failed');

    return ['path'=>$path,'name'=>$file['name'],'mime'=>mime_content_type($path),'size'=>$file['size']];
  }
}