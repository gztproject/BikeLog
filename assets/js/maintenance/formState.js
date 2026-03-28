function normalizeAmount(value) {
    const normalized = Number.parseFloat(value);

    return Number.isFinite(normalized) ? normalized : 0;
}

function calculateMaintenanceDraftSummary(taskCount, taskCosts = [], unspecifiedCosts = 0) {
    const taskCost = taskCosts.reduce((sum, value) => sum + normalizeAmount(value), 0);
    const extraCost = normalizeAmount(unspecifiedCosts);

    return {
        taskCount,
        taskCost,
        unspecifiedCosts: extraCost,
        totalCost: taskCost + extraCost,
    };
}

function canRemoveMaintenanceTask(taskCount) {
    return taskCount > 1;
}

module.exports = {
    calculateMaintenanceDraftSummary,
    canRemoveMaintenanceTask,
    normalizeAmount,
};
