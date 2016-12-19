$(document).mousemove(function() {
    $.post("", {mmc: true});
    $(document).off("mousemove");
});
