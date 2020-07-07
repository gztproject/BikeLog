import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#refueling_datetime').datetimepicker({
        locale: 'sl',
        format: 'dd. mm. yyyy',
        icons: {
                    time: "fa fa-clock",
                    date: "fa fa-calendar",
                    up: "fa fa-arrow-up",
                    down: "fa fa-arrow-down",
                    previous: "fa fa-arrow-left",
                    next: "fa fa-arrow-right",
                    today:"fa fa-calendar-day",
                    clear:"fa fa-backspace",
                    close:"fa fa-times"
                }
    });    
});

jQuery(document).ready(function() { 

    $('#refueling_datetime').data("DateTimePicker").date(moment());
    
});