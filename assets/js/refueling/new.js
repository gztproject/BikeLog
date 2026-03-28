import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

const { createDateTimePickerOptions } = require('../common/dateTimePickerOptions');

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

$(function () {
    $('#datetimepicker1').datetimepicker(
        createDateTimePickerOptions({
            format: 'DD. MM. YYYY',
        })
    );
    $('#datetimepicker1').data("DateTimePicker").date(moment());

    $('#refresh-location').on('click', getLocation);
    getLocation();
});
