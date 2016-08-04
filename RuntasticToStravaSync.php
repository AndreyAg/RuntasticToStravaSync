<?php

class RuntasticToStravaSync {

    private $runtasticUrl = 'https://www.runtastic.com/en/d/users/sign_in';
    private $runtasticTypeFile = 'gpx';
    private $runtasticDataDir = './data';
    private $stravaUploadUrl = 'https://www.strava.com/api/v3/uploads';

    private $cookieJar;
    private $guzzleClient;

    private $config;

    public $activitiesMap = ['runtasticActivities' => [
        'RUNNING' => 1, 'NORDIC_WALKING' => 2, 'CYCLING' => 3, 'MOUNTAIN_BIKING' => 4, 'OTHER' => 5, 'SKATING' => 6,
        'HIKING' => 7, 'CROSS_COUNTRY_SKIING' => 8, 'SKIING' => 9, 'SNOW_BOARDING' => 10, 'MOTORBIKING' => 11,
        'DRIVING' => 12, 'SNOWSHOEING' => 13, 'RUNNING_TREADMILL' => 14, 'CYCLING_ERGOMETER' => 15, 'ELLIPTICAL' => 16,
        'ROWING' => 17, 'SWIMMING' => 18, 'WALKING' => 19, 'RIDING' => 20, 'GOLFING' => 21, 'RACE_CYCLING' => 22,
        'TENNIS' => 23, 'BADMINTON' => 24, 'SQUASH' => 25, 'YOGA' => 26, 'AEROBICS' => 27, 'MARTIAL_ARTS' => 28,
        'SAILING' => 29, 'WINDSURFING' => 30, 'PILATES' => 31, 'CLIMBING' => 32, 'FRISBEE' => 33, 'STRENGTH_TRAINING' => 34,
        'VOLLEYBALL' => 35, 'HANDBIKE' => 36, 'CROSS_SKATING' => 37, 'SOCCER' => 38, 'SMOVEY_WALKING' => 39,
        'SMOVEY_EXCERCISING' => 40, 'NORDIC_CROSS_SKATING' => 41, 'SURFING' => 42, 'KITE_SURFING' => 43, 'KAYAKING' => 44,
        'BASKETBALL' => 45, 'SPINNING' => 46, 'PARAGLIDING' => 47, 'WAKE_BOARDING' => 48, 'FREECROSSEN' => 49,
        'DIVING' => 50, 'TABLE_TENNIS' => 51, 'HANDBALL' => 52, 'BACK_COUNTRY_SKIING' => 53, 'ICE_SKATING' => 54,
        'SLEDDING' => 55, 'SNOWMAN_BUILDING' => 56, 'SNOWBALL_FIGHT' => 57, 'CURLING' => 58, 'ICE_STOCK' => 59,
        'BIATHLON' => 60, 'KITE_SKIING' => 61, 'SPEED_SKIING' => 62, 'PUSH_UPS' => 63, 'SIT_UPS' => 64, 'PULL_UPS' => 65,
        'SQUATS' => 66, 'AMERICAN_FOOTBALL' => 67, 'BASEBALL' => 68, 'CROSSFIT' => 69, 'DANCING' => 70, 'ICE_HOCKEY' => 71,
        'SKATEBOARDING' => 72, 'ZUMBA' => 73, 'GYMNASTICS' => 74, 'RUGBY' => 75, 'STANDUP_PADDLING' => 76,
        'SIX_PACK_WORKOUT' => 77, 'BUTT_TRAINER_WORKOUT' => 78, 'LEG_TRAINER_WORKOUT' => 80, 'RESULTS_WORKOUT' => 81
    ], 'stravaActivities' => [
        'CYCLING' => 'ride', 'RUNNING' => 'run', 'SWIMMING' => 'swim', 'WORKOUT' => 'workout', 'HIKING' => 'hike',
        'WALKING' => 'walk', 'NORDIC_SKIING' => 'nordicski', 'ALPINE_SKIING' => 'alpineski',
        'BACK_COUNTRY_SKIING' => 'backcountryski', 'ICE_SKATING' => 'iceskate', 'INLINE_SKATING' => 'inlineskate',
        'KITE_SURFING' => 'kitesurf', 'ROLLER_SKIING' => 'rollerski', 'WINDSURFING' => 'windsurf',
        'SNOW_BOARDING' => 'snowboard', 'SNOWSHOEING' => 'snowshoe', 'EBIKE_RIDING' => 'ebikeride', 'VIRTUAL_RIDING' => 'virtualride'
    ]];

    function __construct($config) {

        if (!file_exists($this->runtasticDataDir)) {
            @mkdir($this->runtasticDataDir) or die('Can not create data dir.');
        }

        $this->cookieJar = new GuzzleHttp\Cookie\CookieJar();
        $this->guzzleClient = new GuzzleHttp\Client();

        $this->config = $config;
    }

    private function uploadActivityToStrava($accessToken, $filePath, $fileName, $fileType, $activityType) {
        try {
             $this->guzzleClient->request('POST', $this->stravaUploadUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}"
                ],
                'multipart' => [
                    [
                        'name' => 'data_type',
                        'contents' => $fileType
                    ], [
                        'name' => 'activity_type',
                        'contents' => $activityType
                    ], [
                        'name' => 'file',
                        'filename' => $fileName . '.' . $fileType,
                        'contents' => fopen($filePath . '/' . $fileName . '.' . $fileType, 'r')
                    ]
                ]
            ]);
        } catch (Exception $e) {
            if($e->getCode() == 401) {
                die('Strava authorization error. Verify your Strava Access Token.');
            }
        }

        file_put_contents($filePath . '/' . $fileName . '.strava', '');
        unlink($filePath . '/' . $fileName . '.' . $this->runtasticTypeFile);
    }


    public function sync() {

        foreach ($this->config as $config) {

            echo 'Sync start for ' . $config['runtastic_login'] . '<br />';

            $runtasticActivitiesURL = 'https://www.runtastic.com/en/users/' . $config['runtastic_user_url'] . '/sport-sessions/';
            $runtasticUserDataDir = $this->runtasticDataDir . '/' . $config['runtastic_user_url'];

            if (!file_exists($runtasticUserDataDir)) {
                @mkdir($runtasticUserDataDir) or die('Can not create user data dir.');
            }

            $csrfToken = @get_meta_tags($runtasticActivitiesURL)['csrf-token'] or die('Can not obtain Runtastic csrf-token.');

            try {
                 $this->guzzleClient->request('POST', $this->runtasticUrl, [
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
                    'cookies' => $this->cookieJar
                ]);
            } catch (Exception $e) {
                die('Runtastic auth error. ' . $e->getMessage());
            }

            try {
                $response = $this->guzzleClient->request('GET', $runtasticActivitiesURL, ['cookies' => $this->cookieJar]);
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
                $stravaActivityType = @$this->activitiesMap['stravaActivities'][
                    array_search($activity[2], $this->activitiesMap['runtasticActivities'])
                ];

                if (!file_exists($runtasticUserDataDir . "/{$fileName}." . $this->runtasticTypeFile) &&
                    !file_exists($runtasticUserDataDir . "/{$fileName}.strava")
                ) {

                    try {
                        $this->guzzleClient->request('GET', $runtasticActivitiesURL . $fileName . '.' . $this->runtasticTypeFile, [
                            'cookies' => $this->cookieJar,
                            'save_to' => fopen($runtasticUserDataDir . "/{$fileName}." . $this->runtasticTypeFile, 'w')
                        ]);
                    } catch (Exception $e) {
                        die('Runtastic download activities error. ' . $e->getMessage());
                    }

                }

                if (!file_exists($runtasticUserDataDir . "/{$fileName}.strava")) {
                    $this->uploadActivityToStrava(
                        $config['strava_access_token'],
                        $runtasticUserDataDir,
                        $fileName,
                        $this->runtasticTypeFile,
                        $stravaActivityType
                    );
                }
            }

            echo 'Sync end for ' . $config['runtastic_login'] . '<br />';
        }
    }
}