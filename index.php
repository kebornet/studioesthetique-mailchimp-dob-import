<?php

set_time_limit(0);

require_once __DIR__ . '/vendor/autoload.php';

use MailchimpDobImport\GmailMessages;
use MailchimpDobImport\GoogleClient;
use MailchimpDobImport\MailchimpContacts;

try {
    $config = require __DIR__ . '/config.php';

    $googleClient = new GoogleClient();
    $client = $googleClient->getClient($config['google']);

    $serviceGmail = new GmailMessages($client);
    $userEmail = $config['gmail']['userEmail'];
    $savedAttachments = $serviceGmail->saveMessageAttachments($userEmail);

    $serviceMailchimp = new MailchimpContacts($config['mailchimp']);
    $serviceMailchimp->setBirthdayContacts($savedAttachments);
} catch (Exception $e) {
    exit('Caught exception: ' . $e->getMessage());
}
