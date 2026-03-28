import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

const { createDateTimePickerOptions } = require('../common/dateTimePickerOptions');
const {
    calculateMaintenanceDraftSummary,
    canRemoveMaintenanceTask,
} = require('./formState');

let $collectionHolder;

function getTaskRows() {
    return $collectionHolder.find('tr[data-item-index]');
}

function getTaskCount() {
    return getTaskRows().length;
}

function getTaskCosts() {
    return getTaskRows()
        .find('input[id$="_cost"]')
        .map(function mapTaskCost() {
            return $(this).val();
        })
        .get();
}

function refreshMaintenanceSummary() {
    const summary = calculateMaintenanceDraftSummary(
        getTaskCount(),
        getTaskCosts(),
        $('#maintenance_unspecifiedCosts').val()
    );

    $('#maintenance-task-count').text(summary.taskCount);
    $('#maintenance-task-cost').text(summary.taskCost.toFixed(2));
    $('#maintenance-total-cost').text(summary.totalCost.toFixed(2));

    const disableRemoval = !canRemoveMaintenanceTask(summary.taskCount);

    $collectionHolder.find('.maintenance-task-remove').prop('disabled', disableRemoval);
}

function buildMaintenanceTaskRow(index, formMarkup) {
    const $prototype = $(formMarkup);
    const $taskField = $prototype.find(`#maintenance_maintenanceTaskCommands_${index}_task`);
    const $costField = $prototype.find(`#maintenance_maintenanceTaskCommands_${index}_cost`);

    $taskField.addClass('form-control');
    $costField.addClass('form-control');

    const $row = $(`
        <tr class="maintenance-task-row" data-item-index="${index}">
            <td class="taskInput"></td>
            <td class="costInput"></td>
            <td class="removeBtn text-right"></td>
        </tr>
    `);

    const $costGroup = $(`
        <div class="input-group unit-input">
            <div class="input-group-append">
                <span class="input-group-text">EUR</span>
            </div>
        </div>
    `);

    const $removeButton = $(`
        <button type="button" class="btn btn-sm btn-outline-danger maintenance-task-remove" data-item-index="${index}">
            <i class="fa fa-minus" aria-hidden="true"></i>
        </button>
    `);

    $row.find('.taskInput').append($taskField);
    $costGroup.prepend($costField);
    $row.find('.costInput').append($costGroup);
    $row.find('.removeBtn').append($removeButton);

    return $row;
}

function addMaintenanceTaskForm() {
    const prototype = $collectionHolder.data('prototype');
    const index = Number($collectionHolder.data('index'));
    const formMarkup = prototype.replace(/__name__/g, index);
    const $newRow = buildMaintenanceTaskRow(index, formMarkup);

    $collectionHolder.append($newRow);
    $collectionHolder.data('index', index + 1);

    refreshMaintenanceSummary();
}

function removeMaintenanceTaskForm(index) {
    if (!canRemoveMaintenanceTask(getTaskCount())) {
        refreshMaintenanceSummary();
        return;
    }

    $collectionHolder.find(`tr[data-item-index="${index}"]`).remove();
    refreshMaintenanceSummary();
}

$(function () {
    $('#maintenance_date').datetimepicker(
        createDateTimePickerOptions({
            format: 'DD. MM. YYYY',
        })
    );

    $collectionHolder = $('tbody.maintenanceTasks');
    $collectionHolder.data('index', getTaskCount());

    if (getTaskCount() === 0) {
        addMaintenanceTaskForm();
    } else {
        refreshMaintenanceSummary();
    }

    $('#add-maintenance-task').on('click', function onAddTaskClick() {
        addMaintenanceTaskForm();
    });

    $collectionHolder.on('click', '.maintenance-task-remove', function onRemoveTaskClick() {
        removeMaintenanceTaskForm(Number($(this).data('itemIndex')));
    });

    $collectionHolder.on('input change', 'input[id$="_cost"], select', refreshMaintenanceSummary);
    $('#maintenance_unspecifiedCosts').on('input change', refreshMaintenanceSummary);
});
