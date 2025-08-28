<?php
class Util {
  static function json($data, int $code=200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
  }
  static function ref(): string { return bin2hex(random_bytes(6)); }
}