// ===== Helpers =====
const $ = (s)=>document.querySelector(s);
const currencyCode = (IQ_CFG.currency || '£') === '£' ? 'GBP' : 'USD';
const fmt = (x)=>new Intl.NumberFormat(undefined,{style:'currency',currency: currencyCode}).format(x);

// ===== State =====
let quoteRef = null;
let uploaded = []; // {name,size,url?}

// ===== Uploads =====
const fileInput = $('#file');
$('#pickBtn').addEventListener('click', ()=> fileInput.click());
const drop = $('#drop');
['dragenter','dragover'].forEach(evt=> drop.addEventListener(evt, e=>{ e.preventDefault(); drop.classList.add('drag'); }));
['dragleave','drop'].forEach(evt=> drop.addEventListener(evt, e=>{ e.preventDefault(); drop.classList.remove('drag'); }));
drop.addEventListener('drop', (e)=> handleFiles(e.dataTransfer.files));
fileInput.addEventListener('change', (e)=> handleFiles(e.target.files));

async function handleFiles(fileList){
  if (!fileList || !fileList.length) return;
  const fd = new FormData();
  for (const f of fileList) fd.append('file[]', f);
  if (quoteRef) fd.append('quote_ref', quoteRef);
  const res = await fetch('/api/upload', { method:'POST', body: fd });
  const data = await res.json();
  if (!data.ok) { alert(data.error || 'Upload failed'); return; }
  quoteRef = data.quote_ref;
  uploaded.push(...data.files);
  renderUploads();
  tryPreviewSTL();
}
function renderUploads(){
  $('#uploads').textContent = uploaded.map(f=>{
    const kb = Math.max(1, Math.round((f.size||0)/1024));
    return `${f.name} (${kb} KB)`;
  }).join('\n');
}

// ===== Quote compute =====
$('#quoteBtn').addEventListener('click', async ()=>{
  const matSel = $('#material');
  const colSel = $('#color');
  const payload = {
    material_id: pickId(matSel.value),
    color_id: pickId(colSel.value),
    material_label: matSel.options[matSel.selectedIndex]?.text || '',
    color_label: colSel.options[colSel.selectedIndex]?.text || '',
    part_weight_g: parseFloat($('#weight').value || '0'),
    quantity: parseInt($('#quantity').value || '1'),
    cavities: parseInt($('#cavities').value || '1'),
    operation_ids: parseOps(),
    existing_mold: document.querySelector('input[name="mold"]:checked')?.value || 'yes',
    lead_time: document.querySelector('input[name="lead"]:checked')?.value || 'standard'
  };
  const r = await fetch('/api/quote', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
  });
  const d = await r.json();
  if (!d.ok) { alert(d.error || 'Quote failed'); return; }
  quoteRef = d.quote_ref;
  renderMeta(d);
  renderBreaks(d.breaks);
  $('#yesBtn').disabled = false;
});

function pickId(v){ return /^\d+$/.test(v) ? parseInt(v,10) : 1; } // fallback id for "Not Sure/Other"
function parseOps(){
  return Array.from($('#ops').selectedOptions).map(o=>{
    if (o.value === '997-none') return [];
    if (o.value === '998-other') return []; // backend will manual-flag based on names later if you model them
    return parseInt(o.value,10);
  }).flat();
}

function renderMeta(d){
  $('#tierMeta').textContent = d.tier ? `Weight tier used: ${d.tier}` : '';
  const banner = $('#manualBanner');
  if (d.manual_required){
    banner.style.display = '';
    banner.textContent = (d.manual_reasons || []).join('; ') || 'Manual review required';
  } else {
    banner.style.display = 'none';
    banner.textContent = '';
  }
}

function renderBreaks(breaks){
  const grid = $('#quoteGrid');
  grid.innerHTML = '';
  breaks.forEach(b=>{
    const el = document.createElement('div');
    el.className = 'col';
    el.innerHTML = `
      <b>${b.label}</b>
      <div>Unit: ${fmt(b.unit_price)}</div>
      <div>@ ${b.qty}</div>
      <div>Order Total: ${fmt(b.extended)}</div>
    `;
    grid.appendChild(el);
  });
}

// ===== YES flow =====
$('#yesBtn').addEventListener('click', async ()=>{
  const payload = {
    quote_ref: quoteRef,
    name: $('#lead_name').value.trim(),
    email: $('#lead_email').value.trim(),
    company: $('#lead_company').value.trim(),
    phone: $('#lead_phone').value.trim(),
    notes: $('#lead_notes').value.trim()
  };
  if (!payload.email) { alert('Email required'); return; }
  const r = await fetch('/api/accept', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
  const d = await r.json();
  if (!d.ok) { alert(d.error || 'Failed to send'); return; }
  $('#yesMsg').textContent = 'Thanks! We’ll review and follow up by email.';
  $('#yesBtn').disabled = true;
});

// ===== STL preview (Three.js legacy globals) =====
let gl = { renderer:null, scene:null, camera:null, loader:null, mesh:null };
function tryPreviewSTL(){
  const stl = uploaded.find(f => f.url && f.name.toLowerCase().endsWith('.stl'));
  if (!stl) return;
  const canvas = $('#stlCanvas');
  if (!gl.renderer){
    gl.renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
    gl.renderer.setPixelRatio(Math.min(2, window.devicePixelRatio||1));
    gl.scene = new THREE.Scene();
    gl.scene.background = new THREE.Color(0x0f1016);
    gl.scene.add(new THREE.DirectionalLight(0xffffff, 1).position.set(1,1,1));
    gl.scene.add(new THREE.AmbientLight(0xffffff, 0.5));
    const aspect = canvas.clientWidth / canvas.clientHeight;
    gl.camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 2000);
    gl.camera.position.set(0, 0, 80);
    gl.loader = new THREE.STLLoader();
    const onResize = ()=>{
      const w = canvas.clientWidth, h = canvas.clientHeight;
      gl.renderer.setSize(w, h, false);
      gl.camera.aspect = w/h; gl.camera.updateProjectionMatrix();
      gl.renderer.render(gl.scene, gl.camera);
    };
    window.addEventListener('resize', onResize); onResize();
  }
  gl.loader.load(stl.url, (geom)=>{
    if (gl.mesh) gl.scene.remove(gl.mesh);
    const mat = new THREE.MeshPhongMaterial({ color: 0x8da2fb, specular:0x111111, shininess: 50 });
    gl.mesh = new THREE.Mesh(geom, mat);
    geom.computeBoundingBox();
    const bb = geom.boundingBox;
    const size = bb.getSize(new THREE.Vector3());
    const center = bb.getCenter(new THREE.Vector3());
    gl.mesh.position.sub(center);
    gl.scene.add(gl.mesh);
    const maxDim = Math.max(size.x, size.y, size.z);
    const dist = maxDim * 1.8 + 20;
    gl.camera.position.set(dist, dist, dist);
    gl.camera.lookAt(new THREE.Vector3(0,0,0));
    gl.renderer.render(gl.scene, gl.camera);
    $('#viewerHint').textContent = stl.name;
  }, undefined, (err)=> console.warn('STL load failed', err));
}