<?php

use RippleWSClient\Connection;
use RippleWSClient\Error\ConnectionException;
use RippleWSClient\RippleAPI;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class RippleWSClientTest extends \PHPUnit_Framework_TestCase
{

    public function testConnection() {
        $connection = new Connection();
        $connection->connect();

        $response = $connection->send('{"command": "ledger_current"}');
        PHPUnit::assertNotNull($response);
        $response_data = json_decode($response, true);
        // echo json_encode($response_data, JSON_PRETTY_PRINT)."\n";
        PHPUnit::assertGreaterThan(6000000, $response_data['result']['ledger_current_index']);

        $response = $connection->send('{"command": "ledger_current"}');
        PHPUnit::assertNotNull($response);
        $response_data = json_decode($response, true);
        // echo json_encode($response_data, JSON_PRETTY_PRINT)."\n";
        PHPUnit::assertGreaterThan(6000000, $response_data['result']['ledger_current_index']);
    }

    public function testAPI() {
        $api = new RippleAPI(new Connection());


        $index = $api->currentLedgerIndex();
        PHPUnit::assertNotNull($index);
        PHPUnit::assertGreaterThan(6000000, $index);

        $index = $api->currentLedgerIndex();
        PHPUnit::assertNotNull($index);
        PHPUnit::assertGreaterThan(6000000, $index);

    }


/*
    public function testBasicIOUSend() {
        // you'll have to fill these in yourself to run this test
        $SOURCE_ADDRESS = 'rXXXXXXXXXXXXXXXXXXXXX';
        $SECRET = 'sXXXXXXXXXXXXXXXXXXXXX';

        $DEST_ADDRESS = 'rXXXXXXXXXXXXXXXXXXXXX';
        $CURRENCY = 'ABC';
        $ISSUER = 'rXXXXXXXXXXXXXXXXXXXXX';

        $api = new RippleAPI(new Connection(), $SECRET);

        $result = $api->basicIOUSend($SOURCE_ADDRESS, $DEST_ADDRESS, 1, $CURRENCY, $ISSUER);

        PHPUnit::assertNotNull($result);
    }
*/


}
