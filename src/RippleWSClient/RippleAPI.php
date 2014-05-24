<?php

namespace RippleWSClient;

use RippleWSClient\Error\RippleAPIException;
use RippleWSClient\Util\CurrencyUtil;

use \Exception;

/*
* RippleAPI
*/
class RippleAPI
{

    const OFFER_TYPE_BUY  = 0;
    const OFFER_TYPE_SELL = 1;
    const FLAG_SELL       = 524288;

    protected $SECRET;

    public function __construct(Connection $connection, $secret=null)
    {
        $this->connection = $connection;

        if ($secret !== null) { $this->SECRET = $secret; }
    }

    public function currentLedgerIndex() {
        $info  = $this->ledgerInfo('validated');
        return $info['ledger_index'];
    }


    public function ledgerInfo($ledger_index) {
        $result = $this->wsCall('ledger', array(
            'ledger_index' => $ledger_index,
        ));

        return $result['ledger'];
    }


    public function basicIOUSend($account, $destination, $currency_amount, $currency_type, $issuer)
    {
        $secret = $this->SECRET;
       // Debug::trace("basicIOUSend(".Debug::desc($account).", ".Debug::desc($destination).", ".Debug::desc($currency_amount).", ".Debug::desc($currency_type).", ".Debug::desc($issuer).", ".substr($secret,0,2).")",__FILE__,__LINE__,$this);

        // sign
        $result = $this->wsCall('sign', array('secret' => $secret, 'tx_json' => array(
            'TransactionType' => 'Payment',
            'Account'         => $account,
            'Destination'     => $destination,
            'Amount'          => array(
                'currency' => $currency_type,
                'value'    => (string)$currency_amount,
                'issuer'   => $issuer,
            ),
        )));
        $tx_blob = $result['tx_blob'];
#        Debug::trace("\$tx_blob=".Debug::desc($tx_blob)."",__FILE__,__LINE__,$this);

        // submit
        $result = $this->submit($tx_blob);
        // Debug::trace("\$result=",$result,__FILE__,__LINE__,$this);
        $this->checkEngineResultCode($result);

        return $result;
    }


    public function XRPSend($account, $destination, $xrp_amount)
    {
        // sign
        $result = $this->wsCall('sign', array('secret' => $this->SECRET, 'tx_json' => array(
            'TransactionType' => 'Payment',
            'Account'         => $account,
            'Destination'     => $destination,
            'Amount'          => intval($xrp_amount * CurrencyUtil::DROP_SIZE),
        )));
        $tx_blob = $result['tx_blob'];

        // submit
        $result = $this->submit($tx_blob);
        return $result;
    }

    public function sellForXRP($account, $xrp_amount_to_receive, $sell_info) {
        return $this->createOffer($account, $xrp_amount_to_receive, $sell_info, self::OFFER_TYPE_SELL);
    }
    public function buyForXRP($account, $buy_info, $xrp_to_spend) {
        return $this->createOffer($account, $buy_info, $xrp_to_spend, self::OFFER_TYPE_BUY);
    }

    public function createOffer($account, $buy_info, $sell_info, $offer_type)
    {
        // sign
        // tfSell (0x00080000) = The hexadecimal number 00080000 is equal to the decimal number 524288.
        $flags = 0;
        if ($offer_type == self::OFFER_TYPE_SELL) {
            $flags = $flags | self::FLAG_SELL;
        }

        $tx_json = array(
            'TransactionType' => 'OfferCreate',
            'Account'         => $account,
            'TakerPays'       => $this->normalizeCurrencyParameter($buy_info),
            'TakerGets'       => $this->normalizeCurrencyParameter($sell_info),
            'Flags'           => $flags,
        );
#        Debug::trace("tx_json=",$tx_json,__FILE__,__LINE__,$this);
        $result = $this->wsCall('sign', array('secret' => $this->SECRET, 'tx_json' => $tx_json));
#        Debug::trace("sign result: ".Debug::desc($result)."",__FILE__,__LINE__,$this);
        $tx_blob = $result['tx_blob'];

        // submit
        $result = $this->submit($tx_blob);

        $this->checkEngineResultCode($result);

        return $result;
    }

    // "TransactionType" : "OfferCancel",
    // "Account" : "rMmTCjGFRWPz8S2zAUUoNVSQHxtRQD4eCx",
    // "OfferSequence" : "5"

    public function cancelOffer($account, $seq)
    {
        // sign
        $result = $this->wsCall('sign', array('secret' => $this->SECRET, 'tx_json' => array(
            'TransactionType' => 'OfferCancel',
            'Account'         => $account,
            'OfferSequence'   => $seq,
        )));
        $tx_blob = $result['tx_blob'];

        // submit
        $result = $this->submit($tx_blob);

        $this->checkEngineResultCode($result);
        return $result;
    }


      // "TransactionType" : "TrustSet",
      // "Account" : "rMmTCjGFRWPz8S2zAUUoNVSQHxtRQD4eCx",
      // "LimitAmount" : { "currency" : "USD", "value" : "100", "issuer" : "r3kmLJN5D28dHuH8vZNUZpMC43pEHpaocV" }
    public function trustSet($account, $limit_amount)
    {
        // sign
        $result = $this->wsCall('sign', array('secret' => $this->SECRET, 'tx_json' => array(
            'TransactionType' => 'TrustSet',
            'Account'         => $account,
            'LimitAmount'     => $this->normalizeCurrencyParameter($limit_amount),
        )));
        $tx_blob = $result['tx_blob'];

        // submit
        $result = $this->submit($tx_blob);

        $this->checkEngineResultCode($result);
        return $result;
    }


    public function getAccountOffers($account)
    {
        // sign
        $result = $this->wsCall('account_offers',  array(
            'account'         => $account,
        ));
        return $result;
    }

    public function getBookOffers($taker_pays, $taker_gets, $ledger_index = null)
    {
        $args = array(
            'taker_pays' => $taker_pays, // 'XRP',
            'taker_gets' => $taker_gets, // array('currency' => 'GKO', 'issuer' => 'xyz'),
        );
        if ($ledger_index !== null) { $args['ledger_index'] = $ledger_index; }
        $result = $this->wsCall('book_offers',  $args);
        return $result;
    }


    public function accountInfo($account) {
        try {
            $result = $this->wsCall('account_info', array(
                'account' => $account,
            ));
            return $result['account_data'];
        } catch (Exception $e) {
            if ($e->getCode() == 14) {
                // account not found
                return null;
            }
            throw $e;
        }
    }


    public function combinedAccountTransactions($account, $limit=200, $ledger_index_min=-1, $ledger_index_max=-1) {
        $done = false;
        $marker = null;

        $combined_transactions = [];
        while (!$done) {
            $vars = [
                'account'          => $account,
                'limit'            => $limit,
                'ledger_index_min' => $ledger_index_min,
                'ledger_index_max' => $ledger_index_max,
            ];
            if ($marker !== null) { $vars['marker'] = $marker; }


            $result = $this->wsCall('account_tx', $vars);
#            Debug::trace("\$result['marker']=".Debug::desc($result['marker'])."",__FILE__,__LINE__,$this);


            // combine the transactions
            $combined_transactions = array_merge($combined_transactions, $result['transactions']);


            if (isset($result['marker']) AND $result['marker']) {
                $marker = $result['marker'];
            } else {
                // we're done!
                $done = true;
            }

        }

        return $combined_transactions;
    } 


    public function accountTransactions($account, $limit=200, $ledger_index_min=-1, $ledger_index_max=-1) {
        $result = $this->wsCall('account_tx', array(
            'account'          => $account,
            'limit'            => $limit,
            'ledger_index_min' => $ledger_index_min,
            'ledger_index_max' => $ledger_index_max,
        ));

        return array(
            'transactions'     => $result['transactions'],
            'ledger_index_min' => $result['ledger_index_min'],
            'ledger_index_max' => $result['ledger_index_max'],
        );
    }

    public function transactionHistory($limit=200) {
        $result = $this->wsCall('tx_history', array(
            'start' => $limit,
        ));

        return $result['txs'];
        // return array(
        //     'transactions'     => $result['transactions'],
        //     'ledger_index_min' => $result['ledger_index_min'],
        //     'ledger_index_max' => $result['ledger_index_max'],
        // );
    }

    // public function getEntryByLedgerIndex($ledger_index) {
    //     $result = $this->wsCall('ledger', array(
    //         'ledger_index' => $ledger_index,
    //     ));

    //     return $result['ledger'];
    // }





    public function serverInfo() {
        try {
            $result = $this->wsCall('server_info', null);
        } catch (Exception $e) {
            // Debug::errorTrace("ERROR: ".$e->getMessage(),__FILE__,__LINE__,$this);
            throw $e;
        }
        return $result;
    }


    public function getTransaction($transaction)
    {
        $result = $this->wsCall('tx',  array(
            'transaction' => $transaction,
        ));
        return $result;
    }

    public function getTrustLines($address)
    {
        // sign
        $result = $this->wsCall('account_lines',  array(
            'account' => $address,
        ));
        return $result;
    }

    public function activeTrustLineCount($address, $currency_code)
    {
        $out = $this->trustLineCountAndBalance($address, $currency_code);
        return $out['activeTrustLines'];
    }

    public function totalTrustLineCount($address, $currency_code)
    {
        $out = $this->trustLineCountAndBalance($address, $currency_code);
        return $out['totalTrustLines'];
    }

    public function balance($address, $currency_code)
    {
        $out = $this->trustLineCountAndBalance($address, $currency_code);
        return $out['balance'];
    }

    public function trustLineCountAndBalance($address, $currency_code)
    {
        $active_line_count_by_currency = [];
        $total_line_count_by_currency = [];
        $balance_by_currency = [];

        $result = $this->getTrustLines($address);
        foreach ($result['lines'] as $line) {
            if (!isset($total_line_count_by_currency[$line['currency']])) { $total_line_count_by_currency[$line['currency']] = 0; }
            if (!isset($active_line_count_by_currency[$line['currency']])) { $active_line_count_by_currency[$line['currency']] = 0; }
            if (!isset($balance_by_currency[$line['currency']])) { $balance_by_currency[$line['currency']] = 0; }

            // add balances
            if ($line['limit_peer'] > 0 AND $line['balance'] < 0) {
                // forward
                $balance = 0 - $line['balance'];
            } else if ($line['limit'] > 0) {
                // reverse
                // this is a case where the issuer is trusting someone else for the same currency

                // $balance = $line['balance'];

                $balance = 0;
            } else {
                $balance = 0;
            }
            $balance_by_currency[$line['currency']] += $balance;

            ++$total_line_count_by_currency[$line['currency']];

            // count a trust line if it is greater than 0
            if (!($line['limit'] == 0 AND $line['limit_peer'] == 0)) {
                ++$active_line_count_by_currency[$line['currency']];
            }

        }

        return [
            'activeTrustLines' => $active_line_count_by_currency[$currency_code],
            'totalTrustLines'  => $total_line_count_by_currency[$currency_code],
            'balance'          => $balance_by_currency[$currency_code],
        ];
    }

    ////////////////////////////////////////////////////////////////////////
    // protected

    protected function submit($tx_blob) {
        $result = $this->wsCall('submit', array('tx_blob' => $tx_blob));
        if ($result['engine_result_code'] < 0) {
            throw new Exception("Error: {$result['engine_result_message']}", $result['engine_result_code']);
        }

        return $result;
    }

    protected function wsCall($method, $params=[]) {
        $done = false;
        while (!$done) {
            try {
                $raw_response = $this->rawWSCall($method, $params);
                break;
            } catch (RippleAPIException $e) {
                Debug::trace("error=".$e->getRippleErrorName(),__FILE__,__LINE__,$this);
                if ($e->getRippleErrorName() == 'slowDown') {
                    $sleep = rand(50000, 1000000);
                    Debug::trace("slow down request received. sleeping for $sleep...",__FILE__,__LINE__,$this);

                    // slow down
                    usleep($sleep); // 50ms - 1 second
                } else {
                    throw $e;
                }
            }

        }

        return $raw_response;
    }

    protected function rawWSCall($method, $params=[]) {
        // 'id' => $this->guid()
        $json_out = array_merge(['command' => $method], $params);


        // Send the request and parse the JSON response into an array
        $response_string = $this->connection->send(json_encode($json_out));
        if (!$response_string) { throw new Exception("No response", 1); }
        $response = json_decode($response_string, true);
        if (!$response) { throw new Exception("Unexpected response: $response_string", 1); }

        $result = isset($response['result']) ? $response['result'] : null;

        if (isset($result['error'])) {
            // [error] => actNotFound
            // [error_code] => 14
            // [error_message] => Account not found.
            if (isset($result['error_message'])) {
                throw new RippleAPIException("Ripple error: {$result['error_message']} ({$result['error_code']})", $result['error_code'], $result);
            }
            if (isset($result['error_exception'])) {
                throw new RippleAPIException("Ripple error: {$result['error_exception']} ({$result['error']})", 1, $result);
            }

            throw new RippleAPIException("Ripple error: {$result['error']}", 1, $result);
        }

        if (!isset($response['result'])) { throw new Exception("Unexpected Response: ".json_encode($response), 1, $result); }
        
        if ($response['status'] != 'success') {
            throw new RippleAPIException("Error: ".json_encode($result), 1, $result);
        }

        return $result;
    }

    protected function checkEngineResultCode($result) {
        if ($result['engine_result_code'] > 0) {
            throw new RippleAPIException("Error: {$result['engine_result_message']}", $result['engine_result_code'], $result);
        }
    }

    protected function normalizeCurrencyParameter($info_in) {
        if (is_array($info_in)) {
            // { "currency" : "USD", "value" : "120", "issuer" : "rMmTCjGFRWPz8S2zAUUoNVSQHxtRQD4eCx" }
            if (!isset($info_in['currency'])) { throw new Exception("Missing parameter currency", 1); }
            if (!isset($info_in['value'])) { throw new Exception("Missing parameter value", 1); }
            if (!isset($info_in['issuer']) OR !strlen($info_in['issuer'])) { throw new Exception("Missing parameter issuer", 1); }

            $currency_type = strtoupper($info_in['currency']);
            $value = doubleval($info_in['value']);
            if ($value <= 0) { throw new Exception("Unexpected value {$info_in['value']}", 1); }

            if ($currency_type == 'XRP') {
                // special case for XRP
                return (string)(intval($value * CurrencyUtil::DROP_SIZE));
            }

            return array(
                'currency' => $currency_type,
                'value'    => (string)(CurrencyUtil::roundRippleCurrencyValue($value)),
                'issuer'   => $info_in['issuer'],
            );
        } else {
            // assume XRP
            $value = doubleval($info_in);
            if ($value <= 0) { throw new Exception("Unexpected value {$info_in['value']}", 1); }

            return (string)(intval($value * CurrencyUtil::DROP_SIZE));
        }
    }

    // protected function guid() {
    //     $charid = strtoupper(md5(uniqid(rand(), true)));
    //     return
    //         substr($charid, 0, 8).'-'
    //         .substr($charid, 8, 4).'-'
    //         .substr($charid,12, 4).'-'
    //         .substr($charid,16, 4).'-'
    //         .substr($charid,20,12);
    // }


}
