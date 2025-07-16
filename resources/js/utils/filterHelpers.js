export function applyProductFilter(filterType, cards) {
    const noResults = document.getElementById('no-results');
    let visibleCount = 0;

    cards.forEach(card => {
        const lateClass = card.querySelector('form[id^="form-"].bg-red-400') ? 'darkred'
                        : card.querySelector('form[id^="form-"].bg-red-100') ? 'red'
                        : card.querySelector('form[id^="form-"].bg-yellow-100') ? 'yellow'
                        : '';

        const createdAt = card.dataset.createdAt;

        let show = false;

        if (filterType === 'all') {
            show = true;
        } else if (filterType === 'latest') {
            const top10 = [...cards].sort((a, b) => new Date(b.dataset.createdAt) - new Date(a.dataset.createdAt)).slice(0, 10);
            show = top10.includes(card);
        } else {
            show = lateClass === filterType;
        }

        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    // Toggle no-results text
    if (noResults) {
        noResults.classList.toggle('hidden', visibleCount > 0);
    }
}
