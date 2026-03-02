/**
 * dashboard.js – Metrics grid + Status bars + Offline-Backoff
 */

const grid   = document.getElementById("metrics");
const search = document.getElementById("search");

let isOnline      = false;
let retryTimer    = null;
let retryInterval = 1000;
const INTERVAL_ONLINE  = 1000;
const INTERVAL_OFFLINE = 10000;

// ── Hilfs-Funktionen (auch von dashboard.php inline genutzt) ─────────────────
function setServerOnline(online) {
    window.dashboardOnline = online;
    const elOn  = document.getElementById('srv-status-online');
    const elOff = document.getElementById('srv-status-offline');
    if (elOn)  elOn.style.display  = online ? '' : 'none';
    if (elOff) elOff.style.display = online ? 'none' : '';

    if (typeof canControl !== 'undefined' && canControl) {
        document.querySelectorAll('.btn-when-online').forEach(b  => b.style.display = online ? ''     : 'none');
        document.querySelectorAll('.btn-when-offline').forEach(b => b.style.display = online ? 'none' : ''    );
    }
}

function setBar(id, pct, warnAt, dangerAt) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.width = Math.min(pct, 100) + '%';
    el.className = 'bar-fill';
    if      (pct >= dangerAt) el.classList.add('bar-danger');
    else if (pct >= warnAt)   el.classList.add('bar-warning');
    else                      el.classList.add('bar-success');
}

function color(raw, thresholds) {
    if (!thresholds) return '';
    if (raw >= thresholds[0]) return 'green';
    if (raw >= thresholds[1]) return 'yellow';
    return 'red';
}

// ── Metric-Cards Rendering ───────────────────────────────────────────────────
function renderGrid(data) {
    grid.innerHTML = '';
    const isAdmin = data.some(m => m.title === "JVM Threads");
    const hiddenMetrics = [
        "JVM RAM Max", 
        "Median Tick-Dauer", 
        "Max. Tick-Dauer",
        "Weltgröße (größte)" // Optional, falls du es nur im Balken willst
    ];
    data.forEach(m => {
        if (search.value && !m.title.toLowerCase().includes(search.value.toLowerCase())) return;
        if (hiddenMetrics.includes(m.title) && !isAdmin) return
        const el = document.createElement('div');
        el.className = 'metric ' + color(m.raw, m.thresholds);
        el.innerHTML = `<div class="metric-name">${m.title}</div><div class="metric-value">${m.value}</div>`;
        grid.appendChild(el);
    });
}

// ── Status-Bars aus Metriken befüllen ────────────────────────────────────────
function updateBars(data) {
    let serverOnline = false;
    let ramMax = 0;

    data.forEach(m => {
        if (m.title === 'JVM RAM Max') ramMax = parseFloat(m.raw) || 0;
    });

    data.forEach(m => {
        const key = m.title.toLowerCase();
        const raw = parseFloat(m.raw) || 0;

        if (key === 'tps') {
            const el = document.getElementById('cpu-text');
            if (el) el.textContent = raw.toFixed(2) + ' TPS';
            // TPS 20 = grün, ab 15 = gelb, ab 0-10 = rot (Balken: hoher TPS = groß, niedrig = klein)
            // Balkenfarbe: >=15 grün, >=10 gelb, <10 rot
            let pct = Math.min((raw / 20) * 100, 100);
            let warnAt = 50;    // ab 10 TPS gelb
            let dangerAt = 75;  // ab 15 TPS grün
            // setBar: pct, warnAt, dangerAt -> bar-success wenn pct < warnAt, bar-warning wenn pct < dangerAt, sonst bar-danger
            // Wir wollen: pct < 50 = rot, < 75 = gelb, sonst grün
            // Daher: bar-danger < warnAt, bar-warning < dangerAt, sonst bar-success
            const elBar = document.getElementById('cpu-bar');
            if (elBar) elBar.className = 'bar-fill'; // Reset
            if (pct < warnAt) {
                setBar('cpu-bar', pct, 0, 0); // bar-danger
                document.getElementById('cpu-bar').classList.add('bar-danger');
            } else if (pct < dangerAt) {
                setBar('cpu-bar', pct, 0, 0); // bar-warning
                document.getElementById('cpu-bar').classList.add('bar-warning');
            } else {
                setBar('cpu-bar', pct, 0, 0); // bar-success
                document.getElementById('cpu-bar').classList.add('bar-success');
            }
            if (raw > 0) serverOnline = true;
        }

        if (key === 'jvm ram belegt') {
            const el = document.getElementById('ram-text');
            if (el) el.textContent = m.value;
            const pct = ramMax > 0 ? (raw / ramMax) * 100 : 0;
            setBar('ram-bar', pct, 70, 90);
        }

        if (key === 'weltgröße (größte)') {
            const el = document.getElementById('ssd-text');
            if (el) el.textContent = m.value;
            setBar('ssd-bar', Math.min(raw / 1e10 * 100, 100), 60, 85);
        }

        if (key.includes('version')) {
            const el = document.getElementById('srv-version');
            if (el) el.textContent = m.value;
        }
    });

    return serverOnline;
}

// ── Reset wenn Offline ───────────────────────────────────────────────────────
function handleOffline() {
    setServerOnline(false);
    if (grid && grid.children.length === 0) {
        grid.innerHTML = '<div class="alert alert-warning" style="margin:8px 0;"><i class="fa fa-plug"></i> Prometheus nicht erreichbar – Metriken werden geladen sobald der Server online ist.</div>';
    }
    ['cpu-bar','ram-bar','ssd-bar'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.width = '0%'; el.className = 'bar-fill'; }
    });
    ['cpu-text','ram-text','ssd-text'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '–';
    });
    if (isOnline) {
        isOnline = false;
        retryInterval = INTERVAL_OFFLINE;
    }
}

// ── Haupt-Update ─────────────────────────────────────────────────────────────
async function update() {
    try {
        const res = await fetch('metrics.php');
        if (!res.ok) { handleOffline(); return; }

        const data = await res.json();
        if (data && data.error) { handleOffline(); return; }
        if (!Array.isArray(data) || data.length === 0) { handleOffline(); return; }

        window.dashboardData = data;
        const online = updateBars(data);
        setServerOnline(online);
        renderGrid(data);

        if (!isOnline) {
            isOnline = true;
            retryInterval = INTERVAL_ONLINE;
        }
    } catch (e) {
        handleOffline();
    }
}

function scheduleNext() {
    clearTimeout(retryTimer);
    retryTimer = setTimeout(async () => {
        await update();
        scheduleNext();
    }, retryInterval);
}

// ── Init ─────────────────────────────────────────────────────────────────────
search.addEventListener('input', () => {
    if (window.dashboardData) renderGrid(window.dashboardData);
});

update().then(scheduleNext);
