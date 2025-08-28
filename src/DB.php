<?php
require_once __DIR__.'/Config.php';

class DB {
  private static ?PDO $pdo = null;
  static function pdo(): PDO {
    if (!self::$pdo) {
      $c = Config::load();
      $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ];
      self::$pdo = new PDO($c['dsn'], $c['user'], $c['pass'], $opt);
      // Ensure foreign keys are on for SQLite
      self::$pdo->exec('PRAGMA foreign_keys = ON;');
    }
    return self::$pdo;
  }
}