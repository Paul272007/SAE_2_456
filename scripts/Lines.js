function toggleLine(header) {
    const card = header.closest('.line-card');
    card.classList.toggle('expanded');

    const chevron = header.querySelector('.line-chevron');
    chevron.textContent = card.classList.contains('expanded') ? '∧' : '∨';
}
