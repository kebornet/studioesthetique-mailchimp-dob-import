<?php

namespace MailchimpDobImport;

use Google_Client;
use Exception;

class GoogleClient
{
    const FILE_VALUE_ACCESS_TOKEN = __DIR__ . '/../token.json';

    /**
     * Returns an authorized API client.
     * 
     * @param  array $config authorization parameters.
     * @return Google_Client the authorized client object
     */
    function getClient(array $config): Google_Client
    {
        $client = new Google_Client($config);

        if (file_exists(self::FILE_VALUE_ACCESS_TOKEN)) {
            $accessToken = json_decode(file_get_contents(self::FILE_VALUE_ACCESS_TOKEN), true);
            $client->setAccessToken($accessToken);
        }
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\nEnter verification code: ", $authUrl);
                $authCode = trim(fgets(STDIN));

                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);
            }
            file_put_contents(self::FILE_VALUE_ACCESS_TOKEN, json_encode($client->getAccessToken()));
        }

        if (!$client->getAccessToken()) {
            throw new Exception('Unable the authorized client Google');
        }

        return $client;
    }
}
