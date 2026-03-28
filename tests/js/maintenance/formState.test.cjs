const test = require('node:test');
const assert = require('node:assert/strict');

const {
    calculateMaintenanceDraftSummary,
    canRemoveMaintenanceTask,
    normalizeAmount,
} = require('../../../assets/js/maintenance/formState');

test('normalizeAmount coerces invalid values to zero', () => {
    assert.equal(normalizeAmount('12.50'), 12.5);
    assert.equal(normalizeAmount('invalid'), 0);
});

test('calculateMaintenanceDraftSummary aggregates task and extra costs', () => {
    assert.deepEqual(
        calculateMaintenanceDraftSummary(3, ['10.50', '5', '', '2.25'], '7.25'),
        {
            taskCount: 3,
            taskCost: 17.75,
            unspecifiedCosts: 7.25,
            totalCost: 25,
        }
    );
});

test('canRemoveMaintenanceTask keeps one task row in place', () => {
    assert.equal(canRemoveMaintenanceTask(1), false);
    assert.equal(canRemoveMaintenanceTask(2), true);
});
