<?php
// Route /api/* to API
if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) { require __DIR__.'/api.php'; exit; }
$cfg = require __DIR__.'/../config.php';
$CURRENCY = $cfg['currency'] ?? '£';
$BASE = $cfg['base_url'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Instant Quote</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#0b0c10; --card:#121319; --muted:#232434; --text:#e8e9f1; --sub:#b7b9c8;
    --accent:#68d391; --warn:#fde68a; --edge:#2c2f3a;
  }
  *{box-sizing:border-box}
  body{margin:0; background:var(--bg); color:var(--text); font:14px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Arial;}
  header{padding:18px 22px; border-bottom:1px solid var(--edge); display:flex; gap:12px; align-items:center}
  header h1{margin:0; font-size:18px}
  .wrap{max-width:1100px; margin:24px auto; padding:0 16px; display:grid; grid-template-columns:1fr 1fr; gap:16px}
  .card{background:var(--card); border:1px solid var(--edge); border-radius:14px; padding:16px}
  h3{margin:0 0 10px 0; font-size:15px}
  .row{display:grid; grid-template-columns:210px 1fr; gap:10px; margin:8px 0}
  input[type="number"], input[type="text"], input[type="email"], select, textarea{
    width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--edge); background:#0f1016; color:var(--text);
  }
  textarea{min-height:70px; resize:vertical}
  button{padding:10px 14px; border-radius:10px; border:1px solid var(--edge); background:#171a22; color:var(--text); cursor:pointer}
  button.primary{background:var(--accent); color:#0b0c10; border-color:#3ea56b; font-weight:600}
  button[disabled]{opacity:.5; cursor:not-allowed}
  .warning{background:#1e1f12; border:1px solid #5b5a2e; color:#efe9b2; padding:8px 10px; border-radius:10px; margin-top:10px}
  .grid{display:grid; grid-template-columns:repeat(5,1fr); gap:10px}
  .col{background:var(--muted); border:1px solid var(--edge); border-radius:12px; padding:10px}
  .col b{display:block; margin-bottom:6px}
  .upload{
    border:2px dashed #3a3d49; border-radius:12px; padding:16px; text-align:center; color:var(--sub);
  }
  .upload.drag{border-color:#8da2fb; background:#111321}
  .filelist{margin-top:10px; font-size:13px; color:var(--sub); white-space:pre-wrap}
  canvas.viewer{width:100%; height:260px; display:block; background:#0f1016; border-radius:10px; border:1px solid var(--edge)}
  .foot{max-width:1100px; margin:10px auto 40px; padding:0 16px; color:var(--sub); font-size:12px}
  .pill{display:inline-block; padding:2px 8px; border-radius:999px; background:#1b1d27; border:1px solid var(--edge); font-size:11px; color:#b9bbca}
</style>
</head>
<body>
<header>
  <h1>Instant Quote</h1>
  <span class="pill">MVP</span>
</header>

<div class="wrap">
  <!-- LEFT: Upload + Preview -->
  <section class="card">
    <h3>Upload files</h3>
    <div id="drop" class="upload">Drag &amp; drop STL/STEP/IGES/PDF/PNG/JPG here or
      <label style="display:inline-block; margin-left:6px">
        <input id="file" type="file" multiple style="display:none">
        <button id="pickBtn" type="button">Choose files</button>
      </label>
    </div>
    <div class="filelist" id="uploads"></div>
    <div style="margin-top:12px">
      <canvas id="stlCanvas" class="viewer"></canvas>
      <div id="viewerHint" style="color:var(--sub); font-size:12px; margin-top:6px">
        STL preview appears here when available.
      </div>
    </div>
    <div class="warning">First revision always routes to manual review.</div>
  </section>

  <!-- RIGHT: Parameters -->
  <section class="card">
    <h3>Set Parameters</h3>
    <div class="row"><label>Confirm Part Weight (g)</label><input id="weight" type="number" step="0.01" value="12.5"></div>
    <div class="row"><label>Base Material</label>
      <select id="material"><option value="1">FDM ABS</option></select>
    </div>
    <div class="row"><label>Color</label>
      <select id="color"><option value="1">Natural</option></select>
    </div>
    <div class="row"><label>Quantity</label><input id="quantity" type="number" value="1" min="1"></div>
    <div class="row"><label>Number of Mold Cavities</label><input id="cavities" type="number" value="1" min="1"></div>
    <div class="row"><label>Additional Operations</label>
      <select id="ops" multiple size="3">
        <option value="1">Deburr</option>
        <option value="2">Basic QA</option>
      </select>
    </div>
    <button id="quoteBtn" class="primary">Get Instant Quote</button>
  </section>
</div>

<!-- QUOTE GRID -->
<section class="card" style="max-width:1100px; margin:16px auto; padding:16px">
  <h3>Instant Quote</h3>
  <div id="quoteGrid" class="grid"></div>
</section>

<!-- YES / Lead capture -->
<section class="card" style="max-width:1100px; margin:16px auto; padding:16px">
  <h3>Proceed (YES)</h3>
  <div class="row"><label>Name</label><input id="lead_name" autocomplete="name"></div>
  <div class="row"><label>Email</label><input id="lead_email" type="email" autocomplete="email"></div>
  <div class="row"><label>Company</</label><input id="lead_company" autocomplete="organization"></div>
  <div class="row"><label>Phone</label><input id="lead_phone" autocomplete="tel"></div>
  <div class="row"><label>Notes</label><textarea id="lead_notes" placeholder="Anything we should know?"></textarea></div>
  <button id="yesBtn" disabled>Send &amp; Request Manual Quote</button>
  <div id="yesMsg" style="margin-top:10px; color:var(--sub)"></div>
</section>

<div class="foot">
  <span>Currency: <?= htmlspecialchars($CURRENCY) ?></span> ·
  <span>Uploads folder: <code>/public/uploads</code></span>
</div>

<!-- Three.js v0.124 GLOBAL build + legacy examples/js loader -->
<script src="https://cdn.jsdelivr.net/npm/three@0.124/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.124/examples/js/loaders/STLLoader.js"></script>

<script>
  window.IQ_CFG = {
    base: "<?= htmlspecialchars($BASE) ?>",
    currency: "<?= htmlspecialchars($CURRENCY) ?>"
  };
</script>
<script src="/js/app.js"></script>
</body>
</html>