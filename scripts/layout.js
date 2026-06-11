function showTime() {
    const date = new Date();

    let h = String(date.getHours()).padStart(2, "0");
    let m = String(date.getMinutes()).padStart(2, "0");
    let s = String(date.getSeconds()).padStart(2, "0");

    let horlogeElement = document.getElementById("horloge");
    if (horlogeElement) {
        horlogeElement.textContent = `${h}:${m}:${s}`;
    }
}

showTime();

setInterval(showTime, 1000);