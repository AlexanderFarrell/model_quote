<?php
require_once __DIR__.'/DB.php';

class Pricing {
  // Tune these from your workbook
  private static function settings(): array {
    return [
      // General shop constants
      'labor_rate'        => 23.0,   // $/hr
      'operator_pct'      => 0.50,   // 50% operator utilization
      'overhead_rate'     => 25.0,   // $/hr
      'efficiency'        => 0.90,   // 90% efficiency
      'colorant_pct'      => 0.04,   // 4% color mix by weight
      'min_part_weight_g' => 0.05,

      // Tiering by SHOT weight (part_weight * cavities)
      // Each tier defines cycle time, machine $/hr, and setup fee (batch)
      'tiers' => [
        ['max_weight_g'=>14,  'cycle_s'=>30,  'machine_rate_hr'=>12, 'setup_fee'=>125],
        ['max_weight_g'=>25,  'cycle_s'=>40,  'machine_rate_hr'=>15, 'setup_fee'=>150],
        ['max_weight_g'=>42,  'cycle_s'=>50,  'machine_rate_hr'=>20, 'setup_fee'=>200],
        ['max_weight_g'=>129, 'cycle_s'=>60,  'machine_rate_hr'=>23, 'setup_fee'=>230],
        ['max_weight_g'=>263, 'cycle_s'=>80,  'machine_rate_hr'=>28, 'setup_fee'=>250],
        ['max_weight_g'=>907, 'cycle_s'=>120, 'machine_rate_hr'=>40, 'setup_fee'=>300],
      ],

      // Per price-break adjustments 1..5
      'profit_pct' => [1=>0.40, 2=>0.35, 3=>0.30, 4=>0.25, 5=>0.20],
      'scrap_pct'  => [1=>0.13255, 2=>0.08853, 3=>0.040875, 4=>0.019326, 5=>0.01836],
      'bom_markup' => [1=>0.10,    2=>0.09,    3=>0.07,     4=>0.06,     5=>0.05],
    ];
  }

  public static function compute(array $in): array {
    $pdo = DB::pdo();
    $S = self::settings();

    // Catalog rows
    $mat   = self::row($pdo, 'SELECT * FROM materials WHERE id=?', [$in['material_id']]);
    $color = self::row($pdo, 'SELECT * FROM colors    WHERE id=?', [$in['color_id']]);

    $ops = [];
    foreach ($in['operation_ids'] ?? [] as $opId) {
      $ops[] = self::row($pdo, 'SELECT * FROM operations WHERE id=?', [$opId]);
    }
    $tiers = $pdo->query('SELECT * FROM price_tiers ORDER BY order_index')->fetchAll();

    // Inputs
    $g      = max($S['min_part_weight_g'], floatval($in['part_weight_g'] ?? 0));
    $cav    = max(1, intval($in['cavities'] ?? 1));
    $lead   = $in['lead_time'] ?? 'standard';         // 'standard'|'expedited'
    $mold   = $in['existing_mold'] ?? 'yes';          // 'yes'|'no_need_mold'|'mold_only'

    // Costs per gram
    $mat_cost_per_g   = isset($mat['cost_per_g'])   ? floatval($mat['cost_per_g'])   : 0.0;
    $color_cost_per_g = isset($color['cost_per_g']) ? floatval($color['cost_per_g']) : 0.0;
    $combined_cost_per_g = $mat_cost_per_g + $S['colorant_pct'] * $color_cost_per_g;

    // Determine tier by SHOT weight
    $shot_weight = $g * $cav;
    $tierIdx = count($S['tiers']) - 1;
    foreach ($S['tiers'] as $i => $tinfo) {
      if ($shot_weight <= $tinfo['max_weight_g']) { $tierIdx = $i; break; }
    }
    $tierInfo = $S['tiers'][$tierIdx];

    // Manual-quote checks
    $manual_reasons = [];
    $matLabel = strtolower(strval($in['material_label'] ?? ($mat['name'] ?? '')));
    $colLabel = strtolower(strval($in['color_label']   ?? ($color['name'] ?? '')));
    if (str_contains($matLabel, 'not sure') || str_contains($matLabel, 'other')) $manual_reasons[] = 'Material not specified';
    if (str_contains($colLabel, 'not sure') || str_contains($colLabel, 'other')) $manual_reasons[] = 'Color not specified';

    foreach ($ops as $op) {
      $n = strtolower(strval($op['name']));
      if ($n !== 'deburr' && $n !== 'basic qa' && $n !== 'none or skip') {
        $manual_reasons[] = 'Secondary process requires review';
        break;
      }
    }
    if ($mold === 'no_need_mold' || $mold === 'mold_only') {
      $manual_reasons[] = 'Mold required';
    }

    // Price breaks
    $breaks = [];
    foreach ($tiers as $i0 => $t) {
      $breakIdx = $i0 + 1;
      $qty      = max(1, intval($t['qty']));

      // Time & rates
      $shots  = intdiv($qty + $cav - 1, $cav); // ceil(qty/cav)
      $hours  = ($shots * $tierInfo['cycle_s'] / 3600.0) / max(1e-6, $S['efficiency']);
      $machine_cost  = $tierInfo['machine_rate_hr'] * $hours;
      $labor_cost    = $S['labor_rate']  * $S['operator_pct'] * $hours;
      $overhead_cost = $S['overhead_rate'] * $hours;
      $setup_fee     = $tierInfo['setup_fee'];

      // BOM & material
      $scrap = $S['scrap_pct'][$breakIdx] ?? 0.0;
      $bom_mu= $S['bom_markup'][$breakIdx] ?? 0.0;
      $profit= $S['profit_pct'][$breakIdx] ?? 0.30;

      $mat_weight = $g * $qty * (1.0 + $scrap);
      $material_cost   = $combined_cost_per_g * $mat_weight;
      $bom_markup_cost = $material_cost * $bom_mu;

      // Operations
      $ops_per_part = 0.0; $ops_fixed = 0.0;
      foreach ($ops as $op) {
        $ops_per_part += floatval($op['cost_per_part'] ?? 0);
        $ops_fixed    += floatval($op['cost_fixed'] ?? 0);
      }
      $ops_cost = $ops_per_part * $qty + $ops_fixed;

      $total_batch_cost = $machine_cost + $labor_cost + $overhead_cost + $setup_fee + $material_cost + $bom_markup_cost + $ops_cost;
      $unit_cost = $total_batch_cost / $qty;

      $unit_price = max(self::round2($unit_cost * (1.0 + $profit)), 0.01);
      $extended   = self::round2($unit_price * $qty);

      $breaks[] = [
        'label' => $t['label'],
        'qty' => $qty,
        'unit_price' => $unit_price,
        'extended' => $extended,
      ];
    }

    return [
      'manual_required' => count($manual_reasons) > 0,
      'manual_reasons'  => $manual_reasons,
      'tier'            => $tierIdx + 1,
      'breaks'          => $breaks,
      'lead_time'       => $lead,
    ];
  }

  private static function row(PDO $pdo, string $sql, array $p): array {
    $st = $pdo->prepare($sql); $st->execute($p);
    $r = $st->fetch(); if (!$r) throw new RuntimeException('Not found');
    return $r;
  }
  private static function round2(float $x): float { return round($x + 1e-9, 2); }
}