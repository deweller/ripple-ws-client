<?php

namespace RippleWSClient\Util;

use \Exception;

/*
* CurrencyUtil
*/
class CurrencyUtil
{

    const DROP_SIZE = 1000000;

    public static function roundRippleCurrencyValue($value) {
        return round($value, 7);
    }

    public static function roundXRPForSale($xrp_value) {
        return round($xrp_value, 5);
    }

    public static function round($value) {
        return round($value, 7);
    }
    public static function roundAndFormat($value, $places=null) {
        if (!strlen($value)) { return $value; }
        if ($places === null) { $places = 7; }
        $out = number_format($value, $places);
        if (strpos($out, '.') !== false) {
            $out = rtrim($out, '0');
            $out = rtrim($out, '.');
        }

        return $out;
    }

    public static function dropsToXRP($value) {
        return self::roundRippleCurrencyValue($value / self::DROP_SIZE);
    }

    public static function roundAndFormatDrops($value) {
        return self::roundAndFormat($value / self::DROP_SIZE);
    }



    // normalize to array
    public static function normalizeCurrencyToArray($xrp_amount_or_array) {
        if (is_array($xrp_amount_or_array)) {
            $in = $xrp_amount_or_array;
            if (!isset($in['currency'])) { throw new Exception("Missing currency", 1); }
            if (!isset($in['value'])) { throw new Exception("Missing value", 1); }
            if ($in['currency'] !== 'XRP') {
                if (!isset($in['issuer']) OR !strlen($in['issuer'])) { throw new Exception("Missing issuer", 1); }
            }

            $currency_type = strtoupper($in['currency']);
            $value = doubleval($in['value']);

            // allow values below zero
            // if ($value < 0) { throw new Exception("Unexpected value {$in['value']}", 1); }

            return array(
                'currency' => $currency_type,
                'value'    => CurrencyUtil::roundRippleCurrencyValue($value),
                'issuer'   => $in['issuer'],
            );
        } else {
            $xrp_amount = $xrp_amount_or_array;
            return array(
                'currency' => 'XRP',
                'value'    => CurrencyUtil::roundRippleCurrencyValue($xrp_amount),
            );
        }
    }

    // returns $a - $b
    public static function subtractCurrencies($a, $b) {
        if ($a['currency'] != $b['currency']) { throw new Exception("cannot subtract different currencies", 1); }

        $out = $a;
        $out['value'] = $a['value'] - $b['value'];
        return $out;
    }

    public static function addCurrencies($a, $b) {
        if ($a['currency'] != $b['currency']) { throw new Exception("cannot add different currencies", 1); }

        $out = $a;
        $out['value'] = $a['value'] + $b['value'];
        return $out;
    }

}
