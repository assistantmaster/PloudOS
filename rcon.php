<?php
/**
 * rcon.php – RCON Endpunkt & Action Handler
 * POST: { cmd: "...", action: "stop|start|force-stop|reload" }
 * Liefert JSON: { ok: true/false, response: "..." }
 */
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'response' => 'Nicht eingeloggt']);
    exit();
}

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$perm = (int)($user['permissions'] ?? 0);

if ($perm < 3) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'response' => 'Keine Berechtigung (perm 3 benötigt)']);
    exit();
}

header('Content-Type: application/json');

// ── RCON Konfiguration ──────────────────────────────────────────────
define('RCON_HOST',     '127.0.0.1');
define('RCON_PORT',     25575);
define('RCON_PASSWORD', 'scoutsmp');   // ← anpassen!
define('RCON_TIMEOUT',  5);

// ── Einfacher RCON Client ──────────────────────────────────────────
class MinecraftRcon {
    private $socket;
    private int $reqId = 1;

    public function connect(string $host, int $port, string $password, int $timeout): bool {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) return false;
        stream_set_timeout($this->socket, $timeout);

        // Authentifizieren
        $res = $this->send(3, $password);
        return ($res['id'] !== -1);
    }

    public function command(string $cmd): string {
        $res = $this->send(2, $cmd);
        return $res['body'] ?? '';
    }

    private function send(int $type, string $payload): array {
        $id   = $this->reqId++;
        $data = pack('VV', $id, $type) . $payload . "\x00\x00";
        $len  = strlen($data);
        fwrite($this->socket, pack('V', $len) . $data);

        $lenRaw = fread($this->socket, 4);
        if (strlen($lenRaw) < 4) return ['id' => -1, 'body' => ''];
        $pktLen = unpack('V', $lenRaw)[1];
        $pkt    = fread($this->socket, max($pktLen, 0));
        if (strlen($pkt) < 8) return ['id' => -1, 'body' => ''];

        $resId   = unpack('V', substr($pkt, 0, 4))[1];
        // PHP unpack 'V' ist unsigned; -1 kommt als 0xFFFFFFFF = 4294967295
        if ($resId > 2147483647) $resId = $resId - 4294967296;
        $body    = substr($pkt, 8, -2);
        return ['id' => $resId, 'body' => $body];
    }

    public function disconnect(): void {
        if ($this->socket) fclose($this->socket);
    }
}

function rcon(string $cmd): array {
    $r = new MinecraftRcon();
    if (!$r->connect(RCON_HOST, RCON_PORT, RCON_PASSWORD, RCON_TIMEOUT)) {
        return ['ok' => false, 'response' => 'RCON-Verbindung fehlgeschlagen. Ist der Server online und RCON aktiviert?'];
    }
    $resp = $r->command($cmd);
    $r->disconnect();
    return ['ok' => true, 'response' => $resp ?: '(kein Output)'];
}

// ── Request verarbeiten ────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$cmd    = trim($input['cmd']    ?? $_POST['cmd']    ?? '');
$action = trim($input['action'] ?? $_POST['action'] ?? '');

if ($action) {
    switch ($action) {
        case 'stop':
            echo json_encode(rcon('stop'));
            break;
        case 'force-stop':
            // RCON stop + ggf. Kill-Fallback
            $res = rcon('stop');
            if (!$res['ok']) {
                // Fallback: kill (nur wenn Prozess bekannt)
                $pid = trim(@shell_exec("pgrep -f 'scoutsmp' 2>/dev/null"));
                if ($pid && is_numeric($pid)) {
                    shell_exec("kill -9 $pid 2>/dev/null");
                    $res = ['ok' => true, 'response' => 'Prozess hart beendet (PID ' . $pid . ')'];
                }
            }
            echo json_encode($res);
            break;
        case 'start':
            // Systemd-Service oder Startscript aufrufen
            $out = shell_exec('systemctl start scoutsmp 2>&1 || echo "Kein Systemd-Service gefunden"');
            echo json_encode(['ok' => true, 'response' => trim($out)]);
            break;
        case 'reload':
            echo json_encode(rcon('reload'));
            break;
        default:
            echo json_encode(['ok' => false, 'response' => 'Unbekannte Aktion: ' . htmlspecialchars($action)]);
    }
} elseif ($cmd) {
    echo json_encode(rcon($cmd));
} else {
    echo json_encode(['ok' => false, 'response' => 'Kein Befehl oder Aktion angegeben']);
}
