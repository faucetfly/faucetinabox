<?php

require("libs/services/faucetbox.php");
require("libs/services/epay.php");
require("libs/services/paytoshi.php");
require("libs/services/faucetsystem.php");
require("libs/services/faucethub.php");
require("libs/services/faucetfly.php");

class Service {
    public static $services = [
        "faucetbox" => [
            "name" => "FaucetBOX.com",
            "currencies" => [
                "BTC", "LTC", "DOGE", "PPC", "XPM", "DASH"
            ]
        ],
        "epay" => [
            "name" => "ePay.info",
            "currencies" => [
                "BTC", "LTC", "DOGE", "DASH", "XMR", "PPC", "XPM", "ETH"
            ]
        ],
        "paytoshi" => [
            "name" => "Paytoshi",
            "currencies" => [ "BTC" ]
        ],
        "faucetsystem" => [
            "name" => "FaucetSystem.com",
            "currencies" => [ "BTC" ]
        ],
        "faucethub" => [
            "name" => "FaucetHub.io",
            "currencies" => [
                "BTC", "LTC", "DOGE"
            ]
        ],
        "faucetfly" => [
            "name" => "FaucetFly.com",
            "currencies" => [
                "BTC"
            ]
        ],
    ];
    protected $service;
    protected $api_key;
    protected $service_instance;
    protected $currency;
    public $communication_error = false;
    public $curl_warning = false;

    public $options = array(
        /* if disable_curl is set to true, it'll use PHP's fopen instead of
         * curl for connection */
        'disable_curl' => false,

        /* do not use these options unless you know what you're doing */
        'local_cafile' => false,
        'force_ipv4' => false,
        'verify_peer' => true
    );

    public function __construct($service, $api_key, $currency = "BTC", $connection_options = null) {
        $this->service = $service;
        $this->api_key = $api_key;
        $this->currency = $currency;
        if($connection_options)
            $this->options = array_merge($this->options, $connection_options);

        switch($this->service) {
        case "faucetbox":
            $this->service_instance = new FaucetBOX($api_key, $currency, $connection_options);
            break;
        case "epay":
            $this->service_instance = new ePay($api_key, $currency);
            break;
        case "paytoshi":
            $this->service_instance = new Paytoshi($api_key, $connection_options);
            break;
        case "faucetsystem":
            $this->service_instance = new FaucetSystem($api_key, $currency, $connection_options);
            break;
        case "faucethub":
            $this->service_instance = new FaucetHub($api_key, $currency, $connection_options);
            break;
        case "faucetfly":
            $this->service_instance = new FaucetFly($api_key, $currency, $connection_options);
            break;
        default:
            trigger_error("Invalid service $service");
        }
    }

    public function getServices($currency = null) {
        if(!$currency) {
            $all_services = [];
            foreach(self::$services as $service => $details) {
                $all_services[$service] = $details["name"];
            }
            return $all_services;
        }

        $services = [];
        foreach(self::$services as $service => $details) {
            if(in_array($service, $details["currencies"])) {
                $services[$service] = $details["name"];
            }
        }

        return $services;
    }

    public function send($to, $amount, $userip, $referral = "false") {
        if($this->currency === "DOGE" && $this->service !== "epay") {
            $amount *= 100000000;
        }
        switch($this->service) {
        case "faucetbox":
            $r = $this->service_instance->send($to, $amount, $referral);
            $check_url = "https://faucetbox.com/check/".rawurlencode($to);
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "epay":
            $r = $this->service_instance->send($to, $amount, $referral === "true", getIP());
            $check_url = "https://epay.info/check/".rawurlencode($to);
            $success = $r['status'] > 0;
            $balance = null;
            $error = $r['error_msg'];
            break;
        case "paytoshi":
            $r = $this->service_instance->send($to, $amount, $referral);
            $check_url = "https://paytoshi.org/".rawurlencode($to)."/balance";
            $success = $r['success'];
            $balance = null;
            $error = array_key_exists("message", $r) ? $r['message'] : null;
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "faucetsystem":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
            $check_url = "https://faucetsystem.com/check/".rawurlencode($to);
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "faucethub":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
            $check_url = "https://faucethub.io/check/".rawurlencode($to);
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "faucetfly":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
            $check_url = "https://faucetfly.com/check/".rawurlencode($to);
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        }

        $sname = self::$services[$this->service]["name"];
        $result = [];
        $result['success'] = $success;
        $result['response'] = json_encode($r);
        if($success) {
            $result['message'] = 'Payment sent to you using '.$sname;
            $result['html'] = '<div class="alert alert-success">' . htmlspecialchars($amount) . " satoshi was sent to you <a target=\"_blank\" href=\"$check_url\">on $sname</a>.</div>";
            $result['html_coin'] = '<div class="alert alert-success">' . htmlspecialchars(rtrim(rtrim(sprintf("%.8f", $amount/100000000), '0'), '.')) . " " . $this->currency . " was sent to you <a target=\"_blank\" href=\"$check_url\">on $sname</a>.</div>";
            $result['balance'] = $balance;
            if($balance) {
                $result['balance_bitcoin'] = sprintf("%.8f", $balance/100000000);
            } else {
                $result['balance_bitcoin'] = null;
            }
        } else {
            $result['message'] = $error;
            $result['html'] = '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>';
        }
        return $result;
    }

    public function sendReferralEarnings($to, $amount, $userip) {
        return $this->send($to, $amount, $userip, "true");
    }

    public function getPayouts($count) {
        switch($this->service) {
        case "faucetbox":
            return $this->service_instance->getPayouts($count);
            break;
        }
        return [];
    }

    public function getCurrencies() {
        switch($this->service) {
        case "faucetbox":
            return $this->service_instance->getCurrencies();
            break;
        }
        return self::$services[$this->service]["currencies"];
    }

    public function getBalance() {
        switch($this->service) {
        case "faucetbox":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;
        case "epay":
            $balance = $this->service_instance->getBalance();
            return array("status" => $balance >= 0 ? 200 : 403, "balance" => $balance, "balance_bitcoin" => $balance/100000000);
        case "paytoshi":
            $balance = $this->service_instance->getBalance();
            if(!is_array($balance) || !array_key_exists("available_balance", $balance)) {
                return array("status" => 403);
            }
            $balance = $balance["available_balance"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return array(
                "status" => 200,
                "balance" => $balance,
                "balance_bitcoin" => $balance/100000000
            );
        case "faucetsystem":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;
        case "faucethub":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;

        case "faucetfly":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;
        }

        die("Database is broken. Please reinstall the script.");
    }

    public function fiabVersionCheck() {
        if($this->service == "faucetbox") {
            $fbox = $this->service_instance;
        } else {
            $fbox = new FaucetBOX("", "BTC", $this->options);
        }
        return $fbox->fiabVersionCheck();
    }
}
