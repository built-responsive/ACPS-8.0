<?php
//*********************************************************************//
//     Order Management Console                                        //
//     Extracted from Admin Index                                      //
//*********************************************************************//

require_once("../config.php");

// --- Pending Cash Orders Scan (today only) --------------------------
$pendingCashOrders = [];
$cashScanDebug     = [];
// --- Load Auto Print Status --------------------------
// Path relative from admin/console/ to config/
$autoprintStatusPath = realpath(__DIR__ . "/../../config/autoprint_status.txt");
$initialAutoPrint = '1'; // Default to ON

if ($autoprintStatusPath !== false && file_exists($autoprintStatusPath)) {
    $content = @file_get_contents($autoprintStatusPath);
    if ($content !== false) {
        $initialAutoPrint = trim($content) === '0' ? '0' : '1';
    }
}
// -----------------------------------------------------

$pendingCashCount = count($pendingCashOrders);
try {
    // Path relative from admin/console/ to photos/
    $baseDir = realpath(__DIR__ . "/../../photos");

    if ($baseDir === false) {
        $cashScanDebug[] = "ERROR: Could not resolve baseDir.";
    } else {
        $date_path   = date('Y/m/d');
        $receiptsDir = rtrim($baseDir, '/').'/'.$date_path.'/receipts';

        if (!is_dir($receiptsDir)) {
           // Silent fail or debug
        } else {
            $files = glob($receiptsDir.'/*.txt') ?: [];
            foreach ($files as $receiptFile) {
                $raw = @file_get_contents($receiptFile);
                if ($raw === false || trim($raw) === '') continue;

                $lines = preg_split('/\r\n|\r|\n/', $raw);

                // 1) Look for a CASH ORDER line that ends with DUE
                $isCash        = false;
                $amount        = 0.0;

                foreach ($lines as $line) {
                    $lineTrim = trim($line);
                    if (preg_match('/^CASH ORDER:\s*\$([0-9]+(?:\.[0-9]{2})?)\s+DUE\s*$/i', $lineTrim, $m)) {
                        $isCash = true;
                        $amount = (float)$m[1];
                        break;
                    }
                }

                if (!$isCash) continue;

                // 2) Pull out order number, date, and label
                $orderId   = null;
                $orderDate = '';
                $label     = '';

                foreach ($lines as $line) {
                    $trim = trim($line);

                    if ($orderId === null && preg_match('/^Order (Number|#):\s*(\d+)/i', $trim, $m)) {
                        $orderId = $m[2];
                    }
                    if ($orderDate === '' && preg_match('/^Order Date:\s*(.+)$/i', $trim, $m)) {
                        $orderDate = trim($m[1]);
                    }
                    if ($label === '' && strpos($trim, '@') !== false) {
                        $label = $trim;
                    }
                }

                if ($orderId === null) {
                    $orderId = pathinfo($receiptFile, PATHINFO_FILENAME);
                }

                $pendingCashOrders[] = [
                    'id'    => (int)$orderId,
                    'name'  => $label,
                    'total' => $amount,
                    'date'  => $orderDate,
                ];
            }

            usort($pendingCashOrders, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });
        }
    }
} catch (Throwable $e) {
    $pendingCashOrders = [];
}
$pendingCashCount = count($pendingCashOrders);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Order Management Console</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Adjust paths to point back to admin/importer resources -->
  <link rel="stylesheet" href="/public/assets/importer/css/bootstrap.min.css">
  <link href="/public/assets/importer/css/styles.css" rel="stylesheet">
  
  <style>
  body {
      background-color: #000;
      color: #ccc;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  }
  .container {
      max-width: 100%;
      padding-left: 30px;
      padding-right: 30px;
      margin-top: 20px;
  }

  /* Modal Styles */
  #openProcessOrderModal {
    padding: 10px 16px;
    border-radius: 6px;
    border: 1px solid #444;
    background: #696969;
    color: #fff;
    cursor: pointer;
    box-shadow: inset 0 0 5px rgba(0,0,0,.5);
    transition: background 0.2s;
  }
  #openProcessOrderModal:hover { background:#7a7a7a; }

  /* ... modal styles remain ... */
  
  /* Cash orders widget base */
  #cash-orders-widget {
    margin: 0px 0 30px;
    background: #111;
    border-radius: 8px;
    border: 1px solid #333;
    box-shadow: 0 10px 25px rgba(0,0,0,.45);
    color: #eee;
    overflow: hidden; /* For rounded corners */
  }
  #cash-orders-widget .card-header {
    background: #1a1a1a;
    color: #f5f5f5;
    font-weight: 600;
    font-size: 15px;
    padding: 12px 16px;
    border-bottom: 1px solid #333;
  }
  #cashOrdersTable {
    margin-bottom: 0;
    width: 100%;
    border-collapse: collapse;
  }
  #cashOrdersTable th {
    background: #222;
    color: #aaa;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #444;
    padding: 12px 15px;
    font-weight: 600;
    text-align: left;
  }
  #cashOrdersTable td {
    vertical-align: middle;
    font-size: 14px;
    padding: 12px 15px;
    border-bottom: 1px solid #2a2a2a;
    color: #ddd;
    text-align: left;
  }
  #cashOrdersTable tr:last-child td {
    border-bottom: none;
  }
  #cashOrdersTable tr:hover td {
    background-color: #1a1a1a;
  }
  
  .cash-order-actions {
      display: flex;
      gap: 6px;
  }
  .cash-order-actions button {
    padding: 6px 12px;
    font-size: 12px;
    line-height: 1.4;
    border-radius: 4px;
    border: 1px solid transparent;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    color: #fff;
    white-space: nowrap;
  }
  
  .cash-order-actions button[data-action="paid"] {
    background-color: #28a745;
    border-color: #28a745;
  }
  .cash-order-actions button[data-action="paid"]:hover {
    background-color: #218838;
    border-color: #1e7e34;
  }

  .cash-order-actions button[data-action="void"] {
    background-color: #dc3545;
    border-color: #dc3545;
  }
  .cash-order-actions button[data-action="void"]:hover {
    background-color: #c82333;
    border-color: #bd2130;
  }

  .cash-order-actions button[data-action="square"] {
    background-color: #007bff;
    border-color: #007bff;
  }
  .cash-order-actions button[data-action="square"]:hover {
    background-color: #0069d9;
    border-color: #0062cc;
  }

  .cash-order-actions button[disabled] {
    opacity: .6;
    cursor: wait;
    pointer-events: none;
  }
  #cashOrdersPager {
    padding: 6px 10px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
  }
  #cashOrdersPager button {
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid #444;
    background: #333;
    color: #eee;
    cursor: pointer;
  }
  #cashOrdersPager button[disabled] {
    opacity: .4;
    cursor: default;
  }
  #cashOrdersStatus {
    font-size: 12px;
    padding: 4px 10px 8px;
    min-height: 18px;
    color: #ccc;
  }
  #cashOrdersStatus.success { color: #5cd65c; }
  #cashOrdersStatus.error  { color: #ff6b6b; }

  /* Header layout / collapse + controls */
  .cash-header-bar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    font-size:13px;
  }
  .cash-header-left {
    display:flex;
    align-items:center;
    gap:8px;
    cursor:pointer;
  }
  .cash-header-title {
    font-weight:600;
  }
  .cash-count-badge {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:26px;
    padding:2px 8px;
    border-radius:16px;
    background:linear-gradient(135deg,#2a2a2a,#101010);
    border:1px solid #444;
    font-size:11px;
    color:#d6ffd6;
  }
  .cash-toggle-icon {
    font-size:16px;
    margin-left:4px;
    color:#bbb;
  }
  .cash-header-actions {
    display:flex;
    align-items:center;
    gap:10px;
    font-size:11px;
  }

  /* Auto Print pill */
  .auto-print-wrap {
    display:flex;
    align-items:center;
    gap:6px;
    color:#aaa;
  }
  .auto-print-label {
    text-transform:uppercase;
    letter-spacing:.09em;
    font-size:10px;
  }
  #autoPrintToggle {
    position:relative;
    width:66px;
    height:24px;
    border-radius:999px;
    border:1px solid #4a4a4a;
    background:radial-gradient(circle at 20% 0%,#2b2b2b,#101010);
    box-shadow:inset 0 0 0 1px rgba(0,0,0,.8);
    cursor:pointer;
    padding:0;
    outline:none;
    display:inline-flex;
    align-items:center;
    justify-content:flex-start;
    transition:background .18s ease,border-color .18s ease,box-shadow .18s ease;
  }
  #autoPrintToggle .auto-print-knob {
    position:absolute;
    top:3px;
    left:3px;
    width:18px;
    height:18px;
    border-radius:999px;
    background:linear-gradient(145deg,#f8f8f8,#b5b5b5);
    box-shadow:0 1px 2px rgba(0,0,0,.85);
    transition:transform .18s ease;
  }
  #autoPrintToggle .auto-print-text {
    width:100%;
    display:flex;
    justify-content:space-between;
    padding:0 9px 0 20px;
    font-size:9px;
    font-weight:600;
    text-transform:uppercase;
  }
  #autoPrintToggle .auto-print-on {
    color:#3ba85c;
    opacity:.2;
  }
  #autoPrintToggle .auto-print-off {
    color:#d35454;
    opacity:.9;
  }
  #autoPrintToggle.is-on {
    border-color:#1b6b3a;
    background:radial-gradient(circle at 10% 0%,#1f402c,#050b06);
    box-shadow:0 0 0 1px rgba(55,189,108,.35),0 0 12px rgba(55,189,108,.45);
  }
  #autoPrintToggle.is-on .auto-print-knob {
    transform:translateX(38px);
  }
  #autoPrintToggle.is-on .auto-print-on { opacity:.95; }
  #autoPrintToggle.is-on .auto-print-off { opacity:.2; }

  /* Refresh spinner + countdown */
  .refresh-wrap {
    display:flex;
    align-items:center;
    gap:4px;
  }
  #cashRefreshBtn {
    position:relative;
    width:28px;
    height:28px;
    border-radius:999px;
    border:1px solid #444;
    background:radial-gradient(circle at 30% 0%,#252525,#050505);
    cursor:pointer;
    padding:0;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
  }
  #cashRefreshBtn::before {
    content:"";
    position:absolute;
    inset:3px;
    border-radius:50%;
    border:2px solid rgba(120,200,255,.55);
    border-top-color:transparent;
    border-left-color:transparent;
    animation:cash-spin 1s linear infinite;
  }
  #cashRefreshBtn.is-paused::before {
    animation-play-state:paused;
    opacity:.25;
  }
  #refreshCountdown {
    position:relative;
    z-index:1;
    font-size:10px;
    color:#d0eaff;
    text-shadow:0 0 4px rgba(0,0,0,.9);
  }
  @keyframes cash-spin {
    to { transform:rotate(360deg); }
  }

  /* Log button */
  .cash-log-btn {
    border-radius:999px;
    border:1px solid #444;
    background:#191919;
    color:#e7e7e7;
    padding:4px 10px;
    font-size:11px;
    display:flex;
    align-items:center;
    gap:6px;
    cursor:pointer;
  }
  .cash-log-btn span.icon { font-size:13px; }
  .cash-log-btn:hover {
    background:#242424;
    border-color:#4f4f4f;
  }

  /* Log modal */
  #cashLogModal {
    position:fixed;
    inset:0;
    display:none;
    z-index:1080;
  }
  #cashLogModal.is-open { display:block; }
  .cashlog-backdrop {
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.7);
  }
  .cashlog-dialog {
    position:relative;
    margin:7vh auto 0;
    width:min(900px,96vw);
    max-height:80vh;
    background:#050505;
    border-radius:12px;
    border:1px solid #333;
    box-shadow:0 20px 45px rgba(0,0,0,.9);
    padding:14px 16px 12px;
    color:#d5d5d5;
    font-family:SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
    font-size:11px;
  }
  .cashlog-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding-bottom:6px;
    border-bottom:1px solid #222;
  }
  .cashlog-header-title {
    font-size:12px;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#9fe79f;
  }
  .cashlog-close {
    border:0;
    background:transparent;
    color:#aaa;
    font-size:18px;
    cursor:pointer;
  }
  .cashlog-close:hover { color:#fff; }
  .cashlog-body {
    margin-top:8px;
    max-height:64vh;
    overflow:auto;
    background:#000;
    padding:8px 10px;
    border-radius:8px;
    border:1px solid #202020;
  }
  .cashlog-line {
    white-space:pre;
    padding:1px 0;
  }
  .cashlog-line.cash-paid        { color:#8aff8a; }
  .cashlog-line.cash-void        { color:#ff8787; }
  .cashlog-line.cash-email-ok    { color:#79dfff; }
  .cashlog-line.cash-email-error { color:#ffc184; }
  .cashlog-statusbar {
    margin-top:6px;
    font-size:10px;
    color:#888;
  }
  
  .manual-print-btn {
      display: block;
      margin-bottom: 20px;
      text-align: right;
  }
  </style>

</head>

<body>

  <main role="main" class="container">

    <div align="center" style="margin-bottom: 30px;">
      <p>
        <img src="/public/assets/images/alley_admin_header.png" width="550" height="169" alt="Administration Header" style="zoom: .70;" />
      </p>
      <h4 style="color: #aaa; letter-spacing: 2px; text-transform: uppercase;">Order Management Console</h4>
    </div>

    <div class="manual-print-btn">
       <button id="openProcessOrderModal">🖨️ Manual Print / Process Order</button>
    </div>

      <div id="cash-orders-widget" class="card text-left">
        <div class="card-header cash-orders-toggle">
          <div class="cash-header-bar">
            <div class="cash-header-left" id="cashHeaderClickRegion">
              <span class="cash-header-title">💵 Pending Cash Orders</span>
              <span id="cashOrdersCount" class="cash-count-badge">
                <?php echo (int)$pendingCashCount; ?>
              </span>
              <span id="cashOrdersToggleIcon" class="cash-toggle-icon" aria-hidden="true">−</span>
            </div>

            <div class="cash-header-actions">
              <div class="auto-print-wrap">
                <span class="auto-print-label">Auto Print</span>
                <button type="button"
                        id="autoPrintToggle"
                        class="auto-print-toggle"
                        aria-pressed="false">
                  <span class="auto-print-knob"></span>
                  <span class="auto-print-text">
                    <span class="auto-print-off">OFF</span>
                    <span class="auto-print-on">ON</span>
                  </span>
                </button>
              </div>

              <div class="refresh-wrap">
                <button type="button"
                        id="cashRefreshBtn"
                        title="Toggle auto refresh">
                  <span id="refreshCountdown">60</span>
                </button>
              </div>

              <button type="button"
                      id="cashLogBtn"
                      class="cash-log-btn"
                      title="View cash order log">
                <span class="icon">⌘</span>
                <span>Log</span>
              </button>
            </div>
          </div>
        </div>

        <div id="cashOrdersPanel">
          <div class="table-responsive">
            <table id="cashOrdersTable" class="table table-dark table-striped table-sm mb-0">
              <thead>
                <tr>
                  <th scope="col">Order #</th>
                  <th scope="col">Name</th>
                  <th scope="col">Total</th>
                  <th scope="col">Date</th>
                  <th scope="col" style="width: 160px;">Actions</th>
                </tr>
              </thead>
              <tbody id="cashOrdersBody">
                <tr>
                  <td colspan="5" class="text-center text-muted">
                    <?php echo $pendingCashCount ? 'Loading pending cash orders…' : 'No pending cash orders.'; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div id="cashOrdersPager">
            <button type="button" id="cashPrevPage">&laquo; Prev</button>
            <span id="cashOrdersPageLabel">Page 1 / 1</span>
            <button type="button" id="cashNextPage">Next &raquo;</button>
          </div>
          <div id="cashOrdersStatus" aria-live="polite"></div>
        </div>
      </div>
      
      <div style="text-align: center; margin-top: 50px; font-size: 12px; color: #555;">
          <a href="../index.php" style="color: #666; text-decoration: none;">&laquo; Back to Import Admin</a>
      </div>

  </main>

  <script src="/public/assets/importer/js/jquery-3.2.1.min.js"></script>
  <script src="/public/assets/importer/js/bootstrap.min.js"></script>

  <!-- Modal for Manual Process -->
  <div id="processOrderModal" class="cemodal" aria-hidden="true">
    <div class="cemodal__backdrop" data-close></div>
    <div class="cemodal__dialog" role="dialog" aria-modal="true" aria-labelledby="processModalTitle">
      <button class="cemodal__close" type="button" title="Close" data-close>&times;</button>
      <h2 id="processModalTitle">Process Cash Order</h2>

      <form id="processOrderForm" novalidate>
        <label for="processOrderInput">Order #</label>
        <input id="processOrderInput" name="order" type="text"
               autocomplete="off" placeholder="e.g. 10005" required />

        <p class="cemodal__hint">Will print the order and send email if required.</p>

        <div class="cemodal__actions">
          <button type="submit" id="processBtn">Start Process</button>
          <button type="button" class="secondary" data-close>Cancel</button>
        </div>

        <div id="processStatus" class="cemodal__status" aria-live="polite"></div>
        <div id="processSpinner" style="display:none;text-align:center;">
          <img src="/public/assets/images/loader.gif" width="80" height="80" alt="Loading..." />
        </div>
        <div id="processResult" style="margin-top:10px;max-height:220px;overflow:auto;font-size:12px;"></div>
      </form>
    </div>
  </div>

  <!-- Modal for Log -->
  <div id="cashLogModal" aria-hidden="true">
    <div class="cashlog-backdrop"></div>
    <div class="cashlog-dialog" role="dialog" aria-modal="true" aria-labelledby="cashLogTitle">
      <div class="cashlog-header">
        <div class="cashlog-header-title" id="cashLogTitle">
          Cash Order Event Log
        </div>
        <button type="button" class="cashlog-close" title="Close log">&times;</button>
      </div>
      <div class="cashlog-body">
        <div class="cashlog-line">Loading…</div>
      </div>
      <div class="cashlog-statusbar">
        <span>Waiting for data…</span>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const btnOpen   = document.getElementById('openProcessOrderModal');
      const modal     = document.getElementById('processOrderModal');
      const form      = document.getElementById('processOrderForm');
      const input     = document.getElementById('processOrderInput');
      const statusRow = document.getElementById('processStatus');
      const resultBox = document.getElementById('processResult');
      const spinner   = document.getElementById('processSpinner');
      const processBtn= document.getElementById('processBtn');

      if (!btnOpen || !modal || !form) return;

      const open = () => {
        modal.classList.add('is-open');
        statusRow.textContent = '';
        statusRow.className   = 'cemodal__status';
        resultBox.innerHTML   = '';
        spinner.style.display = 'none';
        input.value           = '';
        setTimeout(() => input.focus(), 50);
      };
      const close = () => modal.classList.remove('is-open');

      btnOpen.addEventListener('click', e => { e.preventDefault(); open(); });
      modal.addEventListener('click', e => {
        if (e.target.matches('[data-close], .cemodal__backdrop')) close();
      });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
      });

      form.addEventListener('submit', async e => {
        e.preventDefault();
        const order = input.value.trim().replace(/[^0-9]/g, '');
        if (!order) {
          statusRow.textContent = 'Enter a valid order #';
          statusRow.className   = 'cemodal__status error';
          return;
        }

        processBtn.disabled   = true;
        spinner.style.display = 'block';
        statusRow.textContent = 'Fetching receipt & starting print job…';
        statusRow.className   = 'cemodal__status';
        resultBox.innerHTML   = '';

        try {
          // Pointing to parent admin directory
          const printResp = await fetch('../admin_print_order.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({order}).toString()
          });
          const printData = await printResp.json();

          resultBox.innerHTML = printData.receipt || '';

          if (printData.status !== 'success') {
            statusRow.textContent = printData.message || 'Print failed.';
            statusRow.className   = 'cemodal__status error';
            spinner.style.display = 'none';
            processBtn.disabled   = false;
            return;
          }

          statusRow.textContent = 'Print complete. Checking for digital delivery…';
          statusRow.className   = 'cemodal__status success';

          const hasEmail = /digital\s+email/i.test(printData.receipt || '');
          if (hasEmail) {
            statusRow.textContent = 'Digital Email found — sending mailer.php…';
            // Mailer is in root, 2 levels up
            const mailerURL = `${window.location.origin}/mailer.php?order=${encodeURIComponent(order)}`;
            const mailResp  = await fetch(mailerURL, {
              method:'POST',
              headers:{'Accept':'text/plain,*/*'}
            });
            const mailRaw  = await mailResp.text();
            const mailText = mailRaw.replace(/<[^>]*>/g,'');

            if (/Message has been sent/i.test(mailText)) {
              statusRow.textContent = 'Email sent successfully.';
              statusRow.className   = 'cemodal__status success';
            } else {
              statusRow.textContent = 'Email step failed.';
              statusRow.className   = 'cemodal__status error';
            }
          } else {
            statusRow.textContent = 'No digital delivery found.';
            statusRow.className   = 'cemodal__status success';
          }

          spinner.style.display = 'none';
          setTimeout(close, 2500);

        } catch (err) {
          console.error(err);
          statusRow.textContent = 'Network or server error.';
          statusRow.className   = 'cemodal__status error';
          spinner.style.display = 'none';
        } finally {
          processBtn.disabled = false;
        }
      });
    })();
  </script>

<script>
  window.pendingCashOrders = <?php
    echo json_encode($pendingCashOrders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  ?>;
  window.initialAutoPrint = '<?php echo $initialAutoPrint; ?>';
</script>

  <script>
  (function(){
    const PAGE_SIZE = 5;
    const AUTO_REFRESH_SECONDS = 10;

    const bodyEl      = document.getElementById('cashOrdersBody');
    const prevBtn      = document.getElementById('cashPrevPage');
    const nextBtn      = document.getElementById('cashNextPage');
    const pageLabel    = document.getElementById('cashOrdersPageLabel');
    const statusEl     = document.getElementById('cashOrdersStatus');
    const panel        = document.getElementById('cashOrdersPanel');
    const headerClick  = document.getElementById('cashHeaderClickRegion');
    const toggleIcon   = document.getElementById('cashOrdersToggleIcon');
    const countBadgeEl = document.getElementById('cashOrdersCount');

    const autoPrintBtn = document.getElementById('autoPrintToggle');
    const refreshBtn   = document.getElementById('cashRefreshBtn');
    const countdownEl  = document.getElementById('refreshCountdown');
    const logBtn       = document.getElementById('cashLogBtn');

    const logModal     = document.getElementById('cashLogModal');
    const logBackdrop  = logModal ? logModal.querySelector('.cashlog-backdrop') : null;
    const logCloseBtn  = logModal ? logModal.querySelector('.cashlog-close') : null;
    const logBody      = logModal ? logModal.querySelector('.cashlog-body') : null;
    const logStatus    = logModal ? logModal.querySelector('.cashlog-statusbar span') : null;

    if (!bodyEl || !statusEl) return;

    // Orders from PHP
    let orders = (window.pendingCashOrders && Array.isArray(window.pendingCashOrders))
      ? window.pendingCashOrders
      : [];

    let currentPage = 1;

    // --- Collapse behaviour ---
    let panelOpen = true; // Default open for console

    function syncPanel(open) {
      if (!panel || !toggleIcon) return;
      panel.style.display = open ? 'block' : 'none';
      toggleIcon.textContent = open ? '−' : '+';
    }
    syncPanel(panelOpen);

    if (headerClick) {
      headerClick.addEventListener('click', function(){
        panelOpen = !panelOpen;
        syncPanel(panelOpen);
      });
    }

    // --- Auto Print state (localStorage) ---
    let autoPrintOn = true;
    const LS_KEY = 'cashAutoPrint';
    
    // 1. Initialization: Load from PHP first, fallback to localStorage
    try {
        if (window.initialAutoPrint !== undefined) {
            autoPrintOn = window.initialAutoPrint === '1';
        } else {
            const stored = window.localStorage.getItem(LS_KEY);
            if (stored === '0') autoPrintOn = false;
        }
    } catch(e) {}

    function syncAutoPrintUI(){
        if (!autoPrintBtn) return;
        if (autoPrintOn){
            autoPrintBtn.classList.add('is-on');
            autoPrintBtn.setAttribute('aria-pressed','true');
        } else {
            autoPrintBtn.classList.remove('is-on');
            autoPrintBtn.setAttribute('aria-pressed','false');
        }
    }
    syncAutoPrintUI();

    if (autoPrintBtn) {
        autoPrintBtn.addEventListener('click', async function(ev){
            ev.stopPropagation(); // don’t collapse when toggling
            autoPrintOn = !autoPrintOn;
            syncAutoPrintUI();
            
            // 2. NEW: Call the PHP setter script (path adjusted)
            const status = autoPrintOn ? '1' : '0';
            try {
                const resp = await fetch('../admin_set_autoprint.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({status}).toString()
                });
                const data = await resp.json();
                if (data.status === 'success') {
                    console.log(data.message);
                    setStatus('Auto Print: ' + (autoPrintOn ? 'ON' : 'OFF'), 'success');
                } else {
                    throw new Error(data.message || 'Update failed.');
                }
            } catch(e) {
                console.error('Auto Print toggle failed:', e);
                setStatus('Failed to save Auto Print status.', 'error');
                autoPrintOn = !autoPrintOn; 
                syncAutoPrintUI();
            }

            try { window.localStorage.setItem(LS_KEY, autoPrintOn ? '1' : '0'); } catch(e){}
        });
    }
    // --- End Auto Print state ---

    // --- Paging / rendering ---
    function getPageCount(){
      return Math.max(1, Math.ceil(orders.length / PAGE_SIZE));
    }

    function updateCountBadge(){
      if (!countBadgeEl) return;
      countBadgeEl.textContent = orders.length;
    }

    function renderPage(){
      const totalPages = getPageCount();
      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      bodyEl.innerHTML = '';

      if (!orders.length){
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="5" class="text-center text-muted">No pending cash orders.</td>';
        bodyEl.appendChild(tr);
        if (pageLabel) pageLabel.textContent = 'Page 1 / 1';
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        updateCountBadge();
        return;
      }

      const start = (currentPage - 1) * PAGE_SIZE;
      const end  = start + PAGE_SIZE;
      const slice = orders.slice(start, end);

            slice.forEach(order => {
              const tr = document.createElement('tr');
              tr.setAttribute('data-order-id', order.id);
              tr.setAttribute('data-total', order.total);
              tr.innerHTML = `
                <td>${order.id}</td>
                <td>${order.name || ''}</td>
                <td>$${Number(order.total || 0).toFixed(2)}</td>
                <td>${order.date || ''}</td>
                <td class="cash-order-actions">
                  <button type="button" data-action="square">Square</button>
                  <button type="button" data-action="paid">Paid</button>
                  <button type="button" data-action="void">Void</button>
                </td>
              `;
              bodyEl.appendChild(tr);
            });
      if (pageLabel) {
        pageLabel.textContent = 'Page ' + currentPage + ' / ' + totalPages;
      }
      if (prevBtn) prevBtn.disabled = currentPage === 1;
      if (nextBtn) nextBtn.disabled = currentPage === totalPages;

      updateCountBadge();
    }

    function setStatus(msg, type){
      statusEl.textContent = msg || '';
      statusEl.className = '';
      statusEl.id = 'cashOrdersStatus';
      if (type) statusEl.classList.add(type);
    }

    // --- Auto refresh spinner + countdown ---
    let autoRefreshEnabled = true;
    let refreshTimer = null;
    let refreshRemaining = AUTO_REFRESH_SECONDS;

    function stopRefreshTimer() {
        clearInterval(refreshTimer);
        refreshBtn.classList.add('is-paused');
    }

    function restartRefreshTimer() {
        if (autoRefreshEnabled) {
            startRefreshTimer();
        }
    }

    function syncRefreshUI(){
      if (!refreshBtn || !countdownEl) return;
      countdownEl.textContent = refreshRemaining;
      if (autoRefreshEnabled) {
        refreshBtn.classList.remove('is-paused');
      } else {
        refreshBtn.classList.add('is-paused');
      }
    }

    function startRefreshTimer(){
      clearInterval(refreshTimer);
      refreshRemaining = AUTO_REFRESH_SECONDS;
      syncRefreshUI();
      if (!autoRefreshEnabled) return;

      refreshTimer = setInterval(function(){
        refreshRemaining--;
        if (refreshRemaining <= 0){
          refreshRemaining = AUTO_REFRESH_SECONDS;
          reloadOrders();
        }
        syncRefreshUI();
      }, 1000);
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', function(ev){
        ev.stopPropagation(); // don’t collapse header
        autoRefreshEnabled = !autoRefreshEnabled;
        if (autoRefreshEnabled){
          startRefreshTimer();
          setStatus('Auto refresh enabled.', 'success');
        } else {
          clearInterval(refreshTimer);
          setStatus('Auto refresh paused.', '');
        }
        syncRefreshUI();
      });
    }

    // Server endpoint for reloading: ../admin_cash_orders_api.php
    async function reloadOrders(){
      try {
        const resp = await fetch('../admin_cash_orders_api.php', {cache:'no-store'});
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const data = await resp.json();
        if (!data || data.status !== 'ok' || !Array.isArray(data.orders)) {
          throw new Error('Invalid response');
        }
        orders = data.orders;
        currentPage = 1;
        renderPage();
        setStatus('Orders refreshed.', 'success');
      } catch (err) {
        console.error(err);
        setStatus('Auto-refresh failed: ' + err.message, 'error');
      }
    }

    // --- Paid / Void actions ---
    function handleAction(action, orderId, button){
      if (!orderId) return;
      if (action === 'void') {
        const ok = window.confirm('Void this cash order?');
        if (!ok) return;
      }

      if (button) button.disabled = true;
      stopRefreshTimer(); 
      setStatus('Working…', '');

      const payload = new URLSearchParams({
        order: orderId,
        action: action,
        autoprint: autoPrintOn ? '1' : '0'
      });

      fetch('../admin_cash_order_action.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: payload.toString()
      })
      .then(r => r.json())
      .then(data => {
        if (data && data.status === 'success') {
          if (action === 'paid' || action === 'void') {
            orders = orders.filter(o => String(o.id) !== String(orderId));
            renderPage();
          }
          setStatus(data.message || 'Order updated.', 'success');
        } else {
          setStatus((data && data.message) || 'Action failed.', 'error');
        }
      })
      .catch(err => {
        console.error(err);
        setStatus('Network or server error.', 'error');
      })
      .finally(() => {
          restartRefreshTimer();
        if (button) button.disabled = false;
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function(){
        if (currentPage > 1){
          currentPage--;
          renderPage();
        }
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function(){
        if (currentPage < getPageCount()){
          currentPage++;
          renderPage();
        }
      });
    }

        if (bodyEl) {

          bodyEl.addEventListener('click', function(e){

            const btn = e.target.closest('button[data-action]');

            if (!btn) return;

            const action  = btn.getAttribute('data-action');

            const row     = btn.closest('tr');

            const orderId = row && row.getAttribute('data-order-id');

            const total   = row && row.getAttribute('data-total');

    

            if (action === 'paid' || action === 'void') {

              handleAction(action, orderId, btn);

            } else if (action === 'square') {

              handleSquare(orderId, total, btn);

            }

          });

        }

    

        // --- Square Terminal Logic ---

        async function handleSquare(orderId, amount, button) {

            if (!orderId || !amount) return;

            

            button.disabled = true;

            stopRefreshTimer();

            const originalText = button.textContent;

            button.textContent = "Sending...";

            setStatus('Initiating Square Terminal checkout...', '');

    

            try {

                // Step 1: Create Checkout

                const createResp = await fetch('../admin_square_terminal.php', {

                    method: 'POST',

                    headers: {'Content-Type':'application/x-www-form-urlencoded'},

                    body: new URLSearchParams({

                        action: 'create',

                        order_id: orderId,

                        amount: amount

                    })

                });

                const createData = await createResp.json();

    

                if (createData.status !== 'success') {

                    throw new Error(createData.message || 'Failed to create checkout');

                }

    

                const checkoutId = createData.checkout_id;

                button.textContent = "Waiting...";

                setStatus('Sent to Terminal. Please pay on device.', 'success');

    

                // Step 2: Poll for status

                // Poll every 2 seconds for up to 5 minutes (150 attempts)

                let attempts = 0;

                const maxAttempts = 150; 

                

                const pollInterval = setInterval(async () => {

                    attempts++;

                    try {

                        const pollResp = await fetch('../admin_square_terminal.php', {

                            method: 'POST',

                            headers: {'Content-Type':'application/x-www-form-urlencoded'},

                            body: new URLSearchParams({

                                action: 'poll',

                                checkout_id: checkoutId

                            })

                        });

                        const pollData = await pollResp.json();

                        

                        if (pollData.status !== 'success') {

                             // API error during polling

                             console.warn("Polling error:", pollData.message);

                             return; 

                        }

    

                        const status = pollData.terminal_status; // PENDING, IN_PROGRESS, CANCEL_REQUESTED, CANCELED, COMPLETED

                        

                        if (status === 'COMPLETED') {

                            clearInterval(pollInterval);

                            setStatus('Payment Successful! Marking as Paid...', 'success');

                            button.textContent = "Success!";

                            // Automatically trigger 'paid' action

                            handleAction('paid', orderId, null); // Pass null for button since we handled UI

                        } else if (status === 'CANCELED' || status === 'FAILED') {

                            clearInterval(pollInterval);

                            setStatus('Payment Canceled or Failed.', 'error');

                            button.textContent = "Failed";

                            setTimeout(() => {

                                button.disabled = false;

                                button.textContent = originalText;

                                restartRefreshTimer();

                            }, 3000);

                        } else {

                            // Still pending/in-progress

                            button.textContent = "Waiting... " + attempts;

                            if (attempts >= maxAttempts) {

                                clearInterval(pollInterval);

                                setStatus('Polling timed out.', 'error');

                                button.disabled = false;

                                button.textContent = originalText;

                                restartRefreshTimer();

                            }

                        }

    

                    } catch (err) {

                        console.error("Polling network error", err);

                    }

                }, 2000);

    

            } catch (err) {

                console.error(err);

                setStatus('Square Error: ' + err.message, 'error');

                button.disabled = false;

                button.textContent = originalText;

                restartRefreshTimer();

            }

        }

    // --- Log modal wiring ---
    function openLogModal(){
      if (!logModal) return;
      logModal.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      loadLog();
    }
    function closeLogModal(){
      if (!logModal) return;
      logModal.classList.remove('is-open');
      document.body.style.overflow = '';
    }
    async function loadLog(){
      if (!logBody) return;
      logBody.innerHTML = '<div class="cashlog-line">Loading log…</div>';
      if (logStatus) logStatus.textContent = 'Fetching latest events…';

      try {
        // Pointing to parent admin endpoint
        const resp = await fetch('../admin_cash_order_log.php?action=view', {cache:'no-store'});
        const text = await resp.text();
        const lines = text.split(/\r?\n/);
        logBody.innerHTML = '';

        lines.forEach(line => {
          if (!line.trim()) return;
          const div = document.createElement('div');
          div.className = 'cashlog-line';
          if (/\bPAID\b/.test(line))             div.classList.add('cash-paid');
          else if (/\bVOID\b/.test(line))        div.classList.add('cash-void');
          else if (/\bEMAIL_OK\b/.test(line))    div.classList.add('cash-email-ok');
          else if (/\bEMAIL_ERROR\b/.test(line)) div.classList.add('cash-email-error');
          div.textContent = line;
          logBody.appendChild(div);
        });

        if (logStatus) logStatus.textContent = 'Showing most recent entries.';
      } catch (err) {
        console.error(err);
        logBody.innerHTML = '<div class="cashlog-line cash-email-error">Failed to load log: ' + err.message + '</div>';
        if (logStatus) logStatus.textContent = 'Unable to read log file.';
      }
    }

    if (logBtn) {
      logBtn.addEventListener('click', function(ev){
        ev.stopPropagation();
        openLogModal();
      });
    }
    if (logBackdrop) {
      logBackdrop.addEventListener('click', closeLogModal);
    }
    if (logCloseBtn) {
      logCloseBtn.addEventListener('click', closeLogModal);
    }
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && logModal && logModal.classList.contains('is-open')) {
        closeLogModal();
      }
    });

    // initial render + timer
    renderPage();
    startRefreshTimer();
  })();
  </script>

</body>
</html>
