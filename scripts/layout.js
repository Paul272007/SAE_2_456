function refresh() {
    setTimeout(showDate, 1000);
}

function showDate() {
    const date = new Date();
    let h = date.getHours();
    let m = date.getMinutes();
    let s = date.getSeconds() + 1;

    if (h < 10) h = "0" + h;
    if (m < 10) m = "0" + m;
    if (s < 10) s = "0" + s;

    document.getElementById("horloge").textContent = `${h}:${m}:${s}`;
    refresh();
}
showDate();

function showYear() {
    document.getElementById("annee").textContent = new Date().getFullYear();
}
showYear();