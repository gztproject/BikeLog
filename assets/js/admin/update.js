// Handling the update modal.
$('#btn-check-updates').on('click', function (event) {
    $confirm = $('#updateModal');

    $('.modal-body').html("Checking for updates...");
    $confirm.modal('show');

    $.get("update/check", function (data) {
        $(".modal-body").html("Current version: " + data.current_version + "</br> New verion: "+data.new_version);
        if(data.update_available){ 
            alert("Update available!"); 
            $("#btn-do-update").prop( "disabled", false );
        } 
    });


    
});