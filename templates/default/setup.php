<?php

function getTemplateOptions($sql, $template) {
$options = <<<OPTIONS
<script type="text/javascript">
$(function() {
    function load_palette(){
        var palette = $('.current-palette').attr('title');
        var href='';
        if(palette == 'default'){
            href='data:text/css;base64,IA==';
        }else{
            var p1 = 'templates/default/palettes/';
            var p2 = '.css';
            href = p1 + palette + p2;
        }
        $("#palette-css").attr({"href" : href});
    }
    $('#palette_picker').selectpicker();
    $('#palette_picker').selectpicker('setStyle', 'current-palette', 'add');
    load_palette();
    $('.current-palette').on("DOMNodeInserted DOMNodeRemoved DOMSubtreeModified change", load_palette);

    function do_picker_selection(saver){
        var saver_value = $(saver).attr('value');
        var chooser = $(saver).parent().children('.picker-chooser');
        if(saver_value != ''){
            var finder = '.' + saver_value;
            chooser.children().removeClass('active');
            chooser.children(finder).addClass('active');
            if(saver_value.substr(0,5) != 'text-'){
                $(saver).parent().parent().children().last().children().first().attr('class', 'btn-group prev-box picker-chooser').addClass(saver_value);
			}
        }else{
            if(saver_value.substr(0,5) != 'text-'){
                chooser.children('.transparent').addClass('active');
            }else{
                chooser.children('.text-black').addClass('active');
            }
        }
    }

    $('.picker-save').each( function() { // load already selected pickers on page load
        do_picker_selection(this);
    });
    $('.picker-chooser .btn').on("click", function(){ // when you change an element
        var bg = $(this).attr("class").replace('btn ', '');
        var saver = $(this).parent().parent().children('.picker-save');
        saver.attr('value', bg);
        do_picker_selection(saver);
    });
});
</script>
<div class="form-group">
    <div class="col-xs-12 col-sm-6 col-lg-4">
        <label class="control-label">Show link to admin panel on front page?</label>
    </div>
    <div class="col-xs-3" style="padding-top: 5px;">
        <:: admin_link_radio ::>
    </div>
</div>
<div class="form-group">
    <div class="col-xs-12 col-sm-2 col-lg-1">
        <label class="control-label">Palette:</label>
    </div>
    <div class="col-xs-3">
        <select id="palette_picker" class="selectpicker" name="custom_palette_default"><:: custom_palettes ::></select>
    </div>
    <div class="col-xs-4">
        <div class="btn-group prev-box">
            <span class="btn bg-primary"></span><span class="btn bg-success"></span><span class="btn bg-info"></span><span class="btn bg-warning"></span><span class="btn bg-danger"></span>
        </div>
    </div>
</div>

<div class="form-group">
    <label class="control-label">Template defined pickers:</label>
</div>

<table class="table table-hover">
<thead>
<tr><td>Item</td><td>Background</td><td>Text color</td></tr>
</thead>
<tbody>
<:: pickers ::>
</tbody>
</table>

<div class="form-group">
    <label class="control-label">Template defined boxes:</label>
    <div><em>Hint: you can enter HTML and CSS here as well!</em></div>
    <br>
    <:: boxes ::>
</div>
OPTIONS;

// z tego budowane jest <:: boxes ::> w $admin_template
$box_template = <<<TEMPLATE
<:: name ::>: <textarea class="form-control" rows="3" name="<:: field_name ::>"><:: content ::></textarea>
TEMPLATE;

$picker_template = <<<TEMPLATE
<tr>
    <td>
        <label class="picker-label"><:: name ::>:</label>
    </td>
    <td>
        <div class="btn-group prev-box picker-chooser">
            <span class="btn transparent active" title="transparent (or palette default)"></span><span class="btn bg-primary"></span><span class="btn bg-success"></span><span class="btn bg-info"></span><span class="btn bg-warning"></span><span class="btn bg-white" title="white"></span><span class="btn bg-black" title="black"></span><span class="btn bg-danger"></span>
        </div>
        <input class="picker-save" type="hidden" name="<:: field_name ::>" value="<:: content ::>">
    </td>
    <td>
        <div class="btn-group prev-box picker-chooser">
            <span class="btn text-black" title="black">black</span><span class="btn text-primary">main</span><span class="btn text-success">green</span><span class="btn text-info">blue</span><span class="btn text-warning">yellow</span><span class="btn text-white" title="white">white</span><span class="btn text-danger">red</span>
        </div>
        <input class="picker-save" type="hidden" name="<:: field_name_2 ::>" value="<:: content_2 ::>">
    </td>
</tr>
TEMPLATE;

    function pretty_box_name($box){
        return ucfirst(
            trim(
                str_replace(array('custom', 'box'), '',
                    trim(
                        str_replace('_', ' ', $box)
                    )
                )
            ).' box'
        );
    }

    preg_match_all('/\$data\[([\'"])(custom_(?:(?!\1).)*)\1\]/', file_get_contents("templates/$template/index.php"), $matches);
    $boxes = '';
    $pickers = '';
    $q = $sql->prepare("SELECT value FROM Faucetinabox_Settings WHERE name = ?");
    foreach(array_unique($matches[2]) as $box) {
        $q->execute(array("{$box}_$template"));
        $v = $q->fetch();
        if($v)
            $v = htmlspecialchars($v[0]);
        else
            $v = "";
        if($box == 'custom_palette'){ // custom palette
            $current_custom_palette = $v;
        }elseif($box == 'custom_admin_link'){ // admin link
                $is_admin_link_true = ($v == 'true') ? "selected" : "";
                $is_admin_link_false = ($v != 'true') ? "selected" : "";
        }elseif(substr($box, -3) == '_bg'){ // custom classes, hide box, show picker
            $pickers .= str_replace(array("<:: name ::>", "<:: content ::>", "<:: field_name ::>"), array(pretty_box_name(substr($box, 0, -3)), $v, "{$box}_$template"), $picker_template);
        }elseif(substr($box, -3) == '_tx'){ // text colors?
            $pickers = str_replace(array("<:: content_2 ::>", "<:: field_name_2 ::>"), array($v, "{$box}_$template"), $pickers);
        }else{
            $boxes .= str_replace(array("<:: name ::>", "<:: content ::>", "<:: field_name ::>"), array(pretty_box_name($box), $v, "{$box}_$template"), $box_template);
        }
    }

    $options = str_replace("<:: boxes ::>", $boxes, $options);
    $options = str_replace("<:: pickers ::>", $pickers, $options);

    $admin_link_radio = '<select id="custom_admin_link_'.$template.'" name="custom_admin_link_'.$template.'" class="selectpicker"><option value="true" ' .$is_admin_link_true. '>Yes</option><option value="false" ' .$is_admin_link_false. '>No</option></select>';
    $options = str_replace("<:: admin_link_radio ::>", $admin_link_radio, $options);

    $custom_palettes = '';
    $custom_palettes_array = array('default', 'amelia', 'cerulean', 'cyborg', 'flatly', 'journal', 'lumen', 'readable', 'simplex', 'slate', 'spacelab', 'superhero', 'united', 'yeti');
    foreach($custom_palettes_array as $custom_palette) {
        if($custom_palette == $current_custom_palette) {
            $custom_palettes .= "<option selected>$custom_palette</option>";
        } else {
            $custom_palettes .= "<option>$custom_palette</option>";
        }
    }
    $options = str_replace("<:: custom_palettes ::>", $custom_palettes, $options);

    return $options;
}
