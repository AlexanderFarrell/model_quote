<?php
if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) { require __DIR__.'/api.php'; exit; }
$cfg = require __DIR__.'/../config.php';
$CURRENCY = $cfg['currency'] ?? '$';
$BASE = $cfg['base_url'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Instant Quote</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --bg:#0b0c10; --card:#121319; --muted:#232434; --text:#e8e9f1; --sub:#b7b9c8; --accent:#68d391; --edge:#2c2f3a; }
  *{box-sizing:border-box}
  body{margin:0; background:var(--bg); color:var(--text); font:14px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Arial;}
  header{padding:18px 22px; border-bottom:1px solid var(--edge); display:flex; gap:12px; align-items:center}
  .wrap{max-width:1100px; margin:24px auto; padding:0 16px; display:grid; grid-template-columns:1fr 1fr; gap:16px}
  .card{background:var(--card); border:1px solid var(--edge); border-radius:14px; padding:16px}
  .row{display:grid; grid-template-columns:210px 1fr; gap:10px; margin:8px 0}
  input, select, textarea{width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--edge); background:#0f1016; color:var(--text)}
  button{padding:10px 14px; border-radius:10px; border:1px solid var(--edge); background:#171a22; color:var(--text); cursor:pointer}
  button.primary{background:var(--accent); color:#0b0c10; font-weight:600}
  .upload{border:2px dashed #3a3d49; border-radius:12px; padding:16px; text-align:center; color:var(--sub)}
  .upload.drag{border-color:#8da2fb; background:#111321}
  .filelist{margin-top:10px; font-size:13px; color:var(--sub); white-space:pre-wrap}
  canvas.viewer{width:100%; height:260px; display:block; background:#0f1016; border-radius:10px; border:1px solid var(--edge)}
  .grid{display:grid; grid-template-columns:repeat(5,1fr); gap:10px}
  .col{background:var(--muted); border:1px solid var(--edge); border-radius:12px; padding:10px}
  .meta{color:var(--sub); font-size:12px}
  .banner{padding:8px 10px; border-radius:10px; margin-bottom:10px; background:#2a1414; border:1px solid #5f2c2c; color:#ffcccc; display:none}
</style>
</head>
<body>
<header><h1>Instant Quote</h1></header>

<div class="wrap">
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
      <div id="viewerHint" class="meta">STL preview appears here when available.</div>
    </div>
  </section>

  <section class="card">
    <h3>Set Parameters</h3>
    <div class="row"><label>Confirm Part Weight (g)</label><input id="weight" type="number" step="0.01" value="12.5"></div>
    <div class="row"><label>Base Material</label>
      <select id="material">
        <option value="1">FDM ABS</option>
        <option value="999-not-sure">Not Sure?</option>
        <option value="998-other">Other</option>
      </select>
    </div>
    <div class="row"><label>Color</label>
      <select id="color">
        <option value="1">Natural</option>
        <option value="999-not-sure">Not Sure?</option>
        <option value="998-other">Other</option>
      </select>
    </div>
    <div class="row"><label>Quantity</label><input id="quantity" type="number" value="1" min="1"></div>
    <div class="row"><label>Number of Mold Cavities</label><input id="cavities" type="number" value="1" min="1"></div>
    <div class="row"><label>Additional Operations</label>
      <select id="ops" multiple size="3">
        <option value="1">Deburr</option>
        <option value="2">Basic QA</option>
        <option value="997-none">None or Skip</option>
        <option value="998-other">Other (Manual)</option>
      </select>
    </div>
    <div class="row"><label>Do you have an existing Mold?</label>
      <div>
        <label><input type="radio" name="mold" value="yes" checked> Yes</label>
        <label style="margin-left:12px"><input type="radio" name="mold" value="no_need_mold"> No, I also need a mold</label>
        <label style="margin-left:12px"><input type="radio" name="mold" value="mold_only"> I want a mold, only</label>
      </div>
    </div>
    <div class="row"><label>Lead Time</label>
      <div>
        <label><input type="radio" name="lead" value="standard" checked> Standard</label>
        <label style="margin-left:12px"><input type="radio" name="lead" value="expedited"> Expedited (+expedite cost)</label>
      </div>
    </div>
    <button id="quoteBtn" class="primary">Get Instant Quote</button>
  </section>
</div>

<section class="card" style="max-width:1100px; margin:16px auto; padding:16px">
  <div id="manualBanner" class="banner"></div>
  <h3>Instant Quote</h3>
  <div class="meta" id="tierMeta"></div>
  <div id="quoteGrid" class="grid" style="margin-top:10px"></div>
</section>

<section class="card" style="max-width:1100px; margin:16px auto; padding:16px">
  <h3>Proceed (YES)</h3>
  <div class="row"><label>Name</label><input id="lead_name" autocomplete="name"></div>
  <div class="row"><label>Email</label><input id="lead_email" type="email" autocomplete="email"></div>
  <div class="row"><label>Company</label><input id="lead_company" autocomplete="organization"></div>
  <div class="row"><label>Phone</label><input id="lead_phone" autocomplete="tel"></div>
  <div class="row"><label>Notes</label><textarea id="lead_notes" placeholder="Anything we should know?"></textarea></div>
  <button id="yesBtn" disabled>Send &amp; Request Manual Quote</button>
  <div id="yesMsg" class="meta" style="margin-top:10px"></div>
</section>

<div class="meta" style="max-width:1100px; margin:10px auto 40px; padding:0 16px">
  Currency: <?= htmlspecialchars($CURRENCY) ?> · Uploads: <code>/public/uploads</code>
</div>

<!-- Three.js v0.124 global build + legacy STL loader -->
<script src="https://cdn.jsdelivr.net/npm/three@0.124/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.124/examples/js/loaders/STLLoader.js"></script>

<script>
  window.IQ_CFG = { base: "<?= htmlspecialchars($BASE) ?>", currency: "<?= htmlspecialchars($CURRENCY) ?>" };
</script>
<script src="/js/app.js"></script>
</body>
</html>