<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403); exit();
}
header('Content-Type: application/json');

$logFile = '/home/timo/scoutsmp/logs/latest.log';
$lines   = (int)($_GET['lines'] ?? 80);
$lines   = min(max($lines, 10), 500);

if (!is_readable($logFile)) {
    echo json_encode(['ok' => false, 'lines' => [], 'error' => 'Logdatei nicht lesbar: ' . $logFile]);
    exit();
}

// Letzten N Zeilen effizient lesen
$fp   = fopen($logFile, 'r');
$size = filesize($logFile);
if ($size === 0) { echo json_encode(['ok' => true, 'lines' => []]); exit(); }

$chunkSize = 8192;
$buffer    = '';
$pos       = $size;
$collected = [];

while ($pos > 0 && count($collected) < $lines) {
    $readSize = min($chunkSize, $pos);
    $pos     -= $readSize;
    fseek($fp, $pos);
    $buffer = fread($fp, $readSize) . $buffer;
    $parts  = explode("\n", $buffer);
    // Letztes Element ist unvollständige Zeile oben
    $buffer = array_shift($parts);
    $parts  = array_reverse($parts);
    foreach ($parts as $line) {
        if ($line !== '') $collected[] = $line;
        if (count($collected) >= $lines) break;
    }
}
// Verbleibender Buffer
if ($buffer !== '' && count($collected) < $lines) $collected[] = $buffer;
fclose($fp);

$result = array_reverse(array_slice($collected, 0, $lines));

// Zeilen klassifizieren
function classifyLine(string $line): string {
    $l = strtolower($line);
    if (str_contains($l, '[warn') || str_contains($l, 'warning')) return 'warn';
    if (str_contains($l, '[error') || str_contains($l, 'exception') || str_contains($l, 'fatal')) return 'error';
    if (str_contains($l, 'done') || str_contains($l, 'started') || str_contains($l, 'joined')) return 'success';
    return 'info';
}

$out = array_map(fn($l) => ['text' => $l, 'type' => classifyLine($l)], $result);
echo json_encode(['ok' => true, 'lines' => $out]);
