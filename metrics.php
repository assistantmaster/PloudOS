<?php
session_start();
$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); // Nicht eingeloggt? Zurück zum Login!
    exit();
} else {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

}

/*
|--------------------------------------------------------------------------
| ZENTRALE ADMIN-METRIKEN
|--------------------------------------------------------------------------
| unit:
|  - bytes | ms | s | tps | count
| flags:
|  - sum_labels  -> Werte mit Labels aufsummieren
|  - max_label   -> größten Wert nehmen (z. B. Welten)
*/
$config = [];

if ($user['permissions'] >= 1) {

    // SERVER PERFORMANCE
    $config["mc_tps"] = [
        "title" => "TPS",
        "unit" => "tps",
        "order" => 1,
        "thresholds" => [18, 15]
    ];
    $config["mc_tick_duration_average"] = [
        "title" => "Ø Tick-Dauer",
        "unit" => "ms",
        "order" => 2
    ];
    $config["mc_tick_duration_max"] = [
        "title" => "Max. Tick-Dauer",
        "unit" => "ms",
        "order" => 3
    ];
    $config["mc_tick_duration_median"] = [
        "title" => "Median Tick-Dauer",
        "unit" => "ms",
        "order" => 4
    ];
    $config["mc_jvm_memory_allocated"] = [
        "title" => "JVM RAM Belegt",
        "unit" => "bytes",
        "order" => 12
    ];
    $config["mc_jvm_memory_max"] = [
        "title" => "JVM RAM Max",
        "unit" => "bytes",
        "order" => 11
    ];
    $config["mc_world_size"] = [
        "title" => "Weltgröße (größte)",
        "unit" => "bytes",
        "order" => 9,
        "max_label" => true
    ];

    // SPIELER (nur summierte Zahlen)
    $config["mc_players_online_total"] = [
        "title" => "Spieler online",
        "unit" => "count",
        "order" => 5,
        "sum_labels" => true
    ];
    $config["mc_players_total"] = [
        "title" => "Bekannte Spieler",
        "unit" => "count",
        "order" => 6
    ];
    $config["mc_whitelisted_players"] = [
        "title" => "Whitelist-Spieler",
        "unit" => "count",
        "order" => 7
    ];
}

// ===== PERSÖNLICHE USER-DATEN (Permissions ≥ 2) =====
if ((int)$user['permissions'] >= 2) {
    $statsMapping = [
        "PLAY_ONE_MINUTE" => ["title" => "Spielzeit", "unit" => "ticks", "order" => 20],
        "DEATHS"          => ["title" => "Tode", "unit" => "count", "order" => 21],
        "MINE_BLOCK"      => ["title" => "Abgebaute Blöcke", "unit" => "count", "order" => 22],
        "MOB_KILLS"       => ["title" => "Monster Kills", "unit" => "count", "order" => 23],
        "WALK_ONE_CM"     => ["title" => "Gelaufene Distanz", "unit" => "cm", "order" => 24],
        "PLAYER_KILLS"    => ["title" => "Spieler Kills", "unit" => "count", "order" => 25],
        "DAMAGE_DEALT"    => ["title" => "Schaden ausgeteilt", "unit" => "hp", "order" => 26],
        "TIME_SINCE_DEATH"=> ["title" => "Überlebt seit", "unit" => "ticks", "order" => 27]
    ];

    foreach ($statsMapping as $mcKey => $info) {
        $config["mc_player_statistic_" . $mcKey] = [
            "title" => $info["title"],
            "unit"  => $info["unit"],
            "order" => $info["order"]
        ];
    }
}

// ===== WELTEN, JVM & GC (nur Permissions = 3) =====
if ($user['permissions'] >= 3) {
    $config["mc_loaded_chunks_total"] = [
        "title" => "Geladene Chunks",
        "unit" => "count",
        "order" => 10,
        "sum_labels" => true
    ];
    $config["mc_jvm_memory_free"] = [
        "title" => "JVM RAM Frei",
        "unit" => "bytes",
        "order" => 13
    ];

    $config["mc_jvm_threads_current"] = [
        "title" => "JVM Threads",
        "unit" => "count",
        "order" => 14
    ];
    $config["mc_jvm_threads_peak"] = [
        "title" => "JVM Threads Peak",
        "unit" => "count",
        "order" => 15
    ];
    $config["mc_jvm_threads_deadlocked"] = [
        "title" => "Thread Deadlocks",
        "unit" => "count",
        "order" => 16
    ];

    $config["mc_jvm_gc_collection_seconds_sum"] = [
        "title" => "GC Zeit gesamt",
        "unit" => "s",
        "order" => 17,
        "sum_labels" => true
    ];
    $config["mc_jvm_gc_collection_seconds_count"] = [
        "title" => "GC Durchläufe",
        "unit" => "count",
        "order" => 18,
        "sum_labels" => true
    ];
}


/* ------------------------------------------------------------------ */

$ctx = stream_context_create(['http' => ['timeout' => 4]]);
$raw = @file_get_contents("http://127.0.0.1:9940/metrics", false, $ctx);
if ($raw === false) {
    header("Content-Type: application/json");
    http_response_code(503);
    echo json_encode(["error" => "offline", "message" => "Prometheus nicht erreichbar"]);
    exit;
}

$values = [];

/* -------- METRIKEN PARSEN -------- */
foreach (explode("\n", $raw) as $line) {
    if ($line === "" || $line[0] === "#") continue;

    if (preg_match('/^([a-zA-Z_:]+)(\{([^}]*)\})?\s+([0-9.eE+-]+)/', $line, $m)) {
        $name = $m[1];
        $labels = $m[3] ?? "";
        $value = (float)$m[4];

        // 1. Spezialbehandlung: Spieler-Statistiken
        if ($name === "mc_player_statistic") {
            // Nutze stripos statt strpos für Case-Insensitive Vergleich (TCT2020TCT vs tct2020tct)
            if (stripos($labels, 'player_name="' . $user['username'] . '"') !== false) {
                if (preg_match('/statistic="([^"]+)"/', $labels, $st)) {
                    $name = "mc_player_statistic_" . $st[1];
                }
            } else {
                continue; // Gehört nicht diesem User
            }
        }

        // 2. Spezialbehandlung: JVM Memory Typen
        if ($name === "mc_jvm_memory" && preg_match('/type="([^"]+)"/', $labels, $t)) {
            $name = "mc_jvm_memory_" . $t[1];
        }

        // 3. Werte speichern (mit Logik für Summen/Max)
        if (isset($config[$name])) {
            $isSum = $config[$name]['sum_labels'] ?? false;
            $isMax = $config[$name]['max_label'] ?? false;

            if ($isSum) {
                // Addieren (z.B. Spielerzahlen über alle Welten)
                if (!isset($values[$name])) $values[$name] = 0;
                $values[$name] += $value;
            } elseif ($isMax) {
                // Maximum behalten (z.B. Weltgröße -> wir wollen die größte Welt anzeigen)
                if (!isset($values[$name])) $values[$name] = 0;
                if ($value > $values[$name]) {
                    $values[$name] = $value;
                }
            } else {
                // Standard: Einfaches Überschreiben (letzter Wert gewinnt)
                // Bei Einzelwerten wie TPS oder Player Stats ist das okay.
                $values[$name] = $value;
            }
        }
    }
}

/* -------- FORMATIERUNG -------- */
function formatValue(float $v, string $unit): string {
    return match ($unit) {
        "ticks" => $v < 72000 ? round($v / 1200, 1) . " Min" : round($v / 72000, 1) . " Std",
        "bytes" =>
            $v >= 1e9 ? round($v / 1e9, 2) . " GB" :
            ($v >= 1e6 ? round($v / 1e6, 1) . " MB" :
            round($v / 1024, 1) . " KB"),
        "ms" => round($v / 1_000_000, 2) . " ms",
        "s" => round($v, 2) . " s",
        "tps" => round($v, 2),
        "cm" => 
            $v < 100000 ? round($v / 100, 1) . " m" : round($v / 100000, 2) . " km",
        "hp" => round($v / 2, 0) . " ❤️",
        "count" => (string)(int)$v,
        default => (string)$v
    };
}

/* -------- OUTPUT -------- */
$out = [];

foreach ($config as $key => $meta) {
    if (!isset($values[$key])) continue;

    $out[] = [
        "key" => $key,
        "title" => $meta["title"],
        "value" => formatValue($values[$key], $meta["unit"]),
        "raw" => $values[$key],
        "thresholds" => $meta["thresholds"] ?? null,
        "order" => $meta["order"]
    ];
}

usort($out, fn($a, $b) => $a["order"] <=> $b["order"]);

header("Content-Type: application/json");
echo json_encode($out);
