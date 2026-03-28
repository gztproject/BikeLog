import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

const { createDateTimePickerOptions } = require('../common/dateTimePickerOptions');

function parseInteger(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) ? parsed : null;
}

function formatInteger(value) {
    return new Intl.NumberFormat().format(value);
}

function setLocationStatus(statusKey) {
    const $status = $('#refueling-location-status');

    if ($status.length === 0) {
        return;
    }

    const message = $status.data(`${statusKey}Label`);

    $status
        .removeClass('status-pending status-ready status-error')
        .addClass(`status-${statusKey}`)
        .text(message);
}

function setPosition(position) {
    $('#refueling_latitude').val(position.coords.latitude);
    $('#refueling_longitude').val(position.coords.longitude);
    setLocationStatus('ready');
}

function setLocationError() {
    $('#refueling_latitude').val('');
    $('#refueling_longitude').val('');
    setLocationStatus('error');
}

function getLocation() {
    if (!window.navigator.geolocation) {
        setLocationStatus('unsupported');
        return;
    }

    setLocationStatus('pending');
    window.navigator.geolocation.getCurrentPosition(setPosition, setLocationError, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000,
    });
}

function getSelectedBikeData($bikeField) {
    const option = $bikeField.find(':selected');

    if (option.length === 0 || !option.val()) {
        return null;
    }

    const lastRefuelingOdometer = parseInteger(option.data('lastRefuelingOdometer'));
    const purchaseOdometer = parseInteger(option.data('purchaseOdometer'));
    const currentOdometer = parseInteger(option.data('currentOdometer'));
    const lastRefuelingDateRaw = option.data('lastRefuelingDate');

    return {
        currentOdometer,
        purchaseOdometer,
        lastRefuelingOdometer,
        lastRefuelingDate: lastRefuelingDateRaw ? moment(lastRefuelingDateRaw) : null,
    };
}

function getSelectedDate($dateField) {
    const rawValue = $dateField.val();

    if (!rawValue) {
        return null;
    }

    const parsedDate = moment(rawValue, ['DD. MM. YYYY', moment.ISO_8601], true);

    return parsedDate.isValid() ? parsedDate : null;
}

function isRelativeModeAvailable(bikeData, selectedDate, isContinuumEnabled) {
    if (!bikeData || !isContinuumEnabled) {
        return false;
    }

    if (bikeData.lastRefuelingOdometer === null) {
        return true;
    }

    if (!bikeData.lastRefuelingDate || !selectedDate) {
        return true;
    }

    return !selectedDate.isBefore(bikeData.lastRefuelingDate, 'day');
}

function getRelativeBaseline(bikeData) {
    if (!bikeData) {
        return null;
    }

    if (bikeData.lastRefuelingOdometer !== null) {
        return {
            value: bikeData.lastRefuelingOdometer,
            source: 'lastRefueling',
        };
    }

    return {
        value: bikeData.purchaseOdometer,
        source: 'purchase',
    };
}

function getMinimumOdometer(bikeData, selectedDate) {
    if (!bikeData) {
        return null;
    }

    let minimum = bikeData.purchaseOdometer;

    if (
        bikeData.lastRefuelingOdometer !== null
        && (!bikeData.lastRefuelingDate || !selectedDate || !selectedDate.isBefore(bikeData.lastRefuelingDate, 'day'))
    ) {
        minimum = Math.max(minimum, bikeData.lastRefuelingOdometer);
    }

    return minimum;
}

function setInlineFeedback($feedback, $field, message) {
    if (!message) {
        $feedback.prop('hidden', true).text('');
        $field.removeClass('is-invalid');
        return;
    }

    $feedback.prop('hidden', false).text(message);
    $field.addClass('is-invalid');
}

$(function () {
    const $datePicker = $('#datetimepicker1');
    const $dateField = $('#refueling_datetime');
    const $bikeField = $('#refueling_bike');
    const $odometerField = $('#refueling_odometer');
    const $relativeField = $('#refueling-relative-mileage');
    const $continuumField = $('#refueling_isNotBreakingContinuum');
    const $assistant = $('#refueling-mileage-assistant');
    const $assistantSummary = $assistant.find('.refueling-mileage-assistant__summary');
    const $odometerHint = $('#refueling-odometer-hint');
    const $odometerFeedback = $('#refueling-odometer-feedback');
    const $relativeHint = $('#refueling-relative-hint');
    const $relativeFeedback = $('#refueling-relative-feedback');

    let isSyncing = false;

    function renderAssistant() {
        const bikeData = getSelectedBikeData($bikeField);
        const selectedDate = getSelectedDate($dateField);
        const isContinuumEnabled = $continuumField.is(':checked');
        const relativeModeAvailable = isRelativeModeAvailable(bikeData, selectedDate, isContinuumEnabled);
        const relativeBaseline = getRelativeBaseline(bikeData);

        if (!bikeData) {
            $assistantSummary.text($assistant.data('selectBikeLabel'));
            $odometerHint.text($assistant.data('selectBikeLabel'));
            $relativeHint.text($assistant.data('relativeDisabledLabel'));
            $relativeField.prop('disabled', true).val('');
            $odometerField.removeAttr('min');
            $odometerField[0].setCustomValidity('');
            setInlineFeedback($odometerFeedback, $odometerField, '');
            setInlineFeedback($relativeFeedback, $relativeField, '');
            return;
        }

        const minimumOdometer = getMinimumOdometer(bikeData, selectedDate);
        const baselineLabel = relativeBaseline && relativeBaseline.source === 'lastRefueling'
            ? $assistant.data('baselineLastRefuelingLabel')
            : $assistant.data('baselinePurchaseLabel');

        $assistantSummary.text(
            `${$assistant.data('currentOdometerLabel')} ${formatInteger(bikeData.currentOdometer)} km. `
            + `${$assistant.data('relativeBaselineLabel')} ${formatInteger(relativeBaseline.value)} km (${baselineLabel}).`
        );

        if (minimumOdometer !== null) {
            $odometerField.attr('min', minimumOdometer);
        }

        if (!isContinuumEnabled) {
            $relativeField.prop('disabled', true).val('');
            $relativeHint.text($assistant.data('relativeDisabledContinuumLabel'));
            setInlineFeedback($relativeFeedback, $relativeField, '');
            return;
        }

        if (!relativeModeAvailable) {
            $relativeField.prop('disabled', true).val('');
            $relativeHint.text($assistant.data('relativeDisabledLabel'));
            setInlineFeedback($relativeFeedback, $relativeField, '');
            return;
        }

        $relativeField.prop('disabled', false);
        $relativeHint.text(
            `${$assistant.data('relativeReadyLabel')} ${formatInteger(relativeBaseline.value)} km (${baselineLabel}).`
        );
    }

    function syncMileage(source) {
        if (isSyncing) {
            return;
        }

        const bikeData = getSelectedBikeData($bikeField);
        const selectedDate = getSelectedDate($dateField);
        const isContinuumEnabled = $continuumField.is(':checked');
        const relativeModeAvailable = isRelativeModeAvailable(bikeData, selectedDate, isContinuumEnabled);
        const relativeBaseline = getRelativeBaseline(bikeData);

        if (!bikeData || !relativeModeAvailable || !relativeBaseline) {
            return;
        }

        isSyncing = true;

        if (source === 'relative') {
            const relativeValue = parseInteger($relativeField.val());

            if (relativeValue === null) {
                $odometerField.val('');
            } else {
                $odometerField.val(relativeBaseline.value + relativeValue);
            }
        } else if (source === 'odometer') {
            const odometerValue = parseInteger($odometerField.val());

            if (odometerValue === null) {
                $relativeField.val('');
            } else if (odometerValue < relativeBaseline.value) {
                $relativeField.val('');
            } else {
                $relativeField.val(odometerValue - relativeBaseline.value);
            }
        }

        isSyncing = false;
    }

    function validateOdometer() {
        const bikeData = getSelectedBikeData($bikeField);
        const selectedDate = getSelectedDate($dateField);
        const odometerValue = parseInteger($odometerField.val());

        if (!bikeData || odometerValue === null) {
            $odometerField[0].setCustomValidity('');
            setInlineFeedback($odometerFeedback, $odometerField, '');
            return;
        }

        const minimumOdometer = getMinimumOdometer(bikeData, selectedDate);

        if (minimumOdometer !== null && odometerValue < minimumOdometer) {
            const message = `${$assistant.data('odometerTooLowLabel')} ${formatInteger(minimumOdometer)} km.`;
            $odometerField[0].setCustomValidity(message);
            setInlineFeedback($odometerFeedback, $odometerField, message);
            $odometerHint.text(`${$assistant.data('odometerMinimumLabel')} ${formatInteger(minimumOdometer)} km.`);
            return;
        }

        $odometerField[0].setCustomValidity('');
        setInlineFeedback($odometerFeedback, $odometerField, '');

        if (minimumOdometer !== null) {
            $odometerHint.text(`${$assistant.data('odometerValidLabel')} ${formatInteger(minimumOdometer)} km.`);
        }
    }

    $datePicker.datetimepicker(
        createDateTimePickerOptions({
            format: 'DD. MM. YYYY',
        })
    );
    $datePicker.data('DateTimePicker').date(moment());

    $('#refresh-location').on('click', getLocation);
    $bikeField.on('change', () => {
        renderAssistant();
        syncMileage('odometer');
        validateOdometer();
    });
    $dateField.on('change blur', () => {
        renderAssistant();
        validateOdometer();
    });
    $continuumField.on('change', () => {
        renderAssistant();
        validateOdometer();
    });
    $odometerField.on('input', () => {
        syncMileage('odometer');
        validateOdometer();
    });
    $relativeField.on('input', () => {
        syncMileage('relative');
        validateOdometer();
    });

    renderAssistant();
    validateOdometer();
    getLocation();
});
