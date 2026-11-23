<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bulk Domain Availability Checker</title>
<style>
  :root {
    --bg: #f8fafc; --surface: #ffffff; --border: #e2e8f0;
    --text: #0f172a; --muted: #64748b; --primary: #2563eb; --primary-hover: #1d4ed8;
    --avail-bg: #f0fdf4; --avail-border: #bbf7d0; --avail-text: #15803d;
    --taken-bg: #fef2f2; --taken-border: #fecaca; --taken-text: #b91c1c;
    --chip-bg: #f1f5f9;
  }
  [data-theme="dark"] {
    --bg: #0b1120; --surface: #1e293b; --border: #334155;
    --text: #f1f9; --text: #f1f5f9; --muted: #94a3b8; --primary: #3b82f6; --primary-hover: #60a5fa;
    --avail-bg: rgba(22, 101, 52, 0.22); --avail-border: #166534; --avail-text: #4ade80;
    --taken-bg: rgba(153, 27, 27, 0.22); --taken-border: #991b1b; --taken-text: #f87171;
    --chip-bg: #0f172a;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg); color: var(--text); line-height: 1.5; padding: 24px 16px;
  }
  .container { max-width: 820px; margin: 0 auto; }
  header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  header h1 { font-size: 1.35rem; margin: 0; font-weight: 700; letter-spacing: -0.02em; }
  header p { margin: 2px 0 0; font-size: 0.85rem; color: var(--muted); }
  .icon-btn {
    background: var(--surface); border: 1px solid var(--border); color: var(--text);
    padding: 7px 12px; border-radius: 8px; cursor: pointer; font-size: 0.85rem;
  }
  .card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  }
  textarea {
    width: 100%; min-height: 76px; padding: 12px; border-radius: 8px;
    border: 1px solid var(--border); background: var(--bg); color: var(--text);
    font-size: 0.95rem; font-family: inherit; resize: vertical;
  }
  textarea:focus { outline: 2px solid var(--primary); border-color: transparent; }
  .tld-section { margin-top: 16px; }
  .tld-header { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: var(--muted); margin-bottom: 8px; }
  .tld-actions button {
    background: none; border: none; color: var(--primary); cursor: pointer; font-size: 0.82rem; padding: 0 4px;
  }
  .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
  .chip {
    padding: 5px 11px; border-radius: 999px; font-size: 0.82rem; font-weight: 500;
    border: 1px solid var(--border); background: var(--chip-bg); color: var(--muted);
    cursor: pointer; user-select: none; transition: all 0.12s ease;
  }
  .chip.active {
    background: var(--primary); color: #fff; border-color: var(--primary);
  }
  .custom-tld-row { display: flex; gap: 8px; margin-top: 8px; }
  .custom-tld-row input {
    flex: 1; max-width: 220px; padding: 7px 10px; border-radius: 8px;
    border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.85rem;
  }
  .btn-primary {
    width: 100%; margin-top: 16px; padding: 12px; border: none; border-radius: 8px;
    background: var(--primary); color: #fff; font-size: 0.98rem; font-weight: 600;
    cursor: pointer; transition: background 0.15s;
  }
  .btn-primary:hover { background: var(--primary-hover); }
  .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
  .progress-wrap { height: 4px; background: var(--border); border-radius: 4px; overflow: hidden; margin-top: 14px; display: none; }
  .progress-bar { height: 100%; width: 0%; background: var(--primary); transition: width 0.15s ease; }

  .toolbar {
    display: none; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
    margin: 22px 0 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border);
  }
  .toolbar-left { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; }
  .toolbar-right { display: flex; gap: 8px; }
  .badge-timer {
    background: var(--chip-bg); border: 1px solid var(--border); padding: 2px 9px;
    border-radius: 999px; font-size: 0.78rem; color: var(--muted); font-weight: 600;
  }
  .results { display: flex; flex-direction: column; gap: 8px; }
  .row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--surface); font-size: 0.95rem;
  }
  .row.available { background: var(--avail-bg); border-color: var(--avail-border); }
  .row.taken { background: var(--taken-bg); border-color: var(--taken-border); }
  .domain-name { font-weight: 600; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
  .meta { font-size: 0.75rem; color: var(--muted); margin-left: 8px; }
  .status-pill { font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; }
  .available .status-pill { color: var(--avail-text); }
  .taken .status-pill { color: var(--taken-text); }
  .inspect-btn {
    background: var(--surface); border: 1px solid var(--border); color: var(--text);
    font-size: 0.75rem; padding: 3px 8px; border-radius: 6px; cursor: pointer;
  }
  dialog {
    border: 1px solid var(--border); border-radius: 12px; background: var(--surface);
    color: var(--text); max-width: 480px; width: 92%; padding: 20px;
  }
  dialog::backdrop { background: rgba(0,0,0,0.5); }
  .rdap-grid { display: grid; grid-template-columns: 110px 1fr; gap: 8px; font-size: 0.88rem; margin: 14px 0; }
  .rdap-label { color: var(--muted); font-weight: 600; }
</style>
</head>
<body>
<div class="container">
  <header>
    <div>
      <h1>Bulk Domain Availability Checker</h1>
      <p>Zero ads. Zero tracking. Direct RDAP &amp; DNS lookup.</p>
    </div>
    <button class="icon-btn" id="themeToggle" title="Toggle Dark/Light Mode">Theme</button>
  </header>

  <div class="card">
    <textarea id="domainInput" placeholder="Enter keywords or full domains (one per line or comma-separated)&#10;Example: thehsclub, mysideproject"></textarea>

    <div class="tld-section">
      <div class="tld-header">
        <span>Extensions to append to keywords:</span>
        <div class="tld-actions">
          <button type="button" onclick="selectGroup('Popular')">Popular</button> ·
          <button type="button" onclick="selectAll(true)">All</button> ·
          <button type="button" onclick="selectAll(false)">None</button>
        </div>
      </div>
      <div class="chips" id="tldChips"></div>
      <div class="custom-tld-row">
        <input type="text" id="customTld" placeholder="Add custom TLD (e.g. club, store)">
        <button type="button" class="icon-btn" onclick="addCustomTld()">+ Add</button>
      </div>
    </div>

    <button class="btn-primary" id="checkBtn" onclick="startCheck()">Check Availability</button>
    <div class="progress-wrap" id="progressWrap"><div class="progress-bar" id="progressBar"></div></div>
  </div>

  <div class="toolbar" id="toolbar">
    <div class="toolbar-left">
      <strong>Results (<span id="countText">0/0</span>)</strong>
      <span class="badge-timer" id="timerBadge">0.00s</span>
      <label style="font-size:0.83rem; cursor:pointer;">
        <input type="checkbox" id="availOnly" onchange="applyFilter()"> Available only
      </label>
    </div>
    <div class="toolbar-right">
      <button class="icon-btn" onclick="copyAvailable()">Copy Available</button>
      <button class="icon-btn" onclick="exportCsv()">Export CSV</button>
    </div>
  </div>

  <div class="results" id="results"></div>
</div>

<dialog id="rdapModal">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <strong id="modalTitle">Domain Details</strong>
    <button class="icon-btn" onclick="document.getElementById('rdapModal').close()">Close</button>
  </div>
  <div id="modalBody" style="margin-top:12px; font-size:0.9rem;">Loading RDAP data...</div>
</dialog>

<script>
let tldGroups = {
  Popular:  ['com', 'net', 'org', 'co', 'io', 'ai', 'app', 'dev', 'xyz', 'me'],
  Regional: ['in', 'co.in', 'org.in', 'uk', 'co.uk', 'ca', 'de', 'eu'],
  Tech:     ['tech', 'cloud', 'software', 'digital', 'systems', 'code', 'site']
};
let selectedTlds = new Set(tldGroups.Popular);
let allResults = [];

// Theme initialization
const savedTheme = localStorage.getItem('bdc_theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);
document.getElementById('themeToggle').onclick = () => {
  const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('bdc_theme', next);
};

// Render default TLD chips immediately, then sync with api.php if customized
renderChips();
fetch('api.php?action=config')
  .then(r => r.json())
  .then(data => {
    if (data && data.tlds) {
      tldGroups = data.tlds;
      renderChips();
    }
  })
  .catch(() => {});

function renderChips() {
  const container = document.getElementById('tldChips');
  container.innerHTML = '';
  const all = new Set([...Object.values(tldGroups).flat(), ...selectedTlds]);
  all.forEach(tld => {
    const el = document.createElement('span');
    el.className = 'chip' + (selectedTlds.has(tld) ? ' active' : '');
    el.textContent = '.' + tld;
    el.onclick = () => {
      selectedTlds.has(tld) ? selectedTlds.delete(tld) : selectedTlds.add(tld);
      renderChips();
    };
    container.appendChild(el);
  });
}

function selectGroup(name) {
  selectedTlds = new Set(tldGroups[name] || []);
  renderChips();
}

function selectAll(flag) {
  selectedTlds = flag ? new Set(Object.values(tldGroups).flat()) : new Set();
  renderChips();
}

function addCustomTld() {
  const input = document.getElementById('customTld');
  const val = input.value.trim().toLowerCase().replace(/^\.+/, '').replace(/[^a-z0-9.]/g, '');
  if (val) {
    selectedTlds.add(val);
    input.value = '';
    renderChips();
  }
}

function buildDomainQueue() {
  const raw = document.getElementById('domainInput').value;
  const tokens = raw.split(/[\n,;\s]+/).map(s => s.trim().toLowerCase()).filter(Boolean);
  const queue = new Set();

  tokens.forEach(token => {
    token = token.replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, '');
    if (!token) return;
    if (token.includes('.')) {
      queue.add(token);
    } else {
      const clean = token.replace(/[^a-z0-9-]/g, '');
      if (clean) {
        selectedTlds.forEach(tld => queue.add(`${clean}.${tld}`));
      }
    }
  });
  return Array.from(queue);
}

async function startCheck() {
  const queue = buildDomainQueue();
  if (!queue.length) {
    alert('Please enter at least one keyword (with a TLD selected) or a full domain name.');
    return;
  }

  const btn = document.getElementById('checkBtn');
  const resultsEl = document.getElementById('results');
  const toolbar = document.getElementById('toolbar');
  const progressWrap = document.getElementById('progressWrap');
  const progressBar = document.getElementById('progressBar');

  btn.disabled = true;
  resultsEl.innerHTML = '';
  toolbar.style.display = 'flex';
  progressWrap.style.display = 'block';
  progressBar.style.width = '0%';
  allResults = [];

  const startTime = performance.now();
  let completed = 0;
  const concurrency = 6;

  queue.forEach(domain => {
    const row = document.createElement('div');
    row.className = 'row';
    row.id = 'row-' + btoa(domain).replace(/=/g, '');
    row.innerHTML = `<span class="domain-name">${domain}</span><span class="status-pill" style="color:var(--muted)">Checking...</span>`;
    resultsEl.appendChild(row);
  });

  async function worker(items) {
    while (items.length) {
      const domain = items.shift();
      try {
        const res = await fetch(`api.php?action=check&domain=${encodeURIComponent(domain)}`);
        const data = await res.json();
        allResults.push(data);
        renderRow(data);
      } catch (e) {
        renderRow({ domain, status: 'error', ms: 0 });
      }
      completed++;
      progressBar.style.width = `${Math.round((completed / queue.length) * 100)}%`;
      document.getElementById('countText').textContent = `${completed}/${queue.length}`;
      document.getElementById('timerBadge').textContent = `${((performance.now() - startTime) / 1000).toFixed(2)}s`;
    }
  }

  const pool = [...queue];
  await Promise.all(Array.from({ length: Math.min(concurrency, queue.length) }, () => worker(pool)));
  btn.disabled = false;
  applyFilter();
}

function renderRow(data) {
  const row = document.getElementById('row-' + btoa(data.domain).replace(/=/g, ''));
  if (!row) return;
  row.className = `row ${data.status}`;
  row.dataset.status = data.status;

  let label = 'Error';
  if (data.status === 'available') label = '✓ Available';
  if (data.status === 'taken') label = '✕ Taken';

  const inspectBtn = data.status === 'taken'
    ? `<button class="inspect-btn" onclick="inspectDomain('${data.domain}')">RDAP</button>`
    : '';

  row.innerHTML = `
    <div>
      <span class="domain-name">${data.domain}</span>
      <span class="meta">${data.method || ''} · ${data.ms || 0}ms${data.cached ? ' (cached)' : ''}</span>
    </div>
    <div class="status-pill">${inspectBtn}<span>${label}</span></div>
  `;
}

function applyFilter() {
  const onlyAvail = document.getElementById('availOnly').checked;
  document.querySelectorAll('.results .row').forEach(row => {
    row.style.display = (onlyAvail && row.dataset.status !== 'available') ? 'none' : 'flex';
  });
}

function copyAvailable() {
  const avail = allResults.filter(r => r.status === 'available').map(r => r.domain);
  if (!avail.length) return alert('No available domains to copy.');
  navigator.clipboard.writeText(avail.join('\n'));
  alert(`Copied ${avail.length} available domain(s) to clipboard.`);
}

function exportCsv() {
  if (!allResults.length) return;
  const rows = [['Domain', 'Status', 'Engine', 'Response_ms']];
  allResults.forEach(r => rows.push([r.domain, r.status, r.method || '', r.ms || 0]));
  const csv = rows.map(r => r.join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'domain-check-results.csv';
  a.click();
}

async function inspectDomain(domain) {
  const modal = document.getElementById('rdapModal');
  const title = document.getElementById('modalTitle');
  const body = document.getElementById('modalBody');
  title.textContent = domain;
  body.textContent = 'Fetching RDAP record...';
  modal.showModal();

  const res = await fetch(`api.php?action=inspect&domain=${encodeURIComponent(domain)}`);
  const d = await res.json();
  if (d.error) {
    body.textContent = d.error;
    return;
  }
  body.innerHTML = `
    <div class="rdap-grid">
      <div class="rdap-label">Registrar</div><div>${d.registrar || d.source || 'N/A'}</div>
      <div class="rdap-label">Registered</div><div>${d.registered || 'N/A'}</div>
      <div class="rdap-label">Expires</div><div>${d.expires || 'N/A'}</div>
      <div class="rdap-label">Updated</div><div>${d.updated || 'N/A'}</div>
      <div class="rdap-label">Nameservers</div><div>${(d.nameservers || []).join('<br>') || 'N/A'}</div>
    </div>
  `;
}
</script>
</body>
</html>