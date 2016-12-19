<?php

function getTemplateOptions($sql, $template) {
$options = <<<OPTIONS
<div class="form-group">
    <label class="control-label" for="custom_left_ad_slot">Left ad slot:</label>
    <textarea class="form-control" rows="3" name="custom_left_ad_slot_$template"><:: left ::></textarea>
</div>
<div class="form-group">
    <label class="control-label" for="custom_right_ad_slot">Right ad slot:</label>
    <textarea class="form-control" rows="3" name="custom_right_ad_slot_$template"><:: right ::></textarea>
</div>
OPTIONS;
    
    $q = $sql->prepare("SELECT value FROM Faucetinabox_Settings WHERE name = ?");
    $q->execute(array("custom_left_ad_slot_$template"));
    $left = $q->fetch();
    if($left)
        $left = htmlspecialchars($left[0]);
    else
        $left = "";


    $q = $sql->prepare("SELECT value FROM Faucetinabox_Settings WHERE name = ?");
    $q->execute(array("custom_right_ad_slot_$template"));
    $right = $q->fetch();
    if($right)
        $right = htmlspecialchars($right[0]);
    else
        $right = "";

    return str_replace(array("<:: left ::>", "<:: right ::>"), array($left, $right), $options);
}
