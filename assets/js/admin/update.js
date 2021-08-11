// Handling the update modal.
$('#btn-check-updates').on('click', function (event) {
    $confirm = $('#updateModal');

    $('.modal-body').html("Checking for updates...");
    $confirm.modal('show');
      
    $.get('https://api.github.com/repos/gztproject/BikeLog/releases/latest', function (data) {
       $newVersion = data.tag_name;
       $newUrl = data.html_url;
       checkDone();
    });

    $.get("update/check", function (data) {
        $currentVersion = data.current_version;
        checkDone();
    });

    function checkDone()
    {
        if(typeof $currentVersion !== 'undefined' && typeof $newVersion !== 'undefined')
        {
            $('#currentA').html($currentVersion); 
            $('#newA').html($newVersion);
            $('#newA').prop('href', $newUrl);
            if($currentVersion < $newVersion)
            {
                $('#btn-do-update').prop('aria-disabled', false);
                $('#btn-do-update').removeClass('.disabled');

                var _href = $("#btn-do-update").attr("href");
                $("#btn-do-update").attr("href", _href + '?' + $newUrl);
            }
        } 
    }

    $(".modal-body").html("Current version: <span id=\"currentA\"></span>" +
        "</br> New verion: <a href=\"#\" target=\"_blank\" id=\"newA\"></a>");

});