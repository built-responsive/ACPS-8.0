<?php
//*********************************************************************//
// AlleyCat Litterbox : Printer Test App (Admin)
// Location: /admin/litterbox/index.php
// Aligns with: /admin/admin_print_order.php behaviors + directory gating
//*********************************************************************//

declare(strict_types=1);

require_once(__DIR__ . "/../config.php");

// NOTE: UI requests HTML unless action=...
$action = $_GET['action'] ?? '';

// -------------------------------------------------
// Helpers (MATCH admin_print_order.php naming/behavior)
// -------------------------------------------------
function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

/**
 * Directory is considered "clear" if:
 *  - No files exist in root
 *  - Only directories allowed are Archive and Pending
 */
function ordersDirIsClear(string $dir): bool {
    if (!is_dir($dir)) return false;

    foreach (new DirectoryIterator($dir) as $entry) {
        if ($entry->isDot()) continue;

        $name = $entry->getFilename();

        // Ignore Windows noise
        if ($name === 'Thumbs.db' || $name === 'desktop.ini') continue;

        if ($entry->isFile()) {
            return false;
        }

        if ($entry->isDir()) {
            if ($name !== 'Archive' && $name !== 'Pending') {
                return false;
            }
        }
    }
    return true;
}

/**
 * Same retry gate as admin_print_order.php, but returns metadata for UI.
 * Hard refreshes each attempt via clearstatcache.
 *
 * Returns: [outputDir, usedPending(bool), tries(int)]
 */
function chooseOrdersOutputDir(string $ordersRoot, int $maxTries = 5): array {
    ensureDir($ordersRoot);

    $orderOutputDir = $ordersRoot;
    for ($try = 1; $try <= $maxTries; $try++) {
        // HARD refresh check each loop
        clearstatcache(true, $ordersRoot);

        if (ordersDirIsClear($ordersRoot)) {
            $orderOutputDir = $ordersRoot;
            return [$orderOutputDir, false, $try];
        }

        if ($try === $maxTries) {
            $orderOutputDir = rtrim($ordersRoot, "/\\") . "/Pending";
            ensureDir($orderOutputDir);
            return [$orderOutputDir, true, $try];
        }

        sleep(1);
    }

    // safety
    $orderOutputDir = rtrim($ordersRoot, "/\\") . "/Pending";
    ensureDir($orderOutputDir);
    return [$orderOutputDir, true, $maxTries];
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data);
    exit;
}

function sanitize_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $name = trim($name, "._-");
    return $name !== '' ? $name : ('file_' . bin2hex(random_bytes(4)));
}

// -------------------------------------------------
// App storage (under /admin/litterbox)
// -------------------------------------------------
$DATA_DIR   = __DIR__ . '/data';
$UPLOAD_DIR = __DIR__ . '/uploads';
$QUEUE_FILE = $DATA_DIR . '/queue.json';
$LOG_FILE   = $DATA_DIR . '/events.log';

ensureDir($DATA_DIR);
ensureDir($UPLOAD_DIR);

if (!file_exists($QUEUE_FILE)) {
    file_put_contents($QUEUE_FILE, json_encode(['items' => [], 'processed' => 0], JSON_PRETTY_PRINT));
}

function load_queue(string $queueFile): array {
    $raw = @file_get_contents($queueFile);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = ['items' => [], 'processed' => 0];
    if (!isset($data['items']) || !is_array($data['items'])) $data['items'] = [];
    if (!isset($data['processed'])) $data['processed'] = 0;
    return $data;
}

function save_queue(string $queueFile, array $data): void {
    @file_put_contents($queueFile, json_encode($data, JSON_PRETTY_PRINT));
}

function log_event(string $logFile, string $msg): void {
    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
}

$ALLOWED_EXT = ['jpg','jpeg','png','webp'];

// -------------------------------------------------
// Orders root selection (MATCH admin_print_order.php default)
// -------------------------------------------------
$defaultOutputDir = getenv('PRINT_OUTPUT_DIR') ?: "../orders";
$fsOutputDir      = getenv('PRINT_OUTPUT_DIR_FS') ?: "../orders_fs";

$defaultOrdersRoot = $defaultOutputDir;

// Optionally allow UI override for testing (still uses same gate)
function resolve_orders_root(string $defaultRoot, string $override): string {
    $defaultOutputDir = getenv('PRINT_OUTPUT_DIR') ?: "../orders";
    $fsOutputDir      = getenv('PRINT_OUTPUT_DIR_FS') ?: "../orders_fs";

    $override = strtolower(trim($override));
    if ($override === 'c') return $defaultOutputDir;
    if ($override === 'r') return $fsOutputDir;
    return $defaultRoot;
}

// -------------------------------------------------
// API endpoints
// -------------------------------------------------
if ($action === 'enqueue') {
    if (!isset($_FILES['files'])) {
        json_out(['ok' => false, 'error' => 'No files uploaded.'], 400);
    }

    // 'orders' can be: 'default'|'c'|'r'
    $ordersSel = $_POST['orders'] ?? 'default';

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 1;

    $queue = load_queue($QUEUE_FILE);
    $added = [];

    for ($i = 0; $i < $count; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];

        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;

        $safe = sanitize_filename((string)$name);
        $ext  = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) continue;

        $id = bin2hex(random_bytes(8));
        $dest = $UPLOAD_DIR . '/' . $id . '_' . $safe;

        if (@move_uploaded_file($tmp, $dest)) {
            $queue['items'][] = [
                'id' => $id,
                'name' => $safe,
                'path' => $dest,
                'orders' => $ordersSel,     // default/c/r
                'status' => 'queued',
                'added_at' => time(),
                'last_error' => null,
            ];
            $added[] = $safe;
            log_event($LOG_FILE, "ENQUEUE orders={$ordersSel} file={$safe}");
        }
    }

    save_queue($QUEUE_FILE, $queue);
    json_out(['ok' => true, 'added' => $added, 'queue_len' => count($queue['items'])]);
}

if ($action === 'status') {
    $queue = load_queue($QUEUE_FILE);

    $defaultOutputDir = getenv('PRINT_OUTPUT_DIR') ?: "../orders";
    $fsOutputDir      = getenv('PRINT_OUTPUT_DIR_FS') ?: "../orders_fs";

    // Hard refresh checks like you wanted
    clearstatcache(true, $defaultOutputDir);
    clearstatcache(true, $fsOutputDir);

    $cClear = is_dir($defaultOutputDir) ? ordersDirIsClear($defaultOutputDir) : false;
    $rClear = is_dir($fsOutputDir) ? ordersDirIsClear($fsOutputDir) : false;

    $tail = '';
    if (file_exists($LOG_FILE)) {
        $fp = fopen($LOG_FILE, 'rb');
        if ($fp) {
            $size = filesize($LOG_FILE);
            $seek = max(0, $size - 4096);
            fseek($fp, $seek);
            $tail = stream_get_contents($fp) ?: '';
            fclose($fp);
        }
    }

    json_out([
        'ok' => true,
        'queue_len' => count($queue['items']),
        'processed' => (int)$queue['processed'],
        'next' => $queue['items'][0] ?? null,
        'orders' => [
            'default' => $defaultOrdersRoot,
            'C' => ['root' => $defaultOutputDir, 'clear' => $cClear],
            'R' => ['root' => $fsOutputDir, 'clear' => $rClear],
        ],
        'log_tail' => $tail,
        'ts' => time(),
    ]);
}

if ($action === 'process_one') {
    // Sequentially process ONE queued file
    $queue = load_queue($QUEUE_FILE);
    if (count($queue['items']) === 0) {
        json_out(['ok' => true, 'did_work' => false, 'message' => 'Queue empty.']);
    }

    $item = $queue['items'][0];

    if (empty($item['path']) || !file_exists($item['path'])) {
        array_shift($queue['items']);
        save_queue($QUEUE_FILE, $queue);
        log_event($LOG_FILE, "DROP missing file id={$item['id']} name={$item['name']}");
        json_out(['ok' => true, 'did_work' => true, 'message' => 'Dropped missing file.']);
    }

    $ordersSel = $item['orders'] ?? 'default';
    $ordersRoot = resolve_orders_root($defaultOrdersRoot, (string)$ordersSel);

    [$outDir, $usedPending, $tries] = chooseOrdersOutputDir($ordersRoot, 5);

    $baseName = pathinfo($item['name'], PATHINFO_FILENAME);
    $ext      = pathinfo($item['name'], PATHINFO_EXTENSION);
    $stamp    = date('Ymd_His');
    $destName = sprintf("LBX-%s-%s.%s", $stamp, $baseName, $ext);
    $destPath = rtrim($outDir, "/\\") . "/" . $destName;

    // HARD refresh just before copy
    clearstatcache(true, $destPath);

    $copied = @copy($item['path'], $destPath);

    if ($copied) {
        @unlink($item['path']);
        array_shift($queue['items']);
        $queue['processed'] = (int)$queue['processed'] + 1;
        save_queue($QUEUE_FILE, $queue);

        log_event($LOG_FILE, "COPY orders={$ordersSel} file={$item['name']} -> {$destPath} (tries={$tries} pending=" . ($usedPending ? "yes" : "no") . ")");

        json_out([
            'ok' => true,
            'did_work' => true,
            'copied' => true,
            'source' => $item['name'],
            'output' => $outDir,                 // lines up with admin_print_order.php JSON key
            'dest_file' => $destName,
            'used_pending' => $usedPending,
            'tries' => $tries,
        ]);
    }

    $queue['items'][0]['last_error'] = 'copy_failed';
    save_queue($QUEUE_FILE, $queue);

    log_event($LOG_FILE, "ERROR copy_failed file={$item['name']} -> {$destPath}");

    json_out([
        'ok' => false,
        'did_work' => true,
        'copied' => false,
        'error' => 'copy_failed',
        'output' => $outDir,
        'used_pending' => $usedPending,
        'tries' => $tries,
    ], 500);
}

if ($action === 'clear_queue') {
    $queue = load_queue($QUEUE_FILE);

    foreach ($queue['items'] as $it) {
        if (!empty($it['path']) && file_exists($it['path'])) {
            @unlink($it['path']);
        }
    }

    $queue = ['items' => [], 'processed' => 0];
    save_queue($QUEUE_FILE, $queue);
    log_event($LOG_FILE, "QUEUE CLEARED");
    json_out(['ok' => true]);
}

// -------------------------------------------------
// UI (HTML)
// -------------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AlleyCat Litterbox</title>
  <style>
    :root{
      --bg:#0b0f14; --panel:#111827; --panel2:#0f172a; --text:#e5e7eb; --muted:#9ca3af;
      --accent:#f59e0b; --good:#10b981; --warn:#f97316; --bad:#ef4444;
      --border:rgba(255,255,255,.08); --shadow:0 12px 30px rgba(0,0,0,.45); --radius:16px;
      --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
    }
    *{box-sizing:border-box}
    body{
      margin:0;font-family:var(--sans);
      background: radial-gradient(1200px 700px at 20% -10%, rgba(245,158,11,.18), transparent 55%),
                  radial-gradient(900px 700px at 90% 0%, rgba(16,185,129,.10), transparent 45%),
                  var(--bg);
      color:var(--text);
    }
    .wrap{max-width:1100px;margin:0 auto;padding:22px}
    .top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
    .brand{display:flex;align-items:center;gap:12px}
    .logo{
      width:42px;height:42px;border-radius:12px;
      background: linear-gradient(135deg, rgba(245,158,11,.95), rgba(245,158,11,.35));
      box-shadow: var(--shadow);
      display:flex;align-items:center;justify-content:center;
      font-weight:900;color:#111827;user-select:none;
    }
    .title{font-size:18px;font-weight:800;letter-spacing:.2px}
    .subtitle{font-size:12px;color:var(--muted);margin-top:2px}
    .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px}
    .card{
      background: linear-gradient(180deg, rgba(17,24,39,.92), rgba(15,23,42,.92));
      border:1px solid var(--border);border-radius: var(--radius);box-shadow: var(--shadow);overflow:hidden;
    }
    .card h2{
      margin:0;padding:14px 16px;font-size:13px;letter-spacing:.5px;text-transform:uppercase;
      color: rgba(229,231,235,.9);border-bottom:1px solid var(--border);background: rgba(0,0,0,.14);
    }
    .card .body{padding:16px}
    .drop{
      border:2px dashed rgba(245,158,11,.45);border-radius:16px;padding:22px;background: rgba(0,0,0,.18);
      transition:.15s ease;text-align:center;
    }
    .drop.dragover{border-color: rgba(245,158,11,.95);background: rgba(245,158,11,.10);transform: translateY(-1px)}
    .drop strong{display:block;font-size:14px}
    .drop span{display:block;color:var(--muted);font-size:12px;margin-top:6px}
    .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:center;justify-content:space-between}
    .controls{display:flex;gap:10px;flex-wrap:wrap}
    button{
      background: rgba(255,255,255,.08);color: var(--text);border:1px solid var(--border);
      border-radius:12px;padding:10px 12px;cursor:pointer;font-weight:700;
    }
    button.primary{background: rgba(245,158,11,.22);border-color: rgba(245,158,11,.40)}
    button.danger{background: rgba(239,68,68,.18);border-color: rgba(239,68,68,.35)}
    button:disabled{opacity:.45;cursor:not-allowed}
    .mode{display:flex;gap:10px;align-items:center;color:var(--muted);font-size:12px}
    .mode label{cursor:pointer}
    .kpi{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .pill{padding:10px 12px;border-radius:14px;border:1px solid var(--border);background: rgba(0,0,0,.18)}
    .pill .k{color:var(--muted);font-size:11px}
    .pill .v{font-size:14px;font-weight:800;margin-top:2px}
    .state.good{color:var(--good)} .state.warn{color:var(--warn)} .state.bad{color:var(--bad)}
    .mono{font-family:var(--mono)}
    .queue{margin-top:14px;border-top:1px solid var(--border);padding-top:14px}
    .qitem{
      display:flex;justify-content:space-between;gap:10px;padding:10px 10px;border-radius:12px;
      border:1px solid var(--border);background: rgba(0,0,0,.16);margin-bottom:8px;align-items:center;
    }
    .qitem small{color:var(--muted)}
    pre{
      margin:0;max-height:320px;overflow:auto;padding:12px;border-radius:12px;border:1px solid var(--border);
      background: rgba(0,0,0,.25);font-family:var(--mono);font-size:11px;color: rgba(229,231,235,.95);
      white-space:pre-wrap;
    }
    .hint{color:var(--muted);font-size:12px;margin-top:10px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="brand">
      <div class="logo">AC</div>
      <div>
        <div class="title">AlleyCat Litterbox</div>
        <div class="subtitle">Drag-drop queue → sequential copy → orders directory gate (aligned)</div>
      </div>
    </div>
    <div class="controls">
      <button id="btnStart" class="primary">Start Processing</button>
      <button id="btnStop">Stop</button>
      <button id="btnClear" class="danger">Clear Queue</button>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Queue Upload</h2>
      <div class="body">
        <div id="drop" class="drop">
          <strong>Drop images here</strong>
          <span>or click to select (JPG / PNG / WEBP). Processes one file per tick.</span>
          <input id="file" type="file" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none" />
        </div>

        <div class="row">
          <div class="mode">
            <span class="mono">Orders root:</span>
            <label><input type="radio" name="orders" value="default" checked /> Default (<?= htmlspecialchars($defaultOrdersRoot) ?>)</label>
            <label><input type="radio" name="orders" value="c" /> Force <?php echo $defaultOutputDir; ?></label>
            <label><input type="radio" name="orders" value="r" /> Force <?php echo $fsOutputDir; ?></label>
          </div>
          <div class="hint">Default matches admin_print_order.php: environment variables decide root.</div>
        </div>

        <div class="queue">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px">
            <div class="mono" style="font-weight:800">Queued Files</div>
            <small class="mono" id="queueCount">0</small>
          </div>
          <div id="queueList"></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Status Monitor</h2>
      <div class="body">
        <div class="kpi">
          <div class="pill">
            <div class="k">Queue</div>
            <div class="v"><span id="kQueue">0</span> pending</div>
          </div>
          <div class="pill">
            <div class="k">Processed</div>
            <div class="v"><span id="kProcessed">0</span> done</div>
          </div>
          <div class="pill">
            <div class="k"><?php echo $defaultOutputDir; ?></div>
            <div class="v state" id="kCState">unknown</div>
            <div class="k mono"><?php echo $defaultOutputDir; ?></div>
          </div>
          <div class="pill">
            <div class="k"><?php echo $fsOutputDir; ?></div>
            <div class="v state" id="kRState">unknown</div>
            <div class="k mono"><?php echo $fsOutputDir; ?></div>
          </div>
        </div>

        <div style="margin-top:14px">
          <div class="mono" style="font-weight:800;margin-bottom:8px">Event Log</div>
          <pre id="log"></pre>
        </div>

        <div class="hint">
          Each tick does a hard-refresh scan. If root is busy: retry 5× (1s) then drop into <span class="mono">/Pending</span>.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const drop = document.getElementById('drop');
  const fileInput = document.getElementById('file');

  const btnStart = document.getElementById('btnStart');
  const btnStop  = document.getElementById('btnStop');
  const btnClear = document.getElementById('btnClear');

  const queueList  = document.getElementById('queueList');
  const queueCount = document.getElementById('queueCount');

  const kQueue     = document.getElementById('kQueue');
  const kProcessed = document.getElementById('kProcessed');
  const kCState    = document.getElementById('kCState');
  const kRState    = document.getElementById('kRState');
  const logEl      = document.getElementById('log');

  let running = false;

  function ordersValue() {
    const el = document.querySelector('input[name="orders"]:checked');
    return el ? el.value : 'default';
  }

  function setState(el, isClear) {
    el.classList.remove('good','warn','bad');
    if (isClear === true) { el.textContent = 'clear'; el.classList.add('good'); }
    else if (isClear === false) { el.textContent = 'busy'; el.classList.add('warn'); }
    else { el.textContent = 'unknown'; el.classList.add('bad'); }
  }

  async function api(action, opts = {}) {
    const url = `index.php?action=${encodeURIComponent(action)}&_=${Date.now()}`;
    const res = await fetch(url, { cache: "no-store", ...opts });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch { data = { ok:false, error:"bad_json", raw:text }; }
    if (!res.ok) throw data;
    return data;
  }

  async function refreshStatus() {
    try {
      const s = await api('status');
      kQueue.textContent = s.queue_len ?? 0;
      kProcessed.textContent = s.processed ?? 0;

      setState(kCState, s.orders?.C?.clear);
      setState(kRState, s.orders?.R?.clear);

      logEl.textContent = s.log_tail || '';

      // queue preview
      queueList.innerHTML = '';
      const next = s.next;
      if (next) {
        const div = document.createElement('div');
        div.className = 'qitem';
        div.innerHTML = `
          <div>
            <div class="mono" style="font-weight:800">${escapeHtml(next.name || 'file')}</div>
            <small class="mono">orders=${escapeHtml(next.orders || 'default')}</small>
          </div>
          <small class="mono">next</small>
        `;
        queueList.appendChild(div);
      } else {
        queueList.innerHTML = `<div class="mono" style="color:rgba(156,163,175,.9);font-size:12px">No queued files.</div>`;
      }
      queueCount.textContent = String(s.queue_len ?? 0);
    } catch (e) {
      logEl.textContent = `Status error: ${JSON.stringify(e, null, 2)}`;
      setState(kCState, null);
      setState(kRState, null);
    }
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[s]));
  }

  async function enqueueFiles(files) {
    const fd = new FormData();
    for (const f of files) fd.append('files[]', f);
    fd.append('orders', ordersValue());
    await api('enqueue', { method:'POST', body: fd });
    await refreshStatus();
  }

  async function processLoop() {
    if (!running) return;

    try {
      const r = await api('process_one', { method:'POST' });
      await refreshStatus();
      const delay = (r.did_work && r.copied) ? 150 : 750;
      setTimeout(processLoop, delay);
    } catch (e) {
      await refreshStatus();
      setTimeout(processLoop, 900);
    }
  }

  // Drag/drop
  drop.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', async () => {
    if (fileInput.files?.length) {
      await enqueueFiles(fileInput.files);
      fileInput.value = '';
    }
  });

  ['dragenter','dragover'].forEach(ev => {
    drop.addEventListener(ev, (e) => {
      e.preventDefault(); e.stopPropagation();
      drop.classList.add('dragover');
    });
  });
  ['dragleave','drop'].forEach(ev => {
    drop.addEventListener(ev, (e) => {
      e.preventDefault(); e.stopPropagation();
      drop.classList.remove('dragover');
    });
  });
  drop.addEventListener('drop', async (e) => {
    const files = e.dataTransfer?.files;
    if (files?.length) await enqueueFiles(files);
  });

  // Controls
  btnStart.addEventListener('click', async () => {
    running = true;
    btnStart.disabled = true;
    btnStop.disabled = false;
    await refreshStatus();
    processLoop();
  });

  btnStop.addEventListener('click', () => {
    running = false;
    btnStart.disabled = false;
    btnStop.disabled = true;
  });

  btnClear.addEventListener('click', async () => {
    running = false;
    btnStart.disabled = false;
    btnStop.disabled = true;
    await api('clear_queue', { method:'POST' });
    await refreshStatus();
  });

  btnStop.disabled = true;
  setInterval(refreshStatus, 1000);
  refreshStatus();
})();
</script>
</body>
</html>
