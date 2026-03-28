const test = require('node:test');
const assert = require('node:assert/strict');

const {
    buildFilterUrl,
    parseFilterState,
} = require('../../../../assets/js/common/filters/dateBikeFilterState');

test('parseFilterState reads bike, year and unix timestamps from the query string', () => {
    assert.deepEqual(
        parseFilterState('?bike=abc123&year=2024&dateFrom=1711843200&dateTo=1714521599'),
        {
            bike: 'abc123',
            year: '2024',
            dateFrom: 1711843200,
            dateTo: 1714521599,
        }
    );
});

test('parseFilterState ignores empty and invalid numeric values', () => {
    assert.deepEqual(parseFilterState('?bike=&dateFrom=oops&dateTo='), {
        bike: '',
        year: undefined,
        dateFrom: undefined,
        dateTo: undefined,
    });
});

test('buildFilterUrl adds, updates and removes filter params', () => {
    assert.equal(
        buildFilterUrl('https://example.test/dashboard/refueling?bike=old&page=2', {
            bike: 'new',
            page: undefined,
            dateFrom: 10,
        }),
        '/dashboard/refueling?bike=new&dateFrom=10'
    );
});

test('buildFilterUrl preserves hash fragments', () => {
    assert.equal(
        buildFilterUrl('https://example.test/dashboard/maintenance#filters', {
            year: 2025,
        }),
        '/dashboard/maintenance?year=2025#filters'
    );
});
