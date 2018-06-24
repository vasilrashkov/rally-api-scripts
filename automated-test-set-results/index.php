<?php

require 'config.php';
/** @var $config */

$time = $config['time'];
$testSetId = $config['test_set_id'];

$baseUrlForTestSet = "https://rally1.rallydev.com/slm/webservice/v2.0/testset/$testSetId";

function getTestCases($pageSize = 20, $testSetId, $authString)
{
    $urlForTestSet = "https://rally1.rallydev.com/slm/webservice/v2.0/TestSet/$testSetId/TestCases?pagesize=$pageSize";
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $urlForTestSet,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $authString,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        die("cURL Error #:" . $err);
    }

    return json_decode($response, true);
}

function createTestResult($authKey, $data, $cookies, $authString)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://rally1.rallydev.com/slm/webservice/v2.0/testcaseresult/create?key=$authKey",
        CURLOPT_USERPWD => $authString,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "content-type: application/json",
            "Cookie: $cookies"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        die("cURL Error #:" . $err);
    }

    return json_decode($response, true);
}

$firstBatchTestCases = getTestCases(10, $testSetId, $config['rally_credentials']);
$allTestCases = getTestCases($firstBatchTestCases['QueryResult']['TotalResultCount'] + 1, $testSetId, $config['rally_credentials'])['QueryResult']['Results'];

function curlResponseHeaderCallback($ch, $headerLine)
{
    global $cookies;
    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1)
        $cookies[] = $cookie;
    return strlen($headerLine); // Needed by curl
}

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "https://rally1.rallydev.com/slm/webservice/v2.0/security/authorize",
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $config['rally_credentials'],
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADERFUNCTION => 'curlResponseHeaderCallback',
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    die('could not authenticate!');
}
$authResponse = json_decode($response, true);
$authToken = $authResponse['OperationResult']['SecurityToken'];
if (!$authToken) {
    die('could not authenticate!');
}

$cookie = '';
foreach ($cookies as $item) {
    $cookie .= $cookie == '' ? $item[1] : '; ' . $item[1];
}

$count = 0;
foreach ($allTestCases as $testCase) {
    $urlForTestCase = $testCase['_ref'];
    if (!$urlForTestCase) {
        continue;
    }
    $data = array(
        'TestCaseResult' => array(
            'Build' => '#133 from master',
            'Date' => $time,
            'TestCase' => array(
                '_ref' => $urlForTestCase
            ),
            'TestSet' => array(
                '_ref' => $baseUrlForTestSet
            ),
            'Notes' => 'Testing Environment: Chrome',
            'Verdict' => 'Pass'

        )
    );

    createTestResult($authToken, $data, $cookie, $config['rally_credentials']);
    $count++;
}

var_dump($count);
