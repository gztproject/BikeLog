import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function () {
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
            today: "fa fa-calendar-day",
            clear: "fa fa-backspace",
            close: "fa fa-times"
        }
    });
});


var x = document.getElementById("demo");

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
    }
    else {
        x.innerHTML = "Geolocation is not supported by this browser.";
    }
}

function showPosition(position) {
    x.innerHTML = "Latitude: " + position.coords.latitude +
        "<br>Longitude: " + position.coords.longitude;
}
jQuery(document).ready(function () {

    $('#refueling_datetime').data("DateTimePicker").date(moment());

    getLocation();

});