<?php
class Config {
  private static ?array $cfg = null;

  public static function load(): array {
    if (self::$cfg === null) {
      self::$cfg = require __DIR__ . '/../config.php';
    }
    return self::$cfg;
  }
}