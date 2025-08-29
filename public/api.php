<?php
require_once __DIR__.'/../src/Config.php';
require_once __DIR__.'/../src/DB.php';
require_once __DIR__.'/../src/Pricing.php';
require_once __DIR__.'/../src/Storage.php';
require_once __DIR__.'/../src/Email.php';
require_once __DIR__.'/../src/Util.php';

$path = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/api/upload' && $method === 'POST') {
  try {
    $ref = $_POST['quote_ref'] ?? Util::ref();
    $saved = [];
    foreach ($_FILES as $key => $file) {
      if (is_array($file['name'])) {
        $count = count($file['name']);
        for ($i=0;$i<$count;$i++) {
          $tmp = [
            'name'=>$file['name'][$i],'type'=>$file['type'][$i],
            'tmp_name'=>$file['tmp_name'][$i],'error'=>$file['error'][$i],'size'=>$file['size'][$i]
          ];
          $info = Storage::saveUpload($tmp, $ref);
          $saved[] = saveFile($ref, $info);
        }
      } else {
        $info = Storage::saveUpload($file, $ref);
        $saved[] = saveFile($ref, $info);
      }
    }
    Util::json(['ok'=>true,'quote_ref'=>$ref,'files'=>$saved]);
  } catch (Throwable $e) {
    Util::json(['ok'=>false,'error'=>$e->getMessage()], 400);
  }
}

if ($path === '/api/quote' && $method === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true) ?? [];
  try {
    $calc = Pricing::compute($in);
    $ref  = Util::ref();
    $pdo  = DB::pdo();

    $st = $pdo->prepare('INSERT INTO quotes(public_ref, material_id, color_id, part_weight_g, cavities, quantity, operations_json, price_breaks_json, manual_required, created_at)
                         VALUES (?,?,?,?,?,?,?, ?, 1, datetime("now"))');
    $st->execute([
      $ref,
      $in['material_id'], $in['color_id'],
      $in['part_weight_g'], $in['cavities'], $in['quantity'],
      json_encode($in['operation_ids'] ?? []),
      json_encode($calc['breaks'])
    ]);

    Util::json(['ok'=>true, 'quote_ref'=>$ref] + $calc);
  } catch (Throwable $e) {
    Util::json(['ok'=>false,'error'=>$e->getMessage()], 400);
  }
}

if ($path === '/api/accept' && $method === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true) ?? [];
  try {
    $pdo = DB::pdo();
    $st = $pdo->prepare('INSERT INTO leads(quote_ref, name, email, company, phone, country, region, notes, created_at)
                         VALUES (?,?,?,?,?,?,?, ?, datetime("now"))');
    $st->execute([
      $in['quote_ref'],$in['name'],$in['email'],$in['company'],$in['phone'],
      $in['country']??null,$in['region']??null,$in['notes']??null
    ]);

    Email::notifyNewLead($in, ['public_ref'=>$in['quote_ref']]);
    Util::json(['ok'=>true]);
  } catch (Throwable $e) {
    Util::json(['ok'=>false,'error'=>$e->getMessage()], 400);
  }
}

http_response_code(404);
echo 'Not found';

function saveFile($ref, $info){
  $pdo = DB::pdo();
  $st = $pdo->prepare('INSERT INTO files(quote_ref,name,mime,size_bytes,path,created_at) VALUES (?,?,?,?,?, datetime("now"))');
  $st->execute([$ref,$info['name'],$info['mime'],$info['size'],$info['path']]);
  $basename = basename($info['path']);
  return ['name'=>$info['name'],'size'=>$info['size'],'url'=> '/uploads/'.$basename];
}