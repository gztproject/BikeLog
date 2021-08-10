// Handling the update modal.
$('#btn-check-updates').on('click', function (event) {
    $confirm = $('#updateModal');
   
    $('.modal-body').html("Checking for updates...");
    
    $confirm.modal('show');
});