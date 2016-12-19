<?php

class Paytoshi
{
    protected $api_key;
    protected $api_base = "https://paytoshi.org/api/v1/faucet/";
    public $communication_error = false;

    public $options = array(
        /* if disable_curl is set to true, it'll use PHP's fopen instead of
         * curl for connection */
        'disable_curl' => false,

        /* do not use these options unless you know what you're doing */
        'local_cafile' => false,
        'force_ipv4' => false,
        'verify_peer' => true
    );

    public function __construct($api_key, $connection_options = null) {
        $this->api_key = $api_key;
        if($connection_options)
            $this->options = array_merge($this->options, $connection_options);
        $this->curl_warning = false;
    }

    public function __execPHP($url, $params = array()) {
        $opts = array(
            "http" => array(
                "method" => "POST",
                "header" => "Content-type: application/x-www-form-urlencoded\r\nReferer: ".$this->getHost()." (fopen)\r\n",
                "content" => http_build_query($params)
            ),
            "ssl" => array(
                "verify_peer" => $this->options['verify_peer'],
            )
        );
        if($this->options['local_cafile']) {
            $opts["ssl"]["cafile"] = dirname(__FILE__) . '/cacert.pem';
        }
        $ctx = stream_context_create($opts);
        $fp = fopen($url.'?apikey='.urlencode($this->api_key), 'rb', null, $ctx);
        $response = stream_get_contents($fp);
        if($response && !$this->options['disable_curl']) {
            $this->curl_warning = true;
        }
        fclose($fp);
        return $response;
    }

    public function __exec($method, $params = array()) {
        $this->communication_error = false;
        $url = $this->api_base . $method;
        if($this->options['disable_curl']) {
            $response = $this->__execPHP($url, $params);
        } else {
            $response = $this->__execCURL($url, $params);
        }
        if($response) {
            $response = json_decode($response, true);
        }
        if(!$response) {
            $this->communication_error = true;
        }
        return $response;
    }

    private function getHost() {
        if(array_key_exists("HTTP_HOST", $_SERVER)) {
            return $_SERVER["HTTP_HOST"];
        } else {
            return "Unknown";
        }
    }

    public function __execCURL($url, $params = array()) {
        $ch = curl_init($url.'?apikey='.urlencode($this->api_key));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->options['verify_peer']);
        curl_setopt($ch, CURLOPT_REFERER, $this->getHost()." (cURL)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($this->options['local_cafile']) {
            curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
        }
        if($this->options['force_ipv4']) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        $response = curl_exec($ch);
        if(!$response) {
            $response = $this->__execPHP($url, $params);
        }
        curl_close($ch);

        return $response;
    }

    public function send($to, $amount, $referral = "false") {
        $r = $this->__exec("send", array("address" => $to, "amount" => $amount, "referral" => $referral == "true" ? 1 : 0, "ip" => $_SERVER["REMOTE_ADDR"]));
        if (is_array($r) && !array_key_exists("error", $r)) {
            return array(
                "success" => true,
                "response" => json_encode($r)
            );
        } else {
            return array(
                "success" => false,
                "message" => !empty($r["message"]) ? $r["message"] : "Unknown error.",
                "response" => json_encode($r)
            );
        }
    }

    public function sendReferralEarnings($to, $amount) {
        return $this->send($to, $amount, "true");
    }

    public function getBalance() {
        return json_decode(file_get_contents($this->api_base."balance?apikey=".urlencode($this->api_key)), true);
    }
}
