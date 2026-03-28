function parseOptionalInteger(value) {
    if (value === null || value === undefined || value === '') {
        return undefined;
    }

    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) ? parsed : undefined;
}

function parseFilterState(search = '') {
    const searchParams = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
    const year = searchParams.get('year') || undefined;

    return {
        bike: searchParams.get('bike') || '',
        year,
        dateFrom: parseOptionalInteger(searchParams.get('dateFrom')),
        dateTo: parseOptionalInteger(searchParams.get('dateTo')),
    };
}

function buildFilterUrl(currentHref, updates = {}) {
    const url = new URL(currentHref, 'http://localhost');

    Object.entries(updates).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            url.searchParams.delete(key);
            return;
        }

        url.searchParams.set(key, String(value));
    });

    const search = url.searchParams.toString();

    return `${url.pathname}${search ? `?${search}` : ''}${url.hash}`;
}

module.exports = {
    buildFilterUrl,
    parseFilterState,
};
