<?php

/*
 * Faucet in a BOX
 * https://faucetinabox.com/
 *
 * Copyright (c) 2014-2016 LiveHome Sp. z o. o.
 *
 * This file is part of Faucet in a BOX.
 *
 * Faucet in a BOX is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Faucet in a BOX is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Faucet in a BOX.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once("script/common.php");
require_once("script/admin_templates.php");
require_once("libs/coolphpcaptcha.php");

function regenerate_csrf_token() {
    global $session_prefix;
    $_SESSION["$session_prefix-csrftoken"] = base64_encode(openssl_random_pseudo_bytes(20));
}

function get_csrf_token() {
    global $session_prefix;
    return "<input type=\"hidden\" name=\"csrftoken\" value=\"". $_SESSION["$session_prefix-csrftoken"]. "\">";
}

function checkOneclickUpdatePossible($response) {
    global $version;

    $oneclick_update_possible = false;
    if (!empty($response['changelog'][$version]['hashes'])) {
        $hashes = $response['changelog'][$version]['hashes'];
        $oneclick_update_possible = class_exists("ZipArchive");
        foreach ($hashes as $file => $hash)  {
            if (strpos($file, 'templates/') === 0)
                continue;
            $oneclick_update_possible &=
                is_writable($file) &&
                sha1_file($file) === $hash;
        }
    }
    return $oneclick_update_possible;
}

function setNewPass() {
    global $sql;
    $alphabet = str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890');
    $password = '';
    for($i = 0; $i < 15; $i++)
        $password .= $alphabet[array_rand($alphabet)];
    $hash = crypt($password);
    $sql->query("REPLACE INTO Faucetinabox_Settings VALUES ('password', '$hash')");
    return $password;
}

$template_updates = array(
    array(
        "test" => "/address_input_name/",
        "message" => "Name of the address field has to be updated. Please follow <a href='https://bitcointalk.org/index.php?topic=1094930.msg12231246#msg12231246'>these instructions</a>"
    ),
    array(
        "test" => "/libs\/mmc\.js/",
        "message" => "Add <code>".htmlspecialchars('<script type="text/javascript" src="libs/mmc.js"></script>')."</code> after jQuery in <code>&lt;head&gt;</code> section."
    ),
    array(
        "test" => "/honeypot/",
        "message" => "Add <code><pre>".htmlspecialchars('<input type="text" name="address" class="form-control" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">')."<br>".htmlspecialchars('<input type="checkbox" name="honeypot" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">')."</pre></code> near the input with name <code>".htmlspecialchars('<?php echo $data["address_input_name"]; ?>')."</code>."
    ),
    array(
        "test" => "/claim\-button/",
        "message" => "Add <code>claim-button</code> class to claim button. Without it button timer and adblock detection won't work"
    )
);


if (session_id()) {
    if (empty($_SESSION["$session_prefix-csrftoken"])) {
        regenerate_csrf_token();
    }
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (empty($_POST["csrftoken"]) || $_SESSION["$session_prefix-csrftoken"] != $_POST["csrftoken"]) {
            trigger_error("CSRF failed!");
            $_POST = [];
            $_REQUEST = [];
            $_SERVER["REQUEST_METHOD"] = "GET";
        }
    }
}


if (!$pass) {
    // first run
    $sql->query($default_data_query);
    $password = setNewPass();
    $page = str_replace('<:: content ::>', $pass_template, $master_template);
    $page = str_replace('<:: password ::>', $password, $page);
    die($page);
}

if ($disable_admin_panel) {
    trigger_error("Admin panel disabled in config!");
    header("Location: index.php");
    die("Please wait...");
}

if (array_key_exists('p', $_GET) && $_GET['p'] == 'logout')
    $_SESSION = [];

if (array_key_exists('p', $_GET) && $_GET['p'] == 'password-reset') {
    $error = "";
    if (array_key_exists('dbpass', $_POST)) {
        $user_captcha = array_key_exists("captcha", $_POST) ? $_POST["captcha"] : "";
        $captcha = new FiabCoolCaptcha();
        $captcha->session_var = "$session_prefix-cool-php-captcha";
        if ($captcha->isValid($user_captcha)) {
            if ($_POST['dbpass'] == $dbpass) {
                $password = setNewPass();
                $page = str_replace('<:: content ::>', $pass_template, $master_template);
                $page = str_replace('<:: password ::>', $password, $page);
                die($page);
            } else {
                $error = $dbpass_error_template;
            }
        } else {
            $error = $captcha_error_template;
        }
    }
    $page = str_replace('<:: content ::>', $error.$pass_reset_template, $master_template);
    $page = str_replace("<:: csrftoken ::>", get_csrf_token(), $page);
    die($page);
}

$invalid_key = false;
if (array_key_exists('password', $_POST)) {
    $user_captcha = array_key_exists("captcha", $_POST) ? $_POST["captcha"] : "";
    $captcha = new FiabCoolCaptcha();
    $captcha->session_var = "$session_prefix-cool-php-captcha";
    if ($captcha->isValid($user_captcha)) {
        if ($pass[0] == crypt($_POST['password'], $pass[0])) {
            $_SESSION["$session_prefix-logged_in"] = true;
            header("Location: ?session_check=0");
            die();
        } else {
            $admin_login_template = $login_error_template.$admin_login_template;
        }
    } else {
        $admin_login_template = $captcha_error_template.$admin_login_template;
    }
}
if (array_key_exists("session_check", $_GET)) {
    if (array_key_exists("$session_prefix-logged_in", $_SESSION)) {
        header("Location: ?");
        die();
    } else {
        //show alert on login screen
        $admin_login_template = $session_error_template.$admin_login_template;
    }
}

if (array_key_exists("$session_prefix-logged_in", $_SESSION)) { // logged in to admin page

    //ajax
    if (array_key_exists("action", $_POST)) {

        header("Content-type: application/json");

        $response = ["status" => 404];

        switch ($_POST["action"]) {
            case "check_referrals":

                $referral = array_key_exists("referral", $_POST) ? trim($_POST["referral"]) : "";

                $response["status"] = 200;
                $response["addresses"] = [];

                if (strlen($referral) > 0) {

                    $q = $sql->prepare("SELECT `a`.`address`, `r`.`address` FROM `Faucetinabox_Refs` `r` LEFT JOIN `Faucetinabox_Addresses` `a` ON `r`.`id` = `a`.`ref_id` WHERE `r`.`address` LIKE ? ORDER BY `a`.`last_used` DESC");
                    $q->execute(["%".$referral."%"]);
                    while ($row = $q->fetch()) {
                        $response["addresses"][] = [
                            "address" => $row[0],
                            "referral" => $row[1],
                        ];
                    }

                }

            break;
        }

        die(json_encode($response));

    }

    if (array_key_exists('task', $_POST) && $_POST['task'] == 'oneclick-update') {
        function recurse_copy($copy_as_new,$src,$dst) {
            $dir = opendir($src);
            @mkdir($dst);
            while (false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . '/' . $file) ) {
                        recurse_copy($copy_as_new, $src . '/' . $file,$dst . '/' . $file);
                    }
                    else {
                        $dstfile = $dst.'/'.$file;
                        if (in_array(realpath($dstfile), $copy_as_new))
                            $dstfile .= ".new";
                        if (!copy($src . '/' . $file,$dstfile)) {
                            return false;
                        }
                    }
                }
            }
            closedir($dir);
            return true;
        }
        function rrmdir($dir) {
          if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
              if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
              }
            }
            reset($objects);
            rmdir($dir);
          }
        }

        ini_set('display_errors', true);
        error_reporting(-1);
        $fb = new Service("faucetbox", null, null, $connection_options);
        $response = $fb->fiabVersionCheck();
        if (empty($response['version']) || $response['version'] == $version || !checkOneclickUpdatePossible($response)) {
            header("Location: ?update_status=fail");
            die();
        }

        $url = $response["url"];
        if ($url[0] == '/') $url = "https:$url";
        $url .= "?update=auto";

        if (!file_put_contents('update.zip', fopen($url, 'rb'))) {
            header("Location: ?update_status=fail");
            die();
        }

        $zip = new ZipArchive();
        if (!$zip->open('update.zip')) {
            unlink('update.zip');
            header("Location: ?update_status=fail");
            die();
        }

        if (!$zip->extractTo('./')) {
            unlink('update.zip');
            header("Location: ?update_status=fail");
            die();
        }

        $dir = trim($zip->getNameIndex(0), '/');
        $zip->close();
        unlink('update.zip');
        unlink("$dir/config.php");

        $modified_files = [];
        foreach ($response['changelog'][$version]['hashes'] as $file => $hash) {
            if (strpos($file, 'templates/') === 0 &&
               sha1_file($file) !== $hash
            ) {
                $modified_files[] = realpath($file);
            }
        }
        if (!recurse_copy($modified_files, $dir, '.')) {
            header("Location: ?update_status=fail");
            die();
        }
        rrmdir($dir);
        header("Location: ?update_status=success&new_files=".count($modified_files));
        die();
    }

    if (
        array_key_exists("update_status", $_GET) &&
        in_array($_GET["update_status"], ["success", "fail"])
    ) {
        if ($_GET["update_status"] == "success") {
            $oneclick_update_alert = $oneclick_update_success_template;
        } else {
            $oneclick_update_alert = $oneclick_update_fail_template;
        }
    } else {
        $oneclick_update_alert = "";
    }

    if (array_key_exists("encoded_data", $_POST)) {
        $data = base64_decode($_POST["encoded_data"]);
        if ($data) {
            parse_str($data, $tmp);
            $_POST = array_merge($_POST, $tmp);
        }
    }

    if (array_key_exists('get_options', $_POST)) {
        if (file_exists("templates/{$_POST["get_options"]}/setup.php")) {
            require_once("templates/{$_POST["get_options"]}/setup.php");
            die(getTemplateOptions($sql, $_POST['get_options']));
        } else {
            die('<p>No template defined options available.</p>');
        }
    } else if (
        array_key_exists("reset", $_POST) &&
        array_key_exists("factory_reset_confirm", $_POST) &&
        $_POST["factory_reset_confirm"] == "on"
    ) {
        $sql->exec("DELETE FROM Faucetinabox_Settings WHERE name NOT LIKE '%key%' AND name != 'password'");
        $sql->exec($default_data_query);
    }
    $q = $sql->prepare("SELECT value FROM Faucetinabox_Settings WHERE name = ?");
    $q->execute(array('apikey'));
    $apikey = $q->fetch();
    $apikey = $apikey[0];
    $q->execute(array('currency'));
    $currency = $q->fetch();
    $currency = $currency[0];
    $q->execute(array('service'));
    $service = $q->fetch();
    $service = $service[0];
    
    $fb = new Service($service, $apikey, $currency, $connection_options);
    $connection_error = '';
    $curl_warning = '';
    $missing_configs_info = '';
    if (!empty($missing_configs)) {
        $list = '';
        foreach ($missing_configs as $missing_config) {
            $list .= str_replace(array("<:: config_name ::>", "<:: config_default ::>", "<:: config_description ::>"), array($missing_config['name'], $missing_config['default'], $missing_config['desc']), $missing_config_template);
        }
        $missing_configs_info = str_replace("<:: missing_configs ::>", $list, $missing_configs_template);
    }
    if ($fb->curl_warning) {
        $curl_warning = $curl_warning_template;
    }
    $currencies = array('BTC', 'LTC', 'DOGE', 'PPC', 'XPM', 'DASH');
    $send_coins_message = '';
    if (array_key_exists('send_coins', $_POST)) {

        $amount = array_key_exists('send_coins_amount', $_POST) ? intval($_POST['send_coins_amount']) : 0;
        $address = array_key_exists('send_coins_address', $_POST) ? trim($_POST['send_coins_address']) : '';

        $fb = new Service($service, $apikey, $currency, $connection_options);
        $ret = $fb->send($address, $amount, getIP());

        if ($ret['success']) {
            $send_coins_message = str_replace(array('{{amount}}','{{address}}'), array($amount,$address), $send_coins_success_template);
        } else {
            $send_coins_message = str_replace(array('{{amount}}','{{address}}','{{error}}'), array($amount,$address,$ret['message']), $send_coins_error_template);
        }

    }
    $changes_saved = "";
    if (array_key_exists('save_settings', $_POST)) {
        $service = $_POST['service'];
        $currency = $_POST['currency'];
        $fb = new Service($service, $_POST['apikey'], $currency, $connection_options);
        $ret = $fb->getBalance();
        if ($fb->communication_error) {
            $connection_error = $connection_error_template;
        }

        //411 - invalid api key (FaucetSystem.com)
        if ($ret['status'] == 403 || $ret['status'] == 411) {
            $invalid_key = true;
        } elseif ($ret['status'] == 405) {
            $sql->query("UPDATE Faucetinabox_Settings SET `value` = 0 WHERE name = 'balance'");
        } elseif (array_key_exists('balance', $ret)) {
            $q = $sql->prepare("UPDATE Faucetinabox_Settings SET `value` = ? WHERE name = 'balance'");
            if ($currency != 'DOGE')
                $q->execute(array($ret['balance']));
            else
                $q->execute(array($ret['balance_bitcoin']));
        }

        $q = $sql->prepare("INSERT IGNORE INTO Faucetinabox_Settings (`name`, `value`) VALUES (?, ?)");
        $template = $_POST["template"];
        preg_match_all('/\$data\[([\'"])(custom_(?:(?!\1).)*)\1\]/', file_get_contents("templates/$template/index.php"), $matches);
        foreach ($matches[2] as $box)
            $q->execute(array("{$box}_$template", ''));


        $sql->beginTransaction();
        $q = $sql->prepare("UPDATE Faucetinabox_Settings SET value = ? WHERE name = ?");
        $ipq = $sql->prepare("INSERT INTO Faucetinabox_Pages (url_name, name, html) VALUES (?, ?, ?)");
        $sql->exec("DELETE FROM Faucetinabox_Pages");
        foreach ($_POST as $k => $v) {
            if ($k == 'apikey' && $invalid_key)
                continue;
            if ($k == 'pages') {
                foreach ($_POST['pages'] as $p) {
                    $url_name = strtolower(preg_replace("/[^A-Za-z0-9_\-]/", '', $p["name"]));
                    $i = 0;
                    $success = false;
                    while (!$success) {
                        try {
                            if ($i)
                                $ipq->execute(array($url_name.'-'.$i, $p['name'], $p['html']));
                            else
                                $ipq->execute(array($url_name, $p['name'], $p['html']));
                            $success = true;
                        } catch(PDOException $e) {
                            $i++;
                        }
                    }
                }
                continue;
            }
            $q->execute(array($v, $k));
        }
        foreach (["block_adblock", "iframe_sameorigin_only", "nastyhosts_enabled", "reverse_proxy"] as $key) {
            if (!array_key_exists($key, $_POST)) $q->execute(array("", $key));
        }
        $sql->commit();

        $changes_saved = $changes_saved_template;
    }
    $captcha_enabled = false;
    $faucet_disabled = false;
    $page = str_replace('<:: content ::>', $admin_template, $master_template);
    $query = $sql->query("SELECT name, value FROM Faucetinabox_Settings");
    while ($row = $query->fetch()) {
        if ($row[0] == 'template') {
            if (file_exists("templates/{$row[1]}/index.php")) {
                $current_template = $row[1];
            } else {
                $templates = glob("templates/*");
                if ($templates)
                    $current_template = substr($templates[0], strlen('templates/'));
                else
                    die(str_replace("<:: content ::>", "<div class='alert alert-danger' role='alert'>No templates found! Please reinstall your faucet.</div>", $master_template));
            }
        } else {
            if (in_array($row[0], ["block_adblock", "iframe_sameorigin_only", "nastyhosts_enabled", "reverse_proxy"])) {
                $row[1] = $row[1] == "on" ? "checked" : "";
            }
            if (in_array($row[0], ["apikey", "rewards"]) && empty($row[1])) {
                $faucet_disabled = true;
            }
            if (strpos($row[0], "recaptcha_") !== false || strpos($row[0], "solvemedia_") !== false || strpos($row[0], "funcaptcha_") !== false) {
                if (!empty($row[1])) {
                    $captcha_enabled = true;
                }
            }
            $page = str_replace("<:: {$row[0]} ::>", $row[1], $page);
        }
    }
    
    $faucet_disabled_message = $faucet_disabled_template;
    if (!$faucet_disabled && $captcha_enabled) {
        $faucet_disabled_message = "";
    }
    $page = str_replace("<:: faucet_disabled ::>", $faucet_disabled_message, $page);


    $templates = '';
    foreach (glob("templates/*") as $template) {
        $template = basename($template);
        if ($template == $current_template) {
            $templates .= "<option selected>$template</option>";
        } else {
            $templates .= "<option>$template</option>";
        }
    }
    $page = str_replace('<:: templates ::>', $templates, $page);
    $page = str_replace('<:: current_template ::>', $current_template, $page);


    if (file_exists("templates/{$current_template}/setup.php")) {
        require_once("templates/{$current_template}/setup.php");
        $page = str_replace('<:: template_options ::>', getTemplateOptions($sql, $current_template), $page);
    } else {
        $page = str_replace('<:: template_options ::>', '<p>No template defined options available.</p>', $page);
    }

    $template_string = file_get_contents("templates/{$current_template}/index.php");
    $template_updates_info = '';
    foreach ($template_updates as $update) {
        if (!preg_match($update["test"], $template_string)) {
            $template_updates_info .= str_replace("<:: message ::>", $update["message"], $template_update_template);
        }
    }
    if (!empty($template_updates_info)) {
        $template_updates_info = str_replace("<:: template_updates ::>", $template_updates_info, $template_updates_template);
    }

    $q = $sql->query("SELECT name, html FROM Faucetinabox_Pages ORDER BY id");
    $pages = '';
    $pages_nav = '';
    $i = 1;
    while ($userpage = $q->fetch()) {
        $html = htmlspecialchars($userpage['html']);
        $name = htmlspecialchars($userpage['name']);
        $pages .= str_replace(array('<:: i ::>', '<:: page_name ::>', '<:: html ::>'),
                              array($i, $name, $html), $page_form_template);
        $pages_nav .= str_replace('<:: i ::>', $i, $page_nav_template);
        ++$i;
    }
    $page = str_replace('<:: pages ::>', $pages, $page);
    $page = str_replace('<:: pages_nav ::>', $pages_nav, $page);
    $currencies_select = "";
    foreach ($currencies as $c) {
        if ($currency == $c)
            $currencies_select .= "<option value='$c' selected>$c</option>";
        else
            $currencies_select .= "<option value='$c'>$c</option>";
    }
    $page = str_replace('<:: currency ::>', $currency, $page);
    $page = str_replace('<:: currencies ::>', $currencies_select, $page);


    if ($invalid_key)
        $page = str_replace('<:: invalid_key ::>', $invalid_key_error_template, $page);
    else
        $page = str_replace('<:: invalid_key ::>', '', $page);

    $services = "";
    foreach($fb->getServices() as $s => $name) {
        if($s == $service) {
            $services .= "<option value='$s' selected>$name</option>";
        } else {
            $services .= "<option value='$s'>$name</option>";
        }
    }
    $page = str_replace('<:: services ::>', $services, $page);

    $page = str_replace('<:: page_form_template ::>',
                        json_encode($page_form_template),
                        $page);
    $page = str_replace('<:: page_nav_template ::>',
                        json_encode($page_nav_template),
                        $page);

    $new_files = [];
    foreach (new RecursiveIteratorIterator (new RecursiveDirectoryIterator ('templates')) as $file) {
        $file = $file->getPathname();
        if (substr($file, -4) == ".new") {
            $new_files[] = $file;
        }
    }

    if ($new_files) {
        $new_files = implode("\n", array_map(function($v) { return "<li>$v</li>"; }, $new_files));
        $new_files = str_replace("<:: new_files ::>", $new_files, $new_files_template);
    } else {
        $new_files = "";
    }
    $page = str_replace("<:: new_files ::>", $new_files, $page);

    $q = $sql->query("SELECT value != CURDATE() FROM Faucetinabox_Settings WHERE name = 'update_last_check' ");
    $recheck_version = $q->fetch();
    if ($recheck_version && $recheck_version[0]) {
        $response = $fb->fiabVersionCheck();
        $oneclick_update_possible = checkOneclickUpdatePossible($response);
        if (!$connection_error && $response['version'] && $version < intval($response["version"])) {
            $page = str_replace('<:: version_check ::>', $new_version_template, $page);
            $changelog = '';
            foreach ($response['changelog'] as $v => $changes) {
                $changelog_entries = array_map(function($entry) {
                    return "<li>$entry</li>";
                }, $changes['changelog']);
                $changelog_entries = implode("", $changelog_entries);
                if (intval($v) > $version) {
                    $changelog .= "<p>Changes in r$v (${changes['released']}): <ul>${changelog_entries}</ul></p>";
                }
            }
            $page = str_replace(array('<:: url ::>', '<:: version ::>', '<:: changelog ::>'), array($response['url'], $response['version'], $changelog), $page);
            if ($oneclick_update_possible) {
                $page = str_replace('<:: oneclick_update_button ::>', $oneclick_update_button_template, $page);
            } else {
                $page = str_replace('<:: oneclick_update_button ::>', '', $page);
            }
        } else {
            $page = str_replace('<:: version_check ::>', '', $page);
            $sql->query("UPDATE Faucetinabox_Settings SET value = CURDATE() WHERE name = 'update_last_check' ");
        }
    } else {
        $page = str_replace('<:: version_check ::>', '', $page);
    }
    
    $page = str_replace('<:: detected_reverse_proxy_name ::>', detectRevProxyProvider(), $page);
    
    
    $page = str_replace('<:: connection_error ::>', $connection_error, $page);
    $page = str_replace('<:: curl_warning ::>', $curl_warning, $page);
    $page = str_replace('<:: send_coins_message ::>', $send_coins_message, $page);
    $page = str_replace('<:: missing_configs ::>', $missing_configs_info, $page);
    $page = str_replace('<:: template_updates ::>', $template_updates_info, $page);
    $page = str_replace('<:: changes_saved ::>', $changes_saved, $page);
    $page = str_replace('<:: oneclick_update_alert ::>', $oneclick_update_alert, $page);
    $page = str_replace("<:: csrftoken ::>", get_csrf_token(), $page);
    $page = str_replace("<:: supported_services ::>", json_encode(Service::$services), $page);
    $page = str_replace("<:: fiab_version ::>", "r".$version, $page);
    die($page);
} else {
    // requested admin page without session
    $page = str_replace('<:: content ::>', $admin_login_template, $master_template);
    $page = str_replace("<:: csrftoken ::>", get_csrf_token(), $page);
    die($page);
}
