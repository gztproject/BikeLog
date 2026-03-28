$(".maintenance-main").click(function() {
    
    var expanded = $(this).attr("class").split(/\s+/).includes("collapsed");
    $(this).find(".arrow").toggleClass(function() {
        if (expanded) {
            return "fa-caret-up";
        } else {
            return "fa-caret-down";
        }
    });

});