import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';
const { createDateTimePickerOptions } = require('../dateTimePickerOptions');
const { buildFilterUrl, parseFilterState } = require('./dateBikeFilterState');

function redirectWithFilters(updates) {
    document.location = buildFilterUrl(document.location.href, {
        ...updates,
        page: undefined,
    });
}

$(function () {
    const initialState = parseFilterState(window.location.search);

    if ($('#dateFieldYear').length) {
        $('#dateFieldYear').datetimepicker(
            createDateTimePickerOptions({
                format: 'YYYY',
                showClear: true,
            })
        );
    }

    $('#dateFieldFrom').datetimepicker(
        createDateTimePickerOptions({
            format: 'DD. MM. YYYY',
            showTodayButton: true,
            showClear: true,
        })
    );

    $('#dateFieldTo').datetimepicker(
        createDateTimePickerOptions({
            format: 'DD. MM. YYYY',
            showTodayButton: true,
            showClear: true,
            useCurrent: false,
        })
    );

    $('#dateFieldFrom').on('dp.change', function onDateFromChange(e) {
        $('#dateFieldTo').data("DateTimePicker").minDate(e.date);
    });

    if (initialState.year && $('#dateFieldYear').length) {
        $('#dateFieldYear').data('DateTimePicker').date(moment(initialState.year, 'YYYY'));
    }
    if (initialState.dateFrom !== undefined) {
        $('#dateFieldFrom').data('DateTimePicker').date(moment.unix(initialState.dateFrom));
    }
    if (initialState.dateTo !== undefined) {
        $('#dateFieldTo').data('DateTimePicker').date(moment.unix(initialState.dateTo));
    }

    $.getJSON('/dashboard/bike/list', function onBikeListLoaded(data) {
        $("#bikePicker").append($('<option>', {
            value: '',
            text: "*",
        }));
        data[0].data.bikes.forEach(function addBikeOption(el) {
            $("#bikePicker").append($('<option>', {
                value: el.id,
                text: el.name,
            }));
        });

        if (initialState.bike) {
            $('#bikePicker').val(initialState.bike);
        }
    });
});

$(document).ready(function () {
    $('#bikePicker').on('change', function onBikeChange() {
        redirectWithFilters({
            bike: $('#bikePicker').find(':selected').val() || undefined,
        });        
    });

    $('#dateFieldYear').on('dp.change', function onYearChange(e) {
        if (e.oldDate || e.date) {
            redirectWithFilters({
                dateFrom: e.date && e.date.isValid() ? moment(e.date).startOf('year').format('X') : undefined,
                dateTo: e.date && e.date.isValid() ? moment(e.date).endOf('year').format('X') : undefined,
                year: e.date && e.date.isValid() ? moment(e.date).format('YYYY') : undefined,
            });
        }
    });

    $('#dateFieldFrom').on('dp.change', function onFilterFromChange(e) {
        if (e.oldDate || e.date) {
            redirectWithFilters({
                dateFrom: e.date && e.date.isValid() ? moment(e.date).startOf('day').format('X') : undefined,
                year: undefined,
            });
        }
    });

    $('#dateFieldTo').on('dp.change', function onFilterToChange(e) {
        if (e.oldDate || e.date) {
            redirectWithFilters({
                dateTo: e.date && e.date.isValid() ? moment(e.date).endOf('day').format('X') : undefined,
                year: undefined,
            });
        }
    });

    $('#clearFilterBtn').on('click', function onClearFilters() {
        const url = location.href.replace(location.search, '');
        document.location = url;
    });
});
