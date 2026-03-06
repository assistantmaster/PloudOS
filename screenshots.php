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
$current = 'screenshots';

$waypoints = [
    ['name'=>'Main Dorf',    'x'=>-2149,'y'=>81,  'z'=>1483, 'color'=>'#e74c3c',
     'xaero'=>'xaero-waypoint:Dorf:D:-2149:81:1483:15:false:0:Internal-overworld-waypoints'],
    ['name'=>'Iron Farm',    'x'=>-1900,'y'=>80,  'z'=>1600, 'color'=>'#95a5a6',
     'xaero'=>'xaero-waypoint:Iron Farm:I:-1900:80:1600:7:false:0:Internal-overworld-waypoints'],
    ['name'=>'Dorf 2',       'x'=>-2372,'y'=>81,  'z'=>1148, 'color'=>'#e67e22',
     'xaero'=>'xaero-waypoint:Dorf 2:D:-2372:81:1148:9:false:0:Internal-overworld-waypoints'],
    ['name'=>'Konrads Base', 'x'=>-2455,'y'=>null,'z'=>1078, 'color'=>'#9b59b6',
     'xaero'=>'xaero-waypoint:Konrads Base:K:-2455:~:1078:9:false:0:Internal-overworld-waypoints'],
    ['name'=>'Outpost',      'x'=>-1865,'y'=>86,  'z'=>2311, 'color'=>'#2c3e50',
     'xaero'=>'xaero-waypoint:Outpost:O:-1865:86:2311:0:false:0:Internal-overworld-waypoints'],
    ['name'=>'Swamp',        'x'=>-989, 'y'=>68,  'z'=>2748, 'color'=>'#27ae60',
     'xaero'=>'xaero-waypoint:Sumpf:S:-989:68:2748:6:false:0:Internal-overworld-waypoints'],
    ['name'=>'End Portal',   'x'=>-1364,'y'=>-19, 'z'=>1125, 'color'=>'#8e44ad',
     'xaero'=>'xaero-waypoint:Endportal:E:-1364:-19:1125:14:false:0:Internal-overworld-waypoints'],
    ['name'=>'Raidfarm',     'x'=>-2651,'y'=>62,  'z'=>615,  'color'=>'#c0392b',
     'xaero'=>'xaero-waypoint:Raidfarm:R:-2651:62:615:2:false:0:Internal-overworld-waypoints'],
    ['name'=>'Jonas Base',   'x'=>-1903,'y'=>58,  'z'=>242,  'color'=>'#2980b9',
     'xaero'=>'xaero-waypoint:Jonas Base:J:-1903:58:242:4:false:0:Internal-overworld-waypoints'],
    ['name'=>'Spawn',        'x'=>-16,  'y'=>66,  'z'=>-423, 'color'=>'#f39c12',
     'xaero'=>'xaero-waypoint:Spawn:S:-16:66:-423:1:false:0:Internal-overworld-waypoints'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Screenshots &amp; Waypoints | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        /* Tabs */
        .sw-tabs {
            display:flex; gap:0;
            border-bottom:2px solid #4673E2;
            margin-bottom:20px; flex-wrap:wrap;
        }
        .sw-tab {
            padding:9px 20px; cursor:pointer;
            font-size:13px; font-weight:600;
            background:#f5f5f5; border:1px solid #ddd;
            border-bottom:none; color:#666; user-select:none;
        }
        .sw-tab:hover { background:#eef2ff; color:#4673E2; }
        .sw-tab.active {
            background:#fff; color:#4673E2;
            border-color:#4673E2;
            border-bottom:2px solid #fff; margin-bottom:-2px;
        }
        .sw-panel { display:none; }
        .sw-panel.active { display:block; }

        /* Foto-Raster */
        .foto-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
            gap:10px;
        }
        .foto-item {
            position:relative; aspect-ratio:16/9;
            overflow:hidden; border-radius:3px;
            background:#111; cursor:pointer;
        }
        .foto-item img {
            width:100%; height:100%; object-fit:cover;
            display:block; transition:transform .25s,opacity .25s;
        }
        .foto-item:hover img { transform:scale(1.06); opacity:.85; }
        .foto-num {
            position:absolute; bottom:4px; right:6px;
            background:rgba(0,0,0,.55); color:#fff;
            font-size:10px; padding:1px 5px; border-radius:2px;
        }
        .foto-zoom {
            position:absolute; inset:0; display:flex;
            align-items:center; justify-content:center;
            font-size:20px; color:transparent;
            transition:background .2s,color .2s;
        }
        .foto-item:hover .foto-zoom { background:rgba(70,115,226,.18); color:#fff; }

        /* Lightbox */
        #lightbox {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.88); z-index:9999;
            align-items:center; justify-content:center; flex-direction:column;
        }
        #lightbox.open { display:flex; }
        #lightbox img {
            max-width:90vw; max-height:82vh; border-radius:3px;
            box-shadow:0 8px 40px rgba(0,0,0,.6); object-fit:contain;
        }
        #lb-counter { color:#aaa; font-size:13px; margin-top:10px; }
        #lb-close {
            position:fixed; top:16px; right:20px;
            color:#fff; font-size:28px; cursor:pointer;
            opacity:.7; background:none; border:none; line-height:1;
        }
        #lb-close:hover { opacity:1; }
        #lb-prev,#lb-next {
            position:fixed; top:50%; transform:translateY(-50%);
            background:rgba(255,255,255,.12); border:none; color:#fff;
            font-size:22px; padding:14px 18px; cursor:pointer;
            border-radius:3px; transition:background .15s;
        }
        #lb-prev:hover,#lb-next:hover { background:rgba(255,255,255,.25); }
        #lb-prev { left:16px; }
        #lb-next { right:16px; }

        /* Waypoints */
        .wp-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
            gap:12px;
        }
        .wp-card { background:#fff; border:1px solid #e8e8e8; border-radius:4px; overflow:hidden; }
        .wp-card-header {
            display:flex; align-items:center; gap:10px;
            padding:10px 14px; border-bottom:1px solid #f0f0f0;
        }
        .wp-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
        .wp-card-name { font-weight:700; font-size:14px; color:#222; flex:1; }
        .wp-card-body { padding:10px 14px; }
        .wp-coords { font-family:'Courier New',monospace; font-size:12px; color:#555; margin-bottom:8px; }
        .wp-coords span { display:inline-block; margin-right:10px; }
        .wp-coords span strong { color:#333; }
        .wp-xaero { display:flex; align-items:center; gap:6px; }
        .wp-xaero-text {
            flex:1; font-family:'Courier New',monospace; font-size:10px;
            color:#888; background:#f8f8f8; border:1px solid #e0e0e0;
            padding:4px 7px; border-radius:3px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .btn-copy {
            flex-shrink:0; background:#4673E2; border:none; color:#fff;
            padding:4px 10px; border-radius:3px; font-size:11px; cursor:pointer;
            white-space:nowrap; transition:background .15s;
        }
        .btn-copy:hover { background:#2c4ebf; }
        .btn-copy.copied { background:#27ae60; }
        .wp-hint {
            font-size:12px; color:#888; font-style:italic;
            margin-top:16px; padding:10px 14px;
            background:#fafafa; border:1px solid #eee; border-radius:3px;
        }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header">
            <h2>Screenshots &amp; Waypoints</h2>
        </div>

        <div class="sw-tabs">
            <div class="sw-tab active" onclick="switchTab('8bitsmp',this)">Screenshots 8BitSMP</div>
            <div class="sw-tab" onclick="switchTab('pixlsmp',this)">Screenshots PixlSMP</div>
            <div class="sw-tab" onclick="switchTab('waypoints',this)">Waypoints</div>
        </div>

        <!-- Screenshots 8BitSMP -->
        <div class="sw-panel active" id="tab-8bitsmp">
            <div class="foto-grid">
                <?php
                $bitsmp_imgs = glob('img/8bitsmp/*.png');
                natsort($bitsmp_imgs);
                $bitsmp_imgs = array_values($bitsmp_imgs);
                foreach ($bitsmp_imgs as $idx => $img):
                    $num = preg_replace('/[^0-9]/', '', basename($img));
                ?>
                <div class="foto-item" onclick="openLightbox('8bitsmp',<?= $idx ?>)">
                    <img src="<?= $img ?>" loading="lazy" alt="Screenshot <?= $num ?>"
                         onerror="this.closest('.foto-item').style.display='none'">
                    <div class="foto-zoom"><i class="fa fa-search-plus"></i></div>
                    <div class="foto-num"><?= $num ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Screenshots PixlSMP -->
        <div class="sw-panel" id="tab-pixlsmp">
            <div class="foto-grid">
                <?php
                $pixlsmp_imgs = glob('img/pixlsmp/*.png');
                natsort($pixlsmp_imgs);
                $pixlsmp_imgs = array_values($pixlsmp_imgs);
                foreach ($pixlsmp_imgs as $idx => $img):
                    $num = preg_replace('/[^0-9]/', '', basename($img));
                ?>
                <div class="foto-item" onclick="openLightbox('pixlsmp',<?= $idx ?>)">
                    <img src="<?= $img ?>" loading="lazy" alt="Screenshot <?= $num ?>"
                         onerror="this.closest('.foto-item').style.display='none'">
                    <div class="foto-zoom"><i class="fa fa-search-plus"></i></div>
                    <div class="foto-num"><?= $num ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Waypoints -->
        <div class="sw-panel" id="tab-waypoints">
            <div class="wp-grid">
                <?php foreach ($waypoints as $wp): ?>
                <div class="wp-card">
                    <div class="wp-card-header">
                        <div class="wp-dot" style="background:<?= htmlspecialchars($wp['color']) ?>"></div>
                        <div class="wp-card-name"><?= htmlspecialchars($wp['name']) ?></div>
                    </div>
                    <div class="wp-card-body">
                        <div class="wp-coords">
                            <span><strong>X</strong> <?= $wp['x'] ?></span>
                            <span><strong>Y</strong> <?= $wp['y'] !== null ? $wp['y'] : '~' ?></span>
                            <span><strong>Z</strong> <?= $wp['z'] ?></span>
                        </div>
                        <div class="wp-xaero">
                            <div class="wp-xaero-text" title="<?= htmlspecialchars($wp['xaero']) ?>">
                                <?= htmlspecialchars($wp['xaero']) ?>
                            </div>
                            <button class="btn-copy"
                                onclick="copyXaero(this,<?= htmlspecialchars(json_encode($wp['xaero'])) ?>)">
                                <i class="fa fa-clipboard"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="wp-hint">
                <i class="fa fa-info-circle"></i>
                Bei Benutzung von <strong>Xaero's Minimap / Worldmap</strong> kann man die Xaero-ID in den Chat kopieren und den Waypoint direkt hinzufügen.
            </div>
        </div>

    </div>
</div>

<!-- Lightbox -->
<div id="lightbox">
    <button id="lb-close" onclick="closeLightbox()">&times;</button>
    <button id="lb-prev"  onclick="lbStep(-1)"><i class="fa fa-chevron-left"></i></button>
    <img id="lb-img" src="" alt="">
    <div id="lb-counter"></div>
    <button id="lb-next"  onclick="lbStep(1)"><i class="fa fa-chevron-right"></i></button>
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
function switchTab(id, el) {
    document.querySelectorAll('.sw-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sw-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + id).classList.add('active');
}

const lbSrc = {
    '8bitsmp': <?php echo json_encode($bitsmp_imgs); ?>,
    'pixlsmp': <?php echo json_encode($pixlsmp_imgs); ?>,
};
let lbGal = [], lbIdx = 0;

function openLightbox(gal, idx) {
    lbGal = lbSrc[gal]; lbIdx = idx;
    renderLb();
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
function lbStep(d) {
    lbIdx = (lbIdx + d + lbGal.length) % lbGal.length;
    renderLb();
}
function renderLb() {
    document.getElementById('lb-img').src = lbGal[lbIdx];
    document.getElementById('lb-counter').textContent = (lbIdx+1) + ' / ' + lbGal.length;
}
document.addEventListener('keydown', e => {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key==='Escape') closeLightbox();
    if (e.key==='ArrowLeft') lbStep(-1);
    if (e.key==='ArrowRight') lbStep(1);
});
document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target===this) closeLightbox();
});

function copyXaero(btn, text) {
    const done = () => {
        btn.innerHTML = '<i class="fa fa-check"></i>';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML='<i class="fa fa-clipboard"></i>'; btn.classList.remove('copied'); }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        const ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); done();
    }
}
</script>
</body>
</html>
