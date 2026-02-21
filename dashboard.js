const grid = document.getElementById("metrics");
const search = document.getElementById("search");
let cards = {};

function color(raw, thresholds) {
    if (!thresholds) return "";
    if (raw >= thresholds[0]) return "green";
    if (raw >= thresholds[1]) return "yellow";
    return "red";
}

async function update() {
    const res = await fetch("metrics.php");
    const data = await res.json();

    grid.innerHTML = "";

    data.forEach(m => {
        if (search.value && !m.title.toLowerCase().includes(search.value.toLowerCase())) return;

        const el = document.createElement("div");
        el.className = `metric ${color(m.raw, m.thresholds)}`;
        el.innerHTML = `
            <div class="metric-name">${m.title}</div>
            <div class="metric-value">${m.value}</div>
        `;
        grid.appendChild(el);
    });
}

search.addEventListener("input", update);

update();
setInterval(update, 1000);
