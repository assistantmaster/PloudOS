<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403); exit();
}
$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ((int)$user['permissions'] < 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Berechtigung 2 benötigt']);
    exit();
}

header('Content-Type: application/json');

$raw = @file_get_contents('http://127.0.0.1:9940/metrics');
if ($raw === false) {
    echo json_encode(['ok' => false, 'error' => 'Prometheus nicht erreichbar']);
    exit();
}

$username = strtolower($user['username']);

// Einheit aus Statistikname ableiten
function guessUnit(string $stat): string {
    $s = strtolower($stat);
    if (str_contains($s, '_one_minute') || str_contains($s, 'time') || str_contains($s, 'since'))  return 'ticks';
    if (str_contains($s, '_one_cm') || str_contains($s, '_cm'))   return 'cm';
    if (str_contains($s, 'damage') || str_contains($s, 'absorb')) return 'hp';
    if (str_contains($s, 'byte') || str_contains($s, 'download') || str_contains($s, 'upload')) return 'bytes';
    return 'count';
}

function formatValue(float $v, string $unit): string {
    return match($unit) {
        'ticks' => $v < 72000 ? round($v / 1200, 1).' Min' : round($v / 72000, 1).' Std',
        'cm'    => $v < 100000 ? round($v / 100, 1).' m' : round($v / 100000, 2).' km',
        'hp'    => round($v / 2, 0).' ❤',
        'bytes' => $v >= 1e9 ? round($v/1e9, 2).' GB' : ($v >= 1e6 ? round($v/1e6,1).' MB' : round($v/1024,1).' KB'),
        'count' => (string)(int)$v,
        default => (string)$v,
    };
}

// Statistiknamen lesbarer machen
function prettify(string $stat): string {
    $map = [
        'PLAY_ONE_MINUTE'        => 'Spielzeit',
        'TIME_SINCE_DEATH'       => 'Überlebt seit letztem Tod',
        'DEATHS'                 => 'Tode',
        'PLAYER_KILLS'           => 'Spieler getötet',
        'MOB_KILLS'              => 'Mobs getötet',
        'ANIMALS_BRED'           => 'Tiere gezüchtet',
        'MINE_BLOCK'             => 'Abgebaute Blöcke',
        'CRAFT_ITEM'             => 'Gecraftete Items',
        'USE_ITEM'               => 'Items benutzt',
        'BREAK_ITEM'             => 'Kaputte Items',
        'PICK_UP_ITEM'           => 'Aufgehobene Items',
        'DROP_COUNT'             => 'Weggeworfene Items',
        'DAMAGE_DEALT'           => 'Schaden ausgeteilt',
        'DAMAGE_DEALT_ABSORBED'  => 'Schaden absorbiert (dealt)',
        'DAMAGE_DEALT_RESISTED'  => 'Schaden resisted (dealt)',
        'DAMAGE_TAKEN'           => 'Schaden erhalten',
        'DAMAGE_ABSORBED'        => 'Schaden absorbiert',
        'DAMAGE_RESISTED'        => 'Schaden widerstanden',
        'DAMAGE_BLOCKED_BY_SHIELD' => 'Mit Schild geblockt',
        'WALK_ONE_CM'            => 'Gelaufen',
        'WALK_ON_WATER_ONE_CM'   => 'Auf Wasser gelaufen',
        'WALK_UNDER_WATER_ONE_CM'=> 'Unter Wasser gelaufen',
        'SPRINT_ONE_CM'          => 'Gesprintet',
        'CROUCH_ONE_CM'          => 'Geschlichen',
        'FLY_ONE_CM'             => 'Geflogen (Creative)',
        'SWIM_ONE_CM'            => 'Geschwommen',
        'CLIMB_ONE_CM'           => 'Geklettert',
        'FALL_ONE_CM'            => 'Gefallen',
        'HORSE_ONE_CM'           => 'Pferd geritten',
        'PIG_ONE_CM'             => 'Schwein geritten',
        'BOAT_ONE_CM'            => 'Boot gefahren',
        'MINECART_ONE_CM'        => 'Lore gefahren',
        'AVIATE_ONE_CM'          => 'Gegleitet (Elytra)',
        'STRIDER_ONE_CM'         => 'Strider geritten',
        'JUMP'                   => 'Sprünge',
        'SLEEP_IN_BED'           => 'Im Bett geschlafen',
        'LEAVE_GAME'             => 'Spiel verlassen',
        'TOTAL_WORLD_TIME'       => 'Gesamte Weltzeit',
        'TALKED_TO_VILLAGER'     => 'Mit Dorfbewohner gesprochen',
        'TRADED_WITH_VILLAGER'   => 'Mit Dorfbewohner gehandelt',
        'ENCHANT_ITEM'           => 'Items verzaubert',
        'RAID_WIN'               => 'Raids gewonnen',
        'RAID_TRIGGER'           => 'Raids ausgelöst',
        'TARGET_HIT'             => 'Zielscheiben getroffen',
        'INSPECT_DISPENSER'      => 'Werfer untersucht',
        'INSPECT_DROPPER'        => 'Dropper untersucht',
        'INSPECT_HOPPER'         => 'Trichter untersucht',
        'OPEN_CHEST'             => 'Truhen geöffnet',
        'OPEN_ENDERCHEST'        => 'Enderchest geöffnet',
        'OPEN_SHULKER_BOX'       => 'Shulkerbox geöffnet',
        'TRIGGER_TRAPPED_CHEST'  => 'Fallentruhe ausgelöst',
        'BELL_RING'              => 'Glocken geläutet',
        'FISH_CAUGHT'            => 'Fische gefangen',
        'PLAY_NOTEBLOCK'         => 'Notenblöcke gespielt',
        'TUNE_NOTEBLOCK'         => 'Notenblöcke gestimmt',
        'POT_FLOWER'             => 'Blumen eingetopft',
        'CLEAN_ARMOR'            => 'Rüstung gereinigt',
        'CLEAN_BANNER'           => 'Banner gereinigt',
        'CAKE_SLICES_EATEN'      => 'Tortenstücke gegessen',
        'CAULDRON_FILLED'        => 'Kessel befüllt',
        'CAULDRON_USED'          => 'Kessel genutzt',
        'ARMOR_CLEANED'          => 'Rüstung geputzt',
        'BANNER_CLEANED'         => 'Banner geputzt',
        'DROPPER_INSPECTED'      => 'Dropper geöffnet',
        'HOPPER_INSPECTED'       => 'Trichter geöffnet',
        'DISPENSER_INSPECTED'    => 'Werfer geöffnet',
        'CHEST_OPENED'           => 'Truhen geöffnet',
        'ENDER_CHEST_OPENED'     => 'Enderchest geöffnet',
        'ITEM_ENCHANTED'         => 'Verzauberungen',
        'RECORD_PLAYED'          => 'Schallplatten gespielt',
        'FURNACE_INTERACTION'    => 'Ofen-Interaktionen',
        'CRAFTING_TABLE_INTERACTION' => 'Werkbank-Interaktionen',
        'CHEST_INTERACTION'      => 'Truhen-Interaktionen',
        'BARREL_INTERACTION'     => 'Fass-Interaktionen',
    ];
    if (isset($map[$stat])) return $map[$stat];
    // Fallback: Snake_Case → Title Case
    return ucwords(strtolower(str_replace('_', ' ', $stat)));
}

$stats = [];

foreach (explode("\n", $raw) as $line) {
    if ($line === '' || $line[0] === '#') continue;
    // mc_player_statistic{statistic="...",player_name="..."}  value
    if (!preg_match('/^mc_player_statistic\{([^}]+)\}\s+([0-9.eE+-]+)/', $line, $m)) continue;

    $labels = $m[1];
    $value  = (float)$m[2];

    if ($value == 0) continue; // Null-Werte überspringen

    // player_name prüfen (case-insensitive)
    if (!preg_match('/player_name="([^"]+)"/', $labels, $pn)) continue;
    if (strtolower($pn[1]) !== $username) continue;

    // Statistikname
    if (!preg_match('/statistic="([^"]+)"/', $labels, $st)) continue;
    $statKey = $st[1];

    $unit  = guessUnit($statKey);
    $stats[$statKey] = [
        'key'   => $statKey,
        'title' => prettify($statKey),
        'value' => formatValue($value, $unit),
        'raw'   => $value,
        'unit'  => $unit,
    ];
}

// Alphabetisch nach Title sortieren
usort($stats, fn($a,$b) => strcmp($a['title'], $b['title']));

echo json_encode(['ok' => true, 'username' => $user['username'], 'stats' => array_values($stats)]);
