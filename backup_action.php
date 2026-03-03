<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Nicht eingeloggt']);
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
    echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung (Admin erforderlich)']);
    exit();
}

define('BACKUP_DIR', '/home/timo/scoutsmp/backups');
define('SERVER_DIR', '/home/timo/scoutsmp');
$action = $_GET['action'] ?? '';

// ── Download ──────────────────────────────────────────────────────────
if ($action === 'download') {
    $file = basename($_GET['file'] ?? '');
    $path = BACKUP_DIR . '/' . $file;
    if (!$file || !str_ends_with($file, '.zip') || !is_file($path)) {
        http_response_code(404); exit('Datei nicht gefunden.');
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit();
}

header('Content-Type: application/json');

// ── Backup erstellen ──────────────────────────────────────────────────
if ($action === 'create') {
    if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0755, true);
    $timestamp = date('Y-m-d_H-i-s');
    $zipName   = 'backup_' . $timestamp . '.zip';
    $zipPath   = BACKUP_DIR . '/' . $zipName;
    $log       = [];
    if (!is_dir(SERVER_DIR)) {
        echo json_encode(['ok'=>false,'error'=>'Server-Ordner nicht gefunden: ' . SERVER_DIR]);
        exit();
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo json_encode(['ok'=>false,'error'=>'ZIP konnte nicht erstellt werden.']);
        exit();
    }
    // Kompletten SERVER_DIR rekursiv einpacken,
    // aber den backups-Ordner selbst ueberspringen (sonst Endlos-Rekursion)
    $realBackupDir = realpath(BACKUP_DIR);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(SERVER_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $fc = 0;
    foreach ($it as $entry) {
        $realEntry = realpath($entry->getPathname());
        if ($realEntry && str_starts_with($realEntry, $realBackupDir)) { continue; }
        if ($entry->isFile()) {
            $relPath = str_replace('\\', '/', substr($entry->getPathname(), strlen(SERVER_DIR) + 1));
            $zip->addFile($entry->getPathname(), $relPath);
            $fc++;
        }
    }
    $zip->close();
    $sz = file_exists($zipPath) ? filesize($zipPath) : 0;
    $log[] = '[OK] Server-Ordner: ' . SERVER_DIR;
    $log[] = '[OK] Backup-Ordner ausgeschlossen: ' . BACKUP_DIR;
    $log[] = '[DONE] ' . $zipName . ' - ' . round($sz / 1048576, 1) . ' MB, ' . $fc . ' Dateien';
    echo json_encode(['ok'=>true,'filename'=>$zipName,'size'=>$sz,'files'=>$fc,'log'=>implode("\n",$log)]);
    exit();
}

// ── Backup loeschen ───────────────────────────────────────────────────
if ($action === 'delete') {
    $file = basename($_GET['file'] ?? '');
    $path = BACKUP_DIR . '/' . $file;
    if (!$file || !str_ends_with($file, '.zip') || !is_file($path)) {
        echo json_encode(['ok'=>false,'error'=>'Datei nicht gefunden oder ungueltig.']);
        exit();
    }
    echo json_encode(
        unlink($path) ? ['ok'=>true] : ['ok'=>false,'error'=>'Loeschen fehlgeschlagen (Schreibrechte?).']
    );
    exit();
}

echo json_encode(['ok'=>false,'error'=>'Unbekannte Aktion.']);
