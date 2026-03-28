document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-part-stat-selector="true"]').forEach((select) => {
        const cards = Array.from(document.querySelectorAll('[data-part-stat-card]'));
        const badge = select.closest('.part-stat-shell')?.querySelector('.part-stat-toolbar .inventory-panel__badge');

        const syncVisiblePart = () => {
            let activeLabel = '';

            cards.forEach((card) => {
                const isActive = card.dataset.partStatCard === select.value;
                card.hidden = !isActive;

                if (isActive) {
                    activeLabel = card.querySelector('.part-stat-card__title')?.textContent?.trim() || '';
                }
            });

            if (badge && activeLabel !== '') {
                badge.textContent = activeLabel;
            }
        };

        select.addEventListener('change', syncVisiblePart);
        syncVisiblePart();
    });
});
