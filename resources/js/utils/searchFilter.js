export function setupSearchFilter(inputId, targetClass, dataFields = [], noResultElementId = null) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const noResult = noResultElementId ? document.getElementById(noResultElementId) : null;

    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        const items = document.querySelectorAll(`.${targetClass}`);

        let hasMatch = false;

        items.forEach(item => {
            const matches = dataFields.some(field => {
                const val = item.dataset[field]?.toLowerCase() || '';
                return val.includes(query);
            });

            item.style.display = matches ? '' : 'none';
            if (matches) hasMatch = true;
        });

        if (noResult) {
            noResult.classList.toggle('hidden', hasMatch);
        }
    });
}
