<?php

namespace RippleWSClient\Error;

use \Exception;

/*
* RippleAPIException
*/
class RippleAPIException extends Exception
{

            // [error] => actNotFound
            // [error_code] => 14
            // [error_message] => Account not found.
    public function __construct($message, $code, $ripple_result=null) {
        parent::__construct($message, $code);

        if ($ripple_result AND is_array($ripple_result)) {
            if (isset($ripple_result['error'])) { $this->setRippleErrorName($ripple_result['error']); }
            if (isset($ripple_result['error_code'])) { $this->setRippleErrorCode($ripple_result['error_code']); }
            if (isset($ripple_result['error_message'])) { $this->setRippleErrorMessage($ripple_result['error_message']); }
        }
    }

    public function setRippleErrorName($error_name) {
        $this->error_name = $error_name;
    }
    public function setRippleErrorMessage($error_string) {
        $this->error_string = $error_string;
    }
    public function setRippleErrorCode($error_code) {
        $this->error_code = $error_code;
    }


    public function getRippleErrorName() {
        return $this->error_name;
    }
    public function getRippleErrorMessage() {
        return $this->error_string;
    }
    public function getRippleErrorCode() {
        return $this->error_code;
    }
}
