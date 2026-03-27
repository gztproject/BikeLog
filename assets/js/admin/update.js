// Handling the update modal.
$('#btn-check-updates').on('click', function(event) {
    $confirm = $('#updateModal');

    $('.modal-body').html("Checking for updates...");
    $confirm.modal('show');

    CheckUpdates();

});

$('#btn-do-update').on('click', function(event) {
    $version = $('#btn-do-update').attr('data-version');
    $.get("update/do?version=" + $version, function(data) {
        $('.modal-body').html = data.response;
        CheckUpdates();
    });
});

function CheckUpdates() {
    $.get('https://api.github.com/repos/gztproject/BikeLog/releases/latest', function(data) {
        $newVersion = data.tag_name;
        $newUrl = data.html_url;
        $changes = data.body;
        checkDone();
    });

    $.get("update/check", function(data) {
        $currentVersion = data.current_version;        
        checkDone();
    });

    function checkDone() {
        if (typeof $currentVersion !== 'undefined' && typeof $newVersion !== 'undefined') {
            $('#currentA').html($currentVersion);
            $('#newA').html($newVersion);
            $('#newA').prop('href', $newUrl);
            $('#changes').html($changes.replaceAll("\n", "<br>"));
            if (isDev()) {
                $('#btn-do-update').attr('disabled', true);
                $('#btn-do-update').attr('data-toggle', "tooltip");
                $('#btn-do-update').attr('title', "Can\'t update in DEV env.");
            }
            else if (IsNewer($currentVersion, $newVersion)) {
                $('#btn-do-update').attr('disabled', false);
                $('#btn-do-update').attr('data-version', $newVersion);
            }
            else
                $('#btn-do-update').attr('disabled', true);
        }
    }

    $(".modal-body").html("<h4>Current version:</h4> <span id=\"currentA\"></span>" +
        "</br><h4>New verion:</h4> <a href=\"#\" target=\"_blank\" id=\"newA\"></a>" + 
        "</br></br><h4>Changes:</h4> <span id=\"changes\"></span>");
}

function IsNewer($current, $new) {
    $curVer = $current.split('-')[0].split('.');
    $newVer = $new.split('-')[0].split('.');
    if ($newVer[0] > $curVer[0])
        return true;
    else if ($newVer[1] > $curVer[1])
        return true;
    else if ($newVer[2] > $curVer[2])
        return true;

    return false;
}

function isDev() {
    // Get class list string
    var classList = $("header").attr("class");
    //alert(classList);
    // Creating class array by splitting class list string
    var classArr = classList.split(/\s+/);
    if (classArr.includes("dev")) {
        return true;
    }
    return false;
}