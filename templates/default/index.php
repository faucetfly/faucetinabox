<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $data["name"]; ?></title>
<link rel="stylesheet" href="//cdn.jsdelivr.net/bootstrap/3.3.4/css/bootstrap.min.css">
<script src="//cdn.jsdelivr.net/jquery/2.1.4/jquery.min.js"></script>
<script type="text/javascript" src="libs/mmc.js"></script>
<script src="//cdn.jsdelivr.net/bootstrap/3.3.4/js/bootstrap.min.js"></script>
<?php
switch($data["custom_palette"]):
case 'amelia':
case 'cerulean':
case 'cyborg':
case 'flatly':
case 'journal':
case 'lumen':
case 'readable':
case 'simplex':
case 'slate':
case 'spacelab':
case 'superhero':
case 'united':
case 'yeti':
?>
<link rel="stylesheet" href="templates/default/palettes/<?php echo $data["custom_palette"]; ?>.css">
<?php
break;
default:
/*
?>
<link rel="stylesheet" href="//cdn.jsdelivr.net/bootstrap/3.2.0/css/bootstrap-theme.min.css">
<?php
*/
break;
endswitch;
?>
<style type="text/css">
html{
    position: relative;
    min-height: 100%;
}
body .footer{
    position: absolute;
    bottom: 0px;
    padding: 5px 0;
}
.row > div{
    padding: 30px;
}
.bg-black{
    background: #000;
}
.bg-white{
    background: #fff;
}
.text-black{
    color: #000;
}
.text-white{
    color: #fff;
}
.admin_link{
    position: fixed;
    bottom: 0px;
    right: 0px;
    z-index: 2;
    text-shadow: 0px -1px 0px rgba(0,0,0,.5), 0px 1px 0px rgba(255,255,255,.5);
}

#recaptcha_area {
    margin: 0 auto;
}

#captchme_widget_div{
margin: 0 auto;
width: 315px;
}

#adcopy-outer {
    margin: 0 auto !important;
}

.g-recaptcha{
width: 304px;
margin: 0 auto;
}

.reklamper-widget-holder{
margin: auto;
}

<?php echo $data["custom_css"]; ?>
</style>
</head>
<body class=" <?php echo $data["custom_body_bg"] . ' ' . $data["custom_body_tx"]; ?>">
    <?php if(!empty($data["user_pages"])): ?>
    <nav class="navbar navbar-fixed navbar-default" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="./">
                    <?php echo $data["name"]; ?>
                </a>
            </div>
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                <?php foreach($data["user_pages"] as $page): ?>
                    <li><a href="?p=<?php echo $page["url_name"]; ?>"><?php echo $page["name"]; ?></a></li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-xs-12 <?php echo $data["custom_box_top_bg"] . ' ' . $data["custom_box_top_tx"]; ?>"><?php echo $data["custom_box_top"]; ?></div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-md-6 col-md-push-3 <?php echo $data["custom_main_box_bg"] . ' ' . $data["custom_main_box_tx"]; ?>">
                <?php if($data["page"] != 'user_page'): ?>
                <h1><?php echo $data["name"]; ?></h1>
                <h2><?php echo $data["short"]; ?></h2>
                <p class="alert alert-info">Balance: <?php echo $data["balance"]." ".$data["unit"]; ?></p>
                <p class="alert alert-success"><?php echo $data["rewards"]; ?> <?php echo $data['unit']; ?> every <?php echo $data["timer"]; ?> minutes.</p>
                <?php endif;    if($data["error"]) echo $data["error"]; ?>
                <?php if($data["safety_limits_end_time"]): ?>
                <p class="alert alert-warning">This faucet exceeded it's safety limits and may not payout now!</p>
                <?php endif; ?>
                <?php switch($data["page"]):
                        case "disabled": ?>
                    <p class="alert alert-danger">FAUCET DISABLED. Go to <a href="admin.php">admin page</a> and fill all required data!</p>
                <?php break; case "paid":
                        echo $data["paid"];
                        if($data["referral"]): ?>
                        Referral commission: <?php echo $data["referral"]; ?>%<br>
                        Reflink: <?php echo $data["reflink"]; ?>
                        <?php endif;
                      break; case "eligible": ?>
                    <form method="POST" class="form-horizontal" role="form">
                        <div class="form-group">
                            <input type="text" name="address" class="form-control" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">
                            <input type="checkbox" name="honeypot" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">
                            <label class="col-sm-4 col-md-offset-1 col-lg-3 control-label">Your address:</label>
                            <div class="col-sm-8 col-md-7" style="min-width: 270px;">
                            <input type="text" name="<?php echo $data["address_input_name"]; ?>" class="form-control" value="<?php echo $data["address"]; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <?php echo $data["captcha"]; ?>
                            <div class="text-center">
                                <?php
                                if (count($data['captcha_info']['available']) > 1) {
                                    foreach ($data['captcha_info']['available'] as $c) {
                                        if ($c == $data['captcha_info']['selected']) {
                                            echo '<b>' .$c. '</b> ';
                                        } else {
                                            echo '<a href="?cc='.$c.'">'.$c.'</a> ';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-sm-offset-4 col-sm-4">
                                <input type="submit" class="btn btn-primary btn-lg claim-button" value="Get reward!">
                            </div>
                        </div>
                    </form>
                <?php if ($data["reflink"]): ?>
				<blockquote class="text-left">
					<p>
						Reflink: <code><?php echo $data["reflink"]; ?></code>
					</p>
					<footer>Share this link with your friends and earn <?php echo $data["referral"]; ?>% referral commission</footer>
				</blockquote>
                <?php endif; ?>
                <?php break; case "visit_later": ?>
                    <p class="alert alert-info">You have to wait <?php echo $data["time_left"]; ?></p>
                <?php break; case "user_page": ?>
                <?php echo $data["user_page"]["html"]; ?>
                <?php break; endswitch; ?>
            </div>
            <div class="col-xs-6 col-md-3 col-md-pull-6 <?php echo $data["custom_box_left_bg"] . ' ' . $data["custom_box_left_tx"]; ?>"><?php echo $data["custom_box_left"]; ?></div>
            <div class="col-xs-6 col-md-3 <?php echo $data["custom_box_right_bg"] . ' ' . $data["custom_box_right_tx"]; ?>"><?php echo $data["custom_box_right"]; ?></div>
        </div>
        <div class="row">
            <div class="col-xs-12 <?php echo $data["custom_box_bottom_bg"] . ' ' . $data["custom_box_bottom_tx"]; ?>"><?php echo $data["custom_box_bottom"]; ?></div>
            <?php if(!$data['disable_admin_panel'] && $data["custom_admin_link"] == 'true'): ?>
            <div class="admin_link"><a href="admin.php">Admin Panel</a></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer text-center col-xs-12 <?php echo $data["custom_footer_bg"] . ' ' . $data["custom_footer_tx"]; ?>">
        <?php echo $data["custom_footer"]; ?>
    </div>
    <?php if($data['button_timer']): ?>
    <script type="text/javascript" src="libs/button-timer.js"></script>
    <script> startTimer(<?php echo $data['button_timer']; ?>); </script>
    <?php endif; ?>
    <?php if($data['block_adblock'] == 'on'): ?>
    <script type="text/javascript" src="libs/advertisement.js"></script>
    <script type="text/javascript" src="libs/check.js"></script>
    <?php endif; ?>
</body>
</html>
