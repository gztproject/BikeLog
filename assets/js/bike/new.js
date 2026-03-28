import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

const { createDateTimePickerOptions } = require('../common/dateTimePickerOptions');

$(function () {
    $('#bike_purchaseDate').datetimepicker(
        createDateTimePickerOptions({
            format: 'DD. MM. YYYY',
        })
    );

    $('#bike_vin').on('input', function onVinInput() {
        this.value = this.value.toUpperCase().replace(/\s+/g, '');
    });
});
