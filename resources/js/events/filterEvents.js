import { applyProductFilter } from '../utils/filterHelpers';

export function setupFilterEvents(toggleBtnId, panelId, optionSelector, cardSelector) {
    const toggleBtn = document.getElementById(toggleBtnId);
    const panel = document.getElementById(panelId);
    const optionButtons = document.querySelectorAll(optionSelector);
    const cards = document.querySelectorAll(cardSelector);

    if (!toggleBtn || !panel) return;

    toggleBtn.addEventListener('click', () => {
        const isHidden = panel.classList.contains('opacity-0');

        if (isHidden) {
            panel.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
            panel.classList.add('opacity-100', 'scale-100');
        } else {
            panel.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            panel.classList.remove('opacity-100', 'scale-100');
        }
    });

    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
            panel.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            panel.classList.remove('opacity-100', 'scale-100');
        }
    });

    optionButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const filterType = btn.dataset.filter;
            applyProductFilter(filterType, cards);
            panel.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            panel.classList.remove('opacity-100', 'scale-100');
        });
    });
}
