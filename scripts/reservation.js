document.addEventListener('DOMContentLoaded', function() {
    const departSelect = document.getElementById('depart');
    const arriveeSelect = document.getElementById('arrivee');

    if (!departSelect || !arriveeSelect) return;

    function updateArriveeOptions() {
        const selectedIndex = departSelect.selectedIndex;

        if (arriveeSelect.selectedIndex > 0 && arriveeSelect.selectedIndex <= selectedIndex) {
            arriveeSelect.selectedIndex = 0;
        }

        for (let i = 1; i < arriveeSelect.options.length; i++) {
            arriveeSelect.options[i].disabled = selectedIndex > 0 && i <= selectedIndex;
        }
    }

    departSelect.addEventListener('change', updateArriveeOptions);
    updateArriveeOptions();
});
