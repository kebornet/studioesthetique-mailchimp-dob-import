<?php

namespace MailchimpDobImport;

use Google_Client;
use Google_Service_Gmail;
use Exception;

class GmailMessages
{
    const SEARCH_MESSAGES_SUBJECT = 'subject:(PATIENT BIRTHDAYS)';
    const SEARCH_LABEL = 'INBOX';
    const SEARCH_ATTACHMENT_FILE_NAME = 'PATIENT BIRTHDAYS.csv';
    const FILE_VALUE_LAST_TIME_GET_MESSAGES = __DIR__ . '/../time.txt';
    const FILE_VALUES_GET_MESSAGE_IDS = __DIR__ . '/../messageIds.txt';

    private Google_Service_Gmail $serviceGmail;

    public function __construct(Google_Client $client)
    {
        $this->serviceGmail = new Google_Service_Gmail($client);
    }

    /**
     *  Get message attachments from inbox mailbox.
     * 
     * @param string $userEmail user Gmail email.
     * @return array saved attachments.
     */
    public function saveMessageAttachments(string $userEmail): array
    {
        $savedAttachments = [];

        $optionParams = [
            'q' => self::SEARCH_MESSAGES_SUBJECT,
            'labelIds' => self::SEARCH_LABEL
        ];

        $messageIds = $this->getMessageIds($userEmail, $optionParams);
        $lastMessageIds = $this->getLastMessageIds();

        $saveMessageIds = [];

        foreach ($messageIds as $messageId) {
            if (!in_array($messageId, $lastMessageIds)) {
                $messageDetails = $this->getMessageDetails($userEmail, $messageId);
                $attachmentMessage = $this->getAttachment($messageDetails, $userEmail, $messageId);
                $savedAttachments[] = $this->saveAttachment($attachmentMessage);
                $saveMessageIds[] = $messageId;
            }
        }
        $this->saveMessageIds($saveMessageIds);

        return $savedAttachments;
    }

    /**
     *  Get message IDs from inbox mailbox.
     * 
     * @param  string $userEmail user Gmail email.
     * @param  array $optionParams additional options for search messages.
     * @return array message IDs
     */
    private function getMessageIds(string $userEmail, array $optionParams): array
    {
        $messagesInbox = [];
        $messageIds = [];
        $nextPageTokenInbox = null;

        if ($lastTimeGetMessages = $this->lastTimeGetMessages()) {
            $optionParams['q'] = $optionParams['q'] . ' ' . 'after:' . $lastTimeGetMessages;
        }

        do {
            if ($nextPageTokenInbox) {
                $optionParams['pageToken'] = $nextPageTokenInbox;
            }
            $usersMessages = $this->serviceGmail->users_messages->listUsersMessages($userEmail, $optionParams);

            if ($usersMessages->getMessages()) {
                $messagesInbox = array_merge($messagesInbox, $usersMessages->getMessages());
                $nextPageTokenInbox = $usersMessages->getNextPageToken();
            } else {
                throw new Exception('Unable to get list user messages');
            }
        } while ($nextPageTokenInbox);

        $this->setTimeLastGetMessages();

        foreach ($messagesInbox as $messageInbox) {
            $messageIds[] = $messageInbox->getId();
        }

        return $messageIds;
    }

    /**
     * Last time get messages.
     * 
     * @return string last time get messages.
     */
    private function lastTimeGetMessages(): string
    {
        if (!file_exists(self::FILE_VALUE_LAST_TIME_GET_MESSAGES)) {
            return false;
        }

        return file_get_contents(self::FILE_VALUE_LAST_TIME_GET_MESSAGES);
    }

    /**
     * Set time last get messages in text file.
     */
    private function setTimeLastGetMessages(): void
    {
        file_put_contents(self::FILE_VALUE_LAST_TIME_GET_MESSAGES, date('Y/m/d'));
    }

    /**
     * Get last message IDs from text file.
     * 
     * @return array last message IDs from text file.
     */
    private function getLastMessageIds(): array
    {
        $messageIds = [];

        if (file_exists(self::FILE_VALUES_GET_MESSAGE_IDS)) {
            if (($fp = fopen(self::FILE_VALUES_GET_MESSAGE_IDS, 'r')) !== false) {
                while (($lastMessageId = fgets($fp)) !== false) {
                    $messageIds[] = trim($lastMessageId);
                }
                fclose($fp);
            } else {
                throw new Exception('Unable to get last Message IDs from text file');
            }
        }

        return $messageIds;
    }

    /**
     * Save message IDs in text file.
     * 
     * @param array $messageIds message IDs.
     */
    private function saveMessageIds(array $messageIds): void
    {
        if (($fp = fopen(self::FILE_VALUES_GET_MESSAGE_IDS, 'w')) !== false) {
            foreach ($messageIds as $messageId) {
                fwrite($fp, $messageId . PHP_EOL);
            }
            fclose($fp);
        } else {
            throw new Exception('Unable to save message ID in text file');
        }
    }

    /**
     * Returns the details message.
     * 
     * @param string $userEmail user Gmail email.
     * @param string $messageId message id.
     * @return array message details parts.
     */
    private function getMessageDetails(string $userEmail, string $messageId): array
    {
        $userMessage = $this->serviceGmail->users_messages->get($userEmail, $messageId);

        if (!$userMessageDetails = $userMessage->getPayload()) {
            throw new Exception('Unable to get user message');
        }

        return $userMessageDetails['parts'];
    }

    /**
     * Get the attachment.
     * 
     * @param  array $messageDetails message details parts.
     * @param string $userEmail user Gmail email.
     * @param string $messageId message id.
     * @return array attachment details.
     */
    private function getAttachment(array $messageDetails, string $userEmail, string $messageId): array
    {
        $attachmentPartId = null;

        foreach ($messageDetails as $messageDetail) {
            if (!empty($messageDetail['filename']) && $messageDetail['filename'] == self::SEARCH_ATTACHMENT_FILE_NAME) {
                $attachmentPartId = $messageDetail['partId'];
            }
        }

        $attachmentDetails = [
            'attachmentId' => $messageDetails[$attachmentPartId]['body']['attachmentId']
        ];
        $attachmentBody = $this->serviceGmail->users_messages_attachments->get($userEmail, $messageId, $attachmentDetails['attachmentId']);

        if (!$attachmentBody->data) {
            throw new Exception('Unable to get attachment message');
        }
        $attachmentDetails['data'] = $this->base64Decode($attachmentBody->data);

        return $attachmentDetails;
    }

    /**
     * Save the attachment (CSV).
     * 
     * @param  array $attachmentDetails attachment details.
     * @return string attachment.
     */
    private function saveAttachment(array $attachmentDetails): string
    {
        $csvFileName = __DIR__ . '/../attachments/' . uniqid('PATIENT BIRTHDAYS_') . '.csv';

        if (!file_put_contents($csvFileName, $attachmentDetails['data'])) {
            throw new Exception('Unable to save attachment message');
        }

        return $csvFileName;
    }

    /**
     * Returns a base64 decoded web safe string.
     * @param  string $string The string to be decoded.
     * @return string Decoded string.
     */
    private function base64Decode(string $string): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $string));
    }
}
