import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#maintenance_date').datetimepicker({
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

var $collectionHolder;
var $addMaintenanceTaskButton = $('<tr class="table-primary"><td colspan="7"><a id="add-maintenance-task" class="btn btn-sm btn-block btn-success"><i class="fa fa-plus" aria-hidden="true"></i></a></td></tr>');


jQuery(document).ready(function() {
    // Get the ul that holds the collection of tags
    $collectionHolder = $('tbody.maintenanceTasks');
    
    $collectionHolder.data('index', $collectionHolder.find('tr').length);   

    if($collectionHolder.find('tr').length == 0)
    {         
        addMaintenanceTaskForm($collectionHolder, $addMaintenanceTaskButton, 1);
    }
    else
    {        
        for(var i=0;i<$collectionHolder.find('tr').length;i++)
        {        
            $('#remove-maintenance-task-' + i).on('click', function(e) {   
                var index = e.target.id.split('-')[3];      
                removeMaintenanceTaskForm($collectionHolder, index); 
            });
        }
        $collectionHolder.append($addMaintenanceTaskButton);
        setTotalPrice(calculateTotal());
    }
        
    $('#add-maintenance-task').on('click', function() {
        // add a new tag form (see next code block)
        addMaintenanceTaskForm($collectionHolder, $addMaintenanceTaskButton, 1); 

    });    
});


function addMaintenanceTaskForm($collectionHolder, $addRemoveMaintenanceTaskButtons, $number) {
    console.log("Adding new maintenance row.");
    for(var i=0; i<$number;i++){
        // Get the data-prototype explained earlier
        var prototype = $collectionHolder.data('prototype');

        // get the new index
        var index = $collectionHolder.data('index')*1;

        var newForm = prototype;
        
        // Replace '__name__' in the prototype's HTML to
        // instead be a number based on how many items we have
        newForm = newForm.replace(/__name__/g, index);
        
        // increase the index with one for the next item
        $collectionHolder.data('index', index + 1);
        

        // Display the form in the page in an li, before the "Add a tag" link li
        var $newFormLi = $('<tr class="maintenance-task-tr-' + index + '">'+
        '<td class="taskInput">' + $('#maintenance_maintenanceTaskCommands_' + index + '_task', newForm).parent().html() + '</td>'+
        '<td class="costInput" data-item-index="' + index + '">' + $('#maintenance_maintenanceTaskCommands_' + index + '_cost' ,newForm).parent().html() + '</td>'+        
        '<td class="removeBtn"><a id="remove-maintenance-task-'+ index +'" class="btn btn-sm btn-block btn-danger removeBtn"><i class="fa fa-minus" aria-hidden="true"></i></a></td></tr>');        
        
        $collectionHolder.append($newFormLi);
        $collectionHolder.append($addRemoveMaintenanceTaskButtons);

        $('#remove-maintenance-task-'+index).on('click', function() {        
            removeMaintenanceTaskForm($collectionHolder, index); 
        });
    }    
}

function removeMaintenanceTaskForm($collectionHolder, index) {
    console.log("Removing maintenance row #"+index+".");
    if(index < 1)
    {
        alert("Can't delete last item!");
        return;
    }
    $collectionHolder.data('index', index);
    $('.maintenance-task-tr-' + index, $collectionHolder).remove();    
}