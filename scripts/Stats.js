const canvas = document.getElementById('topLinesChart');
const dataContainer = document.getElementById('topLinesData');

if (canvas && dataContainer) {
    const colors = ['#cc0000', '#0d6efd', '#198754', '#f59e0b', '#6f42c1'];

    const items = Array.from(dataContainer.querySelectorAll('.legend-item')).map((item, index) => {
        const color = colors[index % colors.length];
        item.querySelector('.legend-color').style.backgroundColor = color;

        return {
            label: item.dataset.label,
            value: Number(item.dataset.value),
            color: color
        };
    });

    const total = items.reduce((sum, item) => sum + item.value, 0);
    const ctx = canvas.getContext('2d');

    canvas.width = 420;
    canvas.height = 420;

    let startAngle = -Math.PI / 2;

    items.forEach((item) => {
        const sliceAngle = (item.value / total) * Math.PI * 2;
        const endAngle = startAngle + sliceAngle;

        ctx.beginPath();
        ctx.moveTo(210, 210);
        ctx.arc(210, 210, 170, startAngle, endAngle);
        ctx.closePath();

        ctx.fillStyle = item.color;
        ctx.fill();

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.stroke();

        const middleAngle = startAngle + sliceAngle / 2;
        const percent = Math.round((item.value / total) * 100);

        if (percent >= 8) {
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 16px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(
                percent + ' %',
                210 + Math.cos(middleAngle) * 100,
                210 + Math.sin(middleAngle) * 100
            );
        }

        startAngle = endAngle;
    });
}