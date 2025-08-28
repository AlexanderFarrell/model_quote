<?php
require_once __DIR__.'/DB.php';

class Pricing {
  static function compute(array $in): array {
    $pdo = DB::pdo();

    $mat  = self::row($pdo,'SELECT * FROM materials WHERE id=?', [$in['material_id']]);
    $ops  = [];
    foreach ($in['operation_ids'] ?? [] as $opId) {
      $ops[] = self::row($pdo,'SELECT * FROM operations WHERE id=?',[$opId]);
    }
    $tiers = $pdo->query('SELECT * FROM price_tiers ORDER BY order_index')->fetchAll();

    $g = max(0.01, floatval($in['part_weight_g']));
    $cav = max(1, intval($in['cavities']));

    $res = [];
    foreach ($tiers as $t) {
      $qty = max(1, intval($t['qty']));

      $material_cost = $g * $mat['cost_per_g'];
      $setup_per_part = $mat['setup_fee'] / $qty;

      $machine_time_hr = ($g / max(1e-6, $mat['build_rate_g_per_hr'])) / $cav;
      $machine_cost = $machine_time_hr * $mat['machine_rate_per_hr'];

      $ops_cost = 0;
      foreach ($ops as $op) {
        $ops_cost += $op['cost_per_part'] + ($op['cost_fixed'] / $qty);
      }

      $raw = $material_cost + $machine_cost + $setup_per_part + $ops_cost;
      $unit = max(self::round2($raw * (1 + $mat['margin_pct'])), 0.01);
      $extended = self::round2($unit * $qty);

      $res[] = [
        'label' => $t['label'],
        'qty' => $qty,
        'unit_price' => $unit,
        'extended' => $extended,
      ];
    }

    return [
      'manual_required' => true, // MVP rule: always manual
      'breaks' => $res
    ];
  }

  private static function row(PDO $pdo, string $sql, array $p): array {
    $st = $pdo->prepare($sql); $st->execute($p);
    $r = $st->fetch(); if (!$r) throw new RuntimeException('Not found');
    return $r;
  }
  private static function round2(float $x): float { return round($x + 1e-9, 2); }
}