<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$perm = (int)($user['permissions'] ?? 0);
$current = 'console';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Konsole | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .console-output {
            background: #1a1a1a; color: #d4d4d4;
            font-family: 'Courier New', Consolas, monospace;
            font-size: 12px; padding: 10px;
            height: 500px; overflow-y: auto;
            border: 1px solid #333; line-height: 1.5;
            white-space: pre-wrap; word-break: break-all;
        }
        .log-warn    { color: #dcdcaa; }
        .log-error   { color: #f44747; }
        .log-success { color: #4ec9b0; }
        .log-info    { color: #9cdcfe; }
        .console-input-row { display:flex; gap:8px; padding:10px 0 0; }
        .console-input-row input { flex:1; font-family:'Courier New',monospace; font-size:13px; }
        .log-status { font-size:11px; color:#888; padding:4px 0; }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2>Konsole</h2>
            <div>
                <span class="log-status" id="log-status">Verbinde…</span>
                <label style="font-size:12px;margin-left:12px;">
                    <input type="checkbox" id="autoscroll" checked> Auto-Scroll
                </label>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-body" style="padding:12px;">
                <div class="console-output" id="console-output"></div>

                <?php if ($perm >= 3): ?>
                <div class="console-input-row">
                    <input type="text" class="form-control" id="console-cmd"
                           placeholder="Befehl eingeben (z.B. list, say Hallo, op Spieler)…"
                           autocomplete="off" autocorrect="off" spellcheck="false">
                    <button class="btn btn-primary" id="send-btn" onclick="sendCmd()">
                        <i class="fa fa-paper-plane"></i> Senden
                    </button>
                </div>
                <div id="cmd-resp" style="font-size:12px;margin-top:6px;color:#666;min-height:18px;"></div>
                <?php else: ?>
                <div style="padding:8px 0;">
                    <span class="text-muted"><i class="fa fa-lock"></i> Berechtigung 3 für Befehle benötigt.</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">Minecraft Dashboard &copy; <?php echo date('Y'); ?></div>
            <div class="col-md-6 text-right"><a href="#">Datenschutz</a> | <a href="#">Impressum</a></div>
        </div>
    </div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script>
const output    = document.getElementById('console-output');
const statusEl  = document.getElementById('log-status');
const autoEl    = document.getElementById('autoscroll');
const canSend   = <?php echo $perm >= 3 ? 'true' : 'false'; ?>;

let lastLineCount = 0;
const colorMap = { warn: 'log-warn', error: 'log-error', success: 'log-success', info: 'log-info' };

// ── Log Polling (jede Sekunde) ───────────────────────────────────────────────
async function fetchLog() {
    try {
        const r = await fetch('log.php?lines=200');
        const d = await r.json();

        if (!d.ok) {
            statusEl.textContent = 'Fehler: ' + (d.error || 'Unbekannt');
            return;
        }

        // Nur updaten wenn sich Zeilenanzahl geändert hat
        if (d.lines.length === lastLineCount) return;
        lastLineCount = d.lines.length;

        output.innerHTML = '';
        d.lines.forEach(line => {
            const el = document.createElement('div');
            const cls = colorMap[line.type] || 'log-info';
            el.className = cls;
            el.textContent = line.text;
            output.appendChild(el);
        });

        if (autoEl && autoEl.checked) {
            output.scrollTop = output.scrollHeight;
        }

        const last = d.lines[d.lines.length - 1];
        statusEl.textContent = 'Live (' + d.lines.length + ' Zeilen) – ' + new Date().toLocaleTimeString();
    } catch(e) {
        statusEl.textContent = 'Verbindungsfehler';
    }
}

fetchLog();
setInterval(fetchLog, 1000);

// ── Befehl senden via RCON ───────────────────────────────────────────────────
<?php if ($perm >= 3): ?>
const cmdInput = document.getElementById('console-cmd');
const respEl   = document.getElementById('cmd-resp');
const sendBtn  = document.getElementById('send-btn');

async function sendCmd() {
    let cmd = cmdInput.value.trim();
    if (!cmd) return;
    // Führendes / entfernen (RCON braucht es nicht)
    if (cmd.startsWith('/')) cmd = cmd.slice(1);

    sendBtn.disabled = true;
    respEl.style.color = '#888';
    respEl.textContent = 'Sende…';

    try {
        const r = await fetch('rcon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cmd })
        });
        const d = await r.json();
        respEl.style.color = d.ok ? '#4ec9b0' : '#f44747';
        respEl.textContent = d.ok
            ? ('> ' + d.response)
            : ('Fehler: ' + d.response);
        if (d.ok) cmdInput.value = '';
    } catch(e) {
        respEl.style.color = '#f44747';
        respEl.textContent = 'Netzwerkfehler: ' + e.message;
    }
    sendBtn.disabled = false;
    cmdInput.focus();
}

cmdInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendCmd(); });
<?php endif; ?>
</script>
</body>
</html>
