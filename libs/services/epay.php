<?php

class ePay {
    protected $client;
    protected $api_key;
    protected $currency;
    public function __construct($api_key, $currency) {
        $this->api_key = $api_key;
        $this->currency = $currency;
        $this->client = null;
    }

    private function connect() {
        $this->client = new SoapClient('https://api.epay.info/?wsdl');
    }

    private function translateStatus($st) {
        if($st > 0) {
            return "";
        } else if ($st === -2) {
            return "Wrong API code";
        } else if ($st === -3) {
            return "Not enough balance";
        } else if ($st === -4) {
            return "API error: one of mandatory parameters is missing";
        } else if ($st === -5) {
            return "API error: payment is sooner than the calculated time out";
        } else if ($st === -6) {
            return "API error: ACL is active and server IP address is not authorized";
        } else if ($st === -7) {
            return "API error: proxy detected";
        } else if ($st === -8) {
            return "API error: user country is blocked.";
        } else if ($st === -10) {
            return "API error: daily budget reached";
        } else if ($st === -11) {
            return "API error: time-frame limit reached";
        } else {
            return "API error code: $st";
        }
    }
    public function send($to, $amount, $referral, $userip) {
        if(!$this->client) $this->connect();
        if($referral)
            $resp = $this->client->send($this->api_key, $to, $amount, 2, 'Referral earnings.', $userip);
        else
            $resp = $this->client->send($this->api_key, $to, $amount, 1, null, $userip);
        $resp["error_msg"] = $this->translateStatus($resp["status"]);
        return $resp;
    }

    public function getBalance() {
        if(!$this->client) $this->connect();
        return $this->client->f_balance($this->api_key, 1);
    }
}
