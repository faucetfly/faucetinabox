<?php

require_once("libs/cool-php-captcha/captcha.php");

class FiabCoolCaptcha extends SimpleCaptcha {
    public function isValid($text_from_user) {
        $trimmed_text_from_user = trim($text_from_user);

        if(empty($_SESSION[$this->session_var])) {
            return false;
        }

        if(empty($trimmed_text_from_user)) {
            return false;
        }

        $isValid = $_SESSION[$this->session_var] === $trimmed_text_from_user;

        if(!$isValid) {
            // make sure we can't bruteforce captcha...
            $_SESSION[$this->session_var] = null;
        }

        return $isValid;
    }
}
