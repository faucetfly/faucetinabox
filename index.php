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

if (!$pass) {
    // first run
    header("Location: admin.php");
    die("Please wait...");
}

if (array_key_exists("p", $_GET) && in_array($_GET["p"], ["admin", "password-reset"])) {
    header("Location: admin.php?p={$_GET["p"]}");
    die("Please wait...");
}

#reCaptcha template
$recaptcha_template = <<<TEMPLATE
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div class="g-recaptcha" data-sitekey="<:: your_site_key ::>"></div>
<noscript>
  <div style="width: 302px; height: 352px;">
    <div style="width: 302px; height: 352px; position: relative;">
      <div style="width: 302px; height: 352px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=<:: your_site_key ::>"
                frameborder="0" scrolling="no"
                style="width: 302px; height:352px; border-style: none;">
        </iframe>
      </div>
      <div style="width: 250px; height: 80px; position: absolute; border-style: none;
                  bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                  class="g-recaptcha-response"
                  style="width: 250px; height: 80px; border: 1px solid #c1c1c1;
                         margin: 0px; padding: 0px; resize: none;" value="">
        </textarea>
      </div>
    </div>
  </div>
</noscript>
TEMPLATE;

if (!empty($_POST["mmc"])) {
    $_SESSION["$session_prefix-mouse_movement_detected"] = true;
    die();
}


// Check functions

function checkTimeForIP($ip, &$time_left = NULL) {
    global $sql, $data;
    $q = $sql->prepare("SELECT TIMESTAMPDIFF(MINUTE, last_used, CURRENT_TIMESTAMP()) FROM Faucetinabox_IPs WHERE ip = ?");
    $q->execute([$ip]);
    if ($time = $q->fetch()) {
        $time = intval($time[0]);
        $required = intval($data["timer"]);
        
        $time_left = $required-$time;
        return $time >= intval($data["timer"]);
    } else {
        $time_left = 0;
        return true;
    }
}

function checkTimeForAddress($address, &$time_left) {
    global $sql, $data;
    $q = $sql->prepare("SELECT TIMESTAMPDIFF(MINUTE, last_used, CURRENT_TIMESTAMP()) FROM Faucetinabox_Addresses WHERE `address` = ?");
    $q->execute([$address]);
    if ($time = $q->fetch()) {
        $time = intval($time[0]);
        $required = intval($data["timer"]);

        $time_left = $required-$time;
        return $time >= intval($data["timer"]);
    } else {
        $time_left = 0;
        return true;
    }
}

function checkAddressValidity($address) {
    global $data;

    if($data['service'] === "epay") return true;

    return (preg_match("/^[0-9A-Za-z]{26,34}$/", $address) === 1);
}

function checkAddressBlacklist($address) {
    global $security_settings;
    return !in_array($address, $security_settings["address_ban_list"]);
}

function checkIPIsWhitelisted() {
    global $security_settings;
    $ip = ip2long(getIP());
    if ($ip) { // only ipv4 supported here
        foreach ($security_settings["ip_white_list"] as $whitelisted) {
            if (ipSubnetCheck($ip, $whitelisted)) {
                return true;
            }
        }
    }
    return false;
}

function checkIPBlacklist() {
    global $security_settings;
    $ip = ip2long(getIP());
    if ($ip) { // only ipv4 supported here
        foreach ($security_settings["ip_ban_list"] as $ban) {
            if (ipSubnetCheck($ip, $ban)) {
                trigger_error("Banned: ".getIP()." (blacklist: {$ban})");
                return false;
            }
        }
    }
    return true;
}

function checkNastyHosts() {
    global $security_settings;
    if ($security_settings["nastyhosts_enabled"]) {
        $hostnames = @file_get_contents(getNastyHostsServer().getIP().'?source=fiab');
        $hostnames = json_decode($hostnames);
        
        if ($hostnames && property_exists($hostnames, "status") && $hostnames->status == 200) {
            if (property_exists($hostnames, "suggestion") && $hostnames->suggestion == "deny") {
                trigger_error("Banned: ".getIP()." (NastyHosts)");
                return false;
            }
            if (property_exists($hostnames, "asn") && property_exists($hostnames->asn, "asn")) {
                foreach ($security_settings["asn_ban_list"] as $ban) {
                    if ($ban == $hostnames->asn->asn) {
                        trigger_error("Banned: ".getIP()." (ASN: {$ban})");
                        return false;
                    }
                }
            }
            if (property_exists($hostnames, "country") && property_exists($hostnames->country, "code")) {
                foreach ($security_settings["country_ban_list"] as $ban) {
                    if ($ban == $hostnames->country->code) {
                        trigger_error("Banned: ".getIP()." (country: {$ban})");
                        return false;
                    }
                }
            }
            if (property_exists($hostnames, "hostnames")) {
                foreach ($security_settings["hostname_ban_list"] as $ban) {
                    foreach ($hostnames->hostnames as $hostname) {
                        if (stripos($hostname, $ban) !== false) {
                            trigger_error("Banned: ".getIP()." (hostname: {$ban})");
                            return false;
                        }
                    }
                }
            }
        } else {
            // nastyhosts down or status != 200
            trigger_error("Couldn't connect to NastyHost, refusing to payout!");
            return false;
        }
    }
    return true;
}

function checkCaptcha() {
    global $data, $captcha;
    
    switch ($captcha["selected"]) {
        case "SolveMedia":
            require_once("libs/solvemedialib.php");
            $resp = solvemedia_check_answer(
                $data["solvemedia_verification_key"],
                getIP(),
                (array_key_exists("adcopy_challenge", $_POST) ? $_POST["adcopy_challenge"] : ""),
                (array_key_exists("adcopy_response", $_POST) ? $_POST["adcopy_response"] : ""),
                $data["solvemedia_auth_key"]
            );
            return $resp->is_valid;
        break;
        case "reCaptcha":
            $url = "https://www.google.com/recaptcha/api/siteverify?secret=".$data["recaptcha_private_key"]."&response=".(array_key_exists("g-recaptcha-response", $_POST) ? $_POST["g-recaptcha-response"] : "")."&remoteip=".getIP();
            $resp = json_decode(file_get_contents($url), true);
            return $resp["success"];
        break;
        case "FunCaptcha":
            require_once("libs/funcaptcha.php");
            $funcaptcha = new FUNCAPTCHA();
            return $funcaptcha->checkResult($data["funcaptcha_private_key"]);
        break;
    }
    
    return false;
}

function releaseAddressLock($address) {
    global $sql;
    $q = $sql->prepare("DELETE FROM Faucetinabox_Address_Locks WHERE address = ?");
    $q->execute([$address]);
}

function claimAddressLock($address) {
    global $sql;
    $q = $sql->prepare("DELETE FROM Faucetinabox_Address_Locks WHERE address = ? AND TIMESTAMPDIFF(MINUTE, locked_since, CURRENT_TIMESTAMP()) > 5");
    $q->execute([$address]);
    $q = $sql->prepare("INSERT INTO Faucetinabox_Address_Locks (address, locked_since) VALUES (?, CURRENT_TIMESTAMP())");
    try {
        $q->execute([$address]);
    } catch (PDOException $e) {
        if($e->getCode() == 23000) {
            return false;
        } else {
            throw $e;
        }
    }
    register_shutdown_function("releaseAddressLock", $address);
    return true;
}

function releaseIPLock($ip) {
    global $sql;
    $q = $sql->prepare("DELETE FROM Faucetinabox_IP_Locks WHERE ip = ?");
    $q->execute([$ip]);
}

function claimIPLock($ip) {
    global $sql;
    $q = $sql->prepare("DELETE FROM Faucetinabox_IP_Locks WHERE ip = ? AND TIMESTAMPDIFF(MINUTE, locked_since, CURRENT_TIMESTAMP()) > 5");
    $q->execute([$ip]);
    $q = $sql->prepare("INSERT INTO Faucetinabox_IP_Locks (ip, locked_since) VALUES (?, CURRENT_TIMESTAMP())");
    try {
        $q->execute([$ip]);
    } catch (PDOException $e) {
        if($e->getCode() == 23000) {
            return false;
        } else {
            throw $e;
        }
    }
    register_shutdown_function("releaseIPLock", $ip);
    return true;
}

function getClaimError($address) {
    if (!claimAddressLock($address)) {
        return "You were locked for multiple claims, try again in 5 minutes.";
    }
    if (!claimIPLock(getIP())) {
        return "You were locked for multiple claims, try again in 5 minutes.";
    }
    if (!checkAddressValidity($address)) {
        return "Invalid address";
    }
    if (!checkCaptcha()) {
        return "Invalid captcha code";
    }
    if (!checkTimeForAddress($address, $time_left)) {
        return "You have to wait {$time_left} minutes";
    }
    if (!checkTimeForIP(getIP(), $time_left)) {
        return "You have to wait {$time_left} minutes";
    }
    if (!checkAddressBlacklist($address)) {
        return "Unknown error.";
    }
    if(!checkIPIsWhitelisted()) {
        if (!checkIPBlacklist()) {
            return "Unknown error.";
        }
        if (!checkNastyHosts()) {
            return "Unknown error.";
        }
    }
    return null;
}



// Get template
$q = $sql->query("SELECT value FROM Faucetinabox_Settings WHERE name = 'template'");
$template = $q->fetch();
$template = $template[0];
if (!file_exists("templates/{$template}/index.php")) {
    $templates = glob("templates/*");
    if ($templates)
        $template = substr($templates[0], strlen("templates/"));
    else
        die(str_replace('<:: content ::>', "<div class='alert alert-danger' role='alert'>No templates found!</div>", $master_template));
}


// Check protocol
if (array_key_exists("HTTPS", $_SERVER) && $_SERVER["HTTPS"])
    $protocol = "https://";
else
    $protocol = "http://";


// Get address
if (array_key_exists("$session_prefix-address_input_name", $_SESSION) && array_key_exists($_SESSION["$session_prefix-address_input_name"], $_POST)) {
    $_POST["address"] = $_POST[$_SESSION["$session_prefix-address_input_name"]];
} else {
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        if (array_key_exists("$session_prefix-address_input_name", $_SESSION)) {
            trigger_error("Post request, but invalid address input name.");
        } else {
            trigger_error("Post request, but session is invalid.");
        }
    }
    unset($_POST["address"]);
}


$data = array(
    "paid" => false,
    "disable_admin_panel" => $disable_admin_panel,
    "address" => "",
    "captcha_valid" => true, //for people who won't update templates
    "captcha" => false,
    "enabled" => false,
    "error" => false,
    "address_eligible" => true,
    "reflink" => $protocol.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'], '?').'?r='
);


// Show ref link
if (array_key_exists('address', $_POST)) {
    $data["reflink"] .= $_POST['address'];
} else if (array_key_exists('address', $_COOKIE)) {
    $data["reflink"] .= $_COOKIE['address'];
    $data["address"] = $_COOKIE['address'];
} else {
    $data["reflink"] .= 'Your_Address';
}


// Get settings from DB
$q = $sql->query("SELECT name, value FROM Faucetinabox_Settings WHERE name <> 'password'");
while ($row = $q->fetch()) {
    if ($row[0] == "safety_limits_end_time") {
        $time = strtotime($row[1]);
        if ($time !== false && $time < time()) {
            $row[1] = "";
        }
    }
    $data[$row[0]] = $row[1];
}

// Update balance
if (time() - $data['last_balance_check'] > 60*10) {
    $fb = new Service($data['service'], $data['apikey'], $data['currency'], $connection_options);
    $ret = $fb->getBalance();
    if (array_key_exists('balance', $ret)) {
        if ($data['currency'] != 'DOGE')
            $balance = $ret['balance'];
        else
            $balance = $ret['balance_bitcoin'];
        $q = $sql->prepare("UPDATE Faucetinabox_Settings SET value = ? WHERE name = ?");
        $q->execute(array(time(), 'last_balance_check'));
        $q->execute(array($balance, 'balance'));
        $data['balance'] = $balance;
        $data['last_balance_check'] = time();
    }
}


// Set unit name
$data['unit'] = 'satoshi';
if ($data["currency"] == 'DOGE')
    $data["unit"] = 'DOGE';


#MuliCaptcha: Firstly check chosen captcha system
$captcha = array('available' => array(), 'selected' => null);
if ($data['solvemedia_challenge_key'] && $data['solvemedia_verification_key'] && $data['solvemedia_auth_key']) {
    $captcha['available'][] = 'SolveMedia';
}
if ($data['recaptcha_public_key'] && $data['recaptcha_private_key']) {
    $captcha['available'][] = 'reCaptcha';
}
if ($data['funcaptcha_public_key'] && $data['funcaptcha_private_key']) {
    $captcha['available'][] = 'FunCaptcha';
}

#MuliCaptcha: Secondly check if user switched captcha or choose default
if (array_key_exists('cc', $_GET) && in_array($_GET['cc'], $captcha['available'])) {
    $captcha['selected'] = $captcha['available'][array_search($_GET['cc'], $captcha['available'])];
    $_SESSION["$session_prefix-selected_captcha"] = $captcha['selected'];
} elseif (array_key_exists("$session_prefix-selected_captcha", $_SESSION) && in_array($_SESSION["$session_prefix-selected_captcha"], $captcha['available'])) {
    $captcha['selected'] = $_SESSION["$session_prefix-selected_captcha"];
} else {
    if ($captcha['available'])
        $captcha['selected'] = $captcha['available'][0];
    if (in_array($data['default_captcha'], $captcha['available'])) {
        $captcha['selected'] = $data['default_captcha'];
    } else if ($captcha['available']) {
        $captcha['selected'] = $captcha['available'][0];
    }
}

#MuliCaptcha: And finally handle chosen captcha system
# -> checkCaptcha()
switch ($captcha['selected']) {
    case 'SolveMedia':
        require_once("libs/solvemedialib.php");
        $data["captcha"] = solvemedia_get_html($data["solvemedia_challenge_key"], null, is_ssl());
    break;
    case 'reCaptcha':
        $data["captcha"] = str_replace('<:: your_site_key ::>', $data["recaptcha_public_key"], $recaptcha_template);
    break;
    case 'FunCaptcha':
        require_once("libs/funcaptcha.php");
        $funcaptcha = new FUNCAPTCHA();
        $data["captcha"] =  $funcaptcha->getFunCaptcha($data["funcaptcha_public_key"]);
    break;
}

$data['captcha_info'] = $captcha;

// Check if faucet's enabled
if ($data['captcha'] && $data['apikey'] && $data['rewards'])
    $data['enabled'] = true;


// check if IP eligible
$data["eligible"] = checkTimeForIP(getIP(), $time_left);
$data['time_left'] = $time_left." minutes";


// Rewards
$rewards = explode(',', $data['rewards']);
$total_weight = 0;
$nrewards = array();
foreach ($rewards as $reward) {
    $reward = explode("*", trim($reward));
    if (count($reward) < 2) {
        $reward[1] = $reward[0];
        $reward[0] = 1;
    }
    $total_weight += intval($reward[0]);
    $nrewards[] = $reward;
}
$rewards = $nrewards;
if (count($rewards) > 1) {
    $possible_rewards = array();
    foreach ($rewards as $r) {
        $chance_per = 100 * $r[0]/$total_weight;
        if ($chance_per < 0.1)
            $chance_per = '< 0.1%';
        else
            $chance_per = round(floor($chance_per*10)/10, 1).'%';

        $possible_rewards[] = $r[1]." ($chance_per)";
    }
} else {
    $possible_rewards = array($rewards[0][1]);
}



if (array_key_exists('address', $_POST) && $data['enabled'] && $data['eligible']) {
    
    $address = trim($_POST["address"]);

    if(empty($data['address']))
        $data['address'] = $address;

    $error = getClaimError($address);
    if ($error) {
        $data["error"] = "<div class=\"alert alert-danger\">{$error}</div>";
    } else {
        
        // Rand amount
        $r = mt_rand()/mt_getrandmax();
        $t = 0;
        foreach ($rewards as $reward) {
            $t += intval($reward[0])/$total_weight;
            if ($t > $r) {
                break;
            }
        }
        if (strpos($reward[1], '-') !== false) {
            $reward_range = explode('-', $reward[1]);
            $from = floatval($reward_range[0]);
            $to = floatval($reward_range[1]);
            $reward = mt_rand($from, $to);
        } else {
            $reward = floatval($reward[1]);
        }
        
        $fb = new Service($data['service'], $data["apikey"], $data["currency"], $connection_options);
        $ret = $fb->send($address, $reward, getIP());
        
        if ($ret['success']) {
            setcookie('address', trim($_POST['address']), time() + 60*60*24*60);
            if (!empty($ret['balance'])) {
                $q = $sql->prepare("UPDATE Faucetinabox_Settings SET `value` = ? WHERE `name` = 'balance'");

                if ($data['unit'] == 'satoshi')
                    $data['balance'] = $ret['balance'];
                else
                    $data['balance'] = $ret['balance_bitcoin'];
                $q->execute(array($data['balance']));
            }

            $sql->exec("UPDATE Faucetinabox_Settings SET value = '' WHERE `name` = 'safety_limits_end_time' ");

            // handle refs
            if (array_key_exists('r', $_GET) && trim($_GET['r']) != $address) {
                $q = $sql->prepare("INSERT IGNORE INTO Faucetinabox_Refs (address) VALUES (?)");
                $q->execute(array(trim($_GET["r"])));
                $q = $sql->prepare("INSERT IGNORE INTO Faucetinabox_Addresses (`address`, `ref_id`, `last_used`) VALUES (?, (SELECT id FROM Faucetinabox_Refs WHERE address = ?), CURRENT_TIMESTAMP())");
                $q->execute(array(trim($_POST['address']), trim($_GET['r'])));
            }
            $refamount = floatval($data['referral'])*$reward/100;
            $q = $sql->prepare("SELECT address FROM Faucetinabox_Refs WHERE id = (SELECT ref_id FROM Faucetinabox_Addresses WHERE address = ?)");
            $q->execute(array(trim($_POST['address'])));
            if ($ref = $q->fetch()) {
                if (!in_array(trim($ref[0]), $security_settings['address_ban_list'])) {
                    $fb->sendReferralEarnings(trim($ref[0]), $refamount, getIP());
                }
            }

            if ($data['unit'] == 'satoshi')
                $data['paid'] = $ret['html'];
            else
                $data['paid'] = $ret['html_coin'];
        } else {
            $response = json_decode($ret["response"]);
            if ($response && property_exists($response, "status") && $response->status == 450) {
                // how many minutes until next safety limits reset?
                $end_minutes  = (date("i") > 30 ? 60 : 30) - date("i");
                // what date will it be exactly?
                $end_date = date("Y-m-d H:i:s", time()+$end_minutes*60-date("s"));
                $sql->prepare("UPDATE Faucetinabox_Settings SET value = ? WHERE `name` = 'safety_limits_end_time' ")->execute([$end_date]);
            }
            $data['error'] = $ret['html'];
        }
        if ($ret['success'] || $fb->communication_error) {
            $q = $sql->prepare("INSERT INTO Faucetinabox_IPs (`ip`, `last_used`) VALUES (?, CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `last_used` = CURRENT_TIMESTAMP()");
            $q->execute([getIP()]);
            $q = $sql->prepare("INSERT INTO Faucetinabox_Addresses (`address`, `last_used`) VALUES (?, CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `last_used` = CURRENT_TIMESTAMP()");
            $q->execute([$address]);

            // suspicious checks
            $q = $sql->query("SELECT value FROM Faucetinabox_Settings WHERE name = 'template'");
            if ($r = $q->fetch()) {
                if (stripos(file_get_contents('templates/'.$r[0].'/index.php'), 'libs/mmc.js') !== FALSE) {
                    if ($fake_address_input_used || !empty($_POST["honeypot"])) {
                        suspicious($security_settings["ip_check_server"], "honeypot");
                    }
                    if (empty($_SESSION["$session_prefix-mouse_movement_detected"])) {
                        suspicious($security_settings["ip_check_server"], "mmc");
                    }
                }
            }
        }
    }
}

if (!$data['enabled'])
    $page = 'disabled';
elseif ($data['paid'])
    $page = 'paid';
elseif ($data['eligible'] && $data['address_eligible'])
    $page = 'eligible';
else
    $page = 'visit_later';

$data['page'] = $page;

if (!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") {
    trigger_error("AJAX call that would break session");
    die();
}

$_SESSION["$session_prefix-address_input_name"] = randHash(rand(25,35));
$data['address_input_name'] = $_SESSION["$session_prefix-address_input_name"];

$data['rewards'] = implode(', ', $possible_rewards);

$q = $sql->query("SELECT url_name, name FROM Faucetinabox_Pages ORDER BY id");
$data["user_pages"] = $q->fetchAll();

$allowed = array("page", "name", "rewards", "short", "error", "paid", "captcha_valid", "captcha", "captcha_info", "time_left", "referral", "reflink", "template", "user_pages", "timer", "unit", "address", "balance", "disable_admin_panel", "address_input_name", "block_adblock", "iframe_sameorigin_only", "button_timer", "safety_limits_end_time");

preg_match_all('/\$data\[([\'"])(custom_(?:(?!\1).)*)\1\]/', file_get_contents("templates/$template/index.php"), $matches);
foreach (array_unique($matches[2]) as $box) {
    $key = "{$box}_$template";
    if (!array_key_exists($key, $data)) {
        $data[$key] = '';
    }
    $allowed[] = $key;
}

foreach (array_keys($data) as $key) {
    if (!(in_array($key, $allowed))) {
        unset($data[$key]);
    }
}

foreach (array_keys($data) as $key) {
    if (array_key_exists($key, $data) && strpos($key, 'custom_') === 0) {
        $data[substr($key, 0, strlen($key) - strlen($template) - 1)] = $data[$key];
        unset($data[$key]);
    }
}

if (array_key_exists('p', $_GET)) {
    $q = $sql->prepare("SELECT url_name, name, html FROM Faucetinabox_Pages WHERE url_name = ?");
    $q->execute(array($_GET['p']));
    if ($page = $q->fetch()) {
        $data['page'] = 'user_page';
        $data['user_page'] = $page;
    } else {
        $data['error'] = "<div class='alert alert-danger'>That page doesn't exist!</div>";
    }
}

$data['address'] = htmlspecialchars($data['address']);

if (!empty($_SESSION["$session_prefix-mouse_movement_detected"])) {
    unset($_SESSION["$session_prefix-mouse_movement_detected"]);
}

require_once('templates/'.$template.'/index.php');
