import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';
import withQuery from 'with-query';
import queryString from 'query-string';

$(function() {

    $('#dateFieldYear').datetimepicker({
        format: 'YYYY',
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
        },
        showClear: true,
        locale:'sl',   
    });

    $('#dateFieldFrom').datetimepicker({
        format: 'DD. MM. YYYY',
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
        },
        showTodayButton: true,
        showClear: true,
        locale:'sl',
        //defaultDate: moment().startOf('month'),

    });

    $('#dateFieldTo').datetimepicker({
        format: 'DD. MM. YYYY',
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
        },
        showTodayButton: true,
        showClear: true,
        locale:'sl',
        useCurrent: false //Important! See issue #1075   
    });    
    
    $("#dateFieldFrom").on("dp.change", function (e) {
        $('#dateFieldTo').data("DateTimePicker").minDate(e.date);
    });    

    if(typeof queryString.parse(location.search)['year'] !== 'undefined')
        $('#dateFieldYear').data("DateTimePicker").date(moment.year = (queryString.parse(location.search)['year']));
    if(typeof queryString.parse(location.search)['dateFrom'] !== 'undefined')
        $('#dateFieldFrom').data("DateTimePicker").date(moment.unix(queryString.parse(location.search)['dateFrom']));
    if(typeof queryString.parse(location.search)['dateTo'] !== 'undefined')
        $('#dateFieldTo').data("DateTimePicker").date(moment.unix(queryString.parse(location.search)['dateTo']));

    $.getJSON("/dashboard/bike/list", function(data, status){
        $("#bikePicker").append($('<option>', {
            value: "",
            text: "*",
        }));
        data[0].data.bikes.forEach(function(el){
            $("#bikePicker").append($('<option>', {
                value: el.id,
                text: el.name,
            }));
        });
        console.log("Loaded bikes"); 
        var orgId = queryString.parse(location.search)['bike'];
        var select = $('#bikePicker');
        if(typeof orgId !== 'undefined')
        {
            $('#bikePicker').val(orgId);
            console.log("Selected bike " + orgId);
        }  
    });
      
  
});

$( document ).ready(function(){    
    
    console.log("Binding event"); 
    $('#bikePicker').on('change', function() {
        var url = withQuery(document.location.href,
        {
            bike: $('#bikePicker').find(":selected").val(),
            page: undefined                
        });        
        document.location = url;        
    });

    $("#dateFieldYear").on("dp.change", function (e) {        
        if(e.oldDate){
            var url = withQuery(document.location.href,{
                dateFrom: e.date.isValid?moment(e.date).startOf('year').format('X'):undefined,
                dateTo: e.date.isValid?moment(e.date).endOf('year').format('X'):undefined,
                year: e.date.isValid?moment(e.date).format('YYYY'):undefined,
                page: undefined
            });        
            document.location = url;
        }
    });

    $('#dateFieldFrom').on('dp.change', function(e){
        if(e.oldDate){
            var url = withQuery(document.location.href,{
                dateFrom: e.date.isValid?moment(e.date).startOf('day').format('X'):undefined, 
                year: undefined,               
                page: undefined
            });        
            document.location = url;
        }

    });

    $('#dateFieldTo').on('dp.change', function(e){
        if(e.oldDate){
            var url = withQuery(document.location.href,{
                dateTo: e.date.isValid?moment(e.date).endOf('day').format('X'):undefined,
                year: undefined,
                page: undefined
            });        
            document.location = url;
        }
    });
    

    $('#clearFilterBtn').on('click', function(e){
        var url = location.href.replace(location.search, "");
        document.location = url;
    });
});

