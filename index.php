<?php

require_once 'vendor/autoload.php';
require 'activities.php';
require 'config.php';

define('RUNTASTIC_SIGN_IN_URL', 'https://www.runtastic.com/en/d/users/sign_in');
define('RUNTASTIC_TYPE_FILE', 'gpx');
define('RUNTASTIC_DATA_DIR', './runtastic');

function uploadActivityToStrava($accessToken, $filePath, $fileName, $fileType, $activityType)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.strava.com/api/v3/uploads');
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'data_type' => $fileType,
        'activity_type' => $activityType,
        'file' => new CurlFile($filePath . '/' . $fileName . '.' . $fileType, 'application/xml', $fileName . '.' . $fileType)
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch));
    curl_close($ch);
    if (isset($res->message) && $res->message == 'Authorization Error') {
        die('Strava authorization error. Verify your Strava Access Token.');
    }
    file_put_contents($filePath . '/' . $fileName . '.strava', '');
}

if(!file_exists(RUNTASTIC_DATA_DIR)) {
    @mkdir(RUNTASTIC_DATA_DIR) or die('Can not create data dir.');
}

foreach($syncConfig as $config) {

    echo 'Sync start for '.$config['runtastic_login'].'<br />';

    $runtasticActivitiesURL = 'https://www.runtastic.com/en/users/' . $config['runtastic_user_url'] . '/sport-sessions/';
    $runtasticUserDataDir = RUNTASTIC_DATA_DIR . '/' . $config['runtastic_user_url'];

    if (!file_exists($runtasticUserDataDir)) {
        @mkdir($runtasticUserDataDir) or die('Can not create user data dir.');
    }

    $csrfToken = @get_meta_tags($runtasticActivitiesURL)['csrf-token'] or die('Can not obtain Runtastic csrf-token.');

    $jar = new GuzzleHttp\Cookie\CookieJar();
    $guzzleClient = new GuzzleHttp\Client();

    try {
        $response = $guzzleClient->request('POST', RUNTASTIC_SIGN_IN_URL, [
            'headers' => [
                'Content-type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Accept-Encoding' => 'deflate',
                'Authorization' => 'com.runtastic.ember',
                'X-CSRF-Token' => $csrfToken,
                'X-Requested-With' => 'XMLHttpRequest',
                'X-App-Version:' => 1.0,
            ],
            'form_params' => [
                'user[email]' => $config['runtastic_login'],
                'user[password]' => $config['runtastic_password'],
                'authenticity_token' => $csrfToken,
                'grant_type' => 'password'
            ],
            'cookies' => $jar
        ]);
    } catch (Exception $e) {
        die('Runtastic auth error. ' . $e->getMessage());
    }

    try {
        $response = $guzzleClient->request('GET', $runtasticActivitiesURL, ['cookies' => $jar]);
    } catch (Exception $e) {
        die('Runtastic load activities error. ' . $e->getMessage());
    }

    preg_match_all('/var index_data = (.*?\]\])/', $response->getBody()->getContents(), $activities, PREG_PATTERN_ORDER);
    if (!count($activities[1])) {
        die('Can not find any Runtastic activities.');
    }

    eval("\$activities = {$activities[1][0]};");

    foreach ($activities as $activity) {

        $fileName = $activity[0];
        $stravaActivityType = @$stravaActivities[array_search($activity[2], $runtasticActivities)];

        if (!file_exists($runtasticUserDataDir . "/{$fileName}." . RUNTASTIC_TYPE_FILE)) {
            try {
                $guzzleClient->request('GET', $runtasticActivitiesURL . $fileName . '.' . RUNTASTIC_TYPE_FILE, [
                    'cookies' => $jar,
                    'save_to' => fopen($runtasticUserDataDir . "/{$fileName}." . RUNTASTIC_TYPE_FILE, 'w')
                ]);
            } catch (Exception $e) {
                die('Runtastic download activities error. ' . $e->getMessage());
            }

        }

        if (!file_exists($runtasticUserDataDir . "/{$fileName}.strava")) {
            uploadActivityToStrava($config['strava_access_token'], $runtasticUserDataDir, $fileName, RUNTASTIC_TYPE_FILE, $stravaActivityType);
        }
    }

    echo 'Sync end for '.$config['runtastic_login'].'<br />';
}