// ===== Helpers =====
const $ = (s)=>document.querySelector(s);
const currencyCode = (IQ_CFG.currency || '£') === '£' ? 'GBP' : 'USD';
const fmt = (x)=>new Intl.NumberFormat(undefined,{style:'currency',currency: currencyCode}).format(x);

// ===== State =====
let quoteRef = null;
let uploaded = []; // {name,size,url?}

// ===== Uploads (click + drag-drop) =====
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
  uploaded.push(...data.files);          // expects {name,size, [url]}
  renderUploads();
  tryPreviewSTL();                       // will preview if any uploaded file has .url and .stl extension
}

function renderUploads(){
  const lines = uploaded.map(f=>{
    const kb = Math.max(1, Math.round((f.size||0)/1024));
    return `${f.name} (${kb} KB)`;
  });
  $('#uploads').textContent = lines.join('\n');
}

// ===== Quote compute =====
$('#quoteBtn').addEventListener('click', async ()=>{
  const payload = {
    material_id: parseInt($('#material').value || '1'),
    color_id: parseInt($('#color').value || '1'),
    part_weight_g: parseFloat($('#weight').value || '0'),
    quantity: parseInt($('#quantity').value || '1'),
    cavities: parseInt($('#cavities').value || '1'),
    operation_ids: Array.from($('#ops').selectedOptions).map(o=>parseInt(o.value))
  };
  const r = await fetch('/api/quote', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
  const d = await r.json();
  if (!d.ok) { alert(d.error || 'Quote failed'); return; }
  quoteRef = d.quote_ref;
  renderBreaks(d.breaks);
  $('#yesBtn').disabled = false;
});

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
      <div>Extended: ${fmt(b.extended)}</div>
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

// ===== STL preview (legacy global THREE + THREE.STLLoader) =====
// Requires /api/upload to include a public url in each file object:
// return ['name'=>$info['name'],'size'=>$info['size'],'url'=>'/uploads/'.$basename];
let gl = { renderer:null, scene:null, camera:null, loader:null, mesh:null };

function tryPreviewSTL(){
  const stl = uploaded.find(f => f.url && f.name.toLowerCase().endsWith('.stl'));
  if (!stl) return;

  const canvas = $('#stlCanvas');
  if (!gl.renderer){
    gl.renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
    gl.renderer.setPixelRatio(Math.min(2, window.devicePixelRatio||1));
    gl.scene = new THREE.Scene();
    gl.scene.background = new THREE.Color(0x0f1016);
    const light = new THREE.DirectionalLight(0xffffff, 1); light.position.set(1,1,1);
    const amb = new THREE.AmbientLight(0xffffff, 0.5);
    gl.scene.add(light, amb);

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
    window.addEventListener('resize', onResize);
    onResize();
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

    // Frame camera
    const maxDim = Math.max(size.x, size.y, size.z);
    const dist = maxDim * 1.8 + 20;
    gl.camera.position.set(dist, dist, dist);
    gl.camera.lookAt(new THREE.Vector3(0,0,0));

    gl.renderer.render(gl.scene, gl.camera);
    $('#viewerHint').textContent = stl.name;
  }, undefined, (err)=> { console.warn('STL load failed', err); });
}