<?php

namespace MailchimpDobImport;

use MailchimpMarketing\ApiClient;
use Exception;

class MailchimpContacts
{
    const EMAIL_COLUMN_NUMBER = 2;
    const BIRTHDAY_COLUMN_NUMBER = 1;

    private ApiClient $serviceMailchimp;

    public function __construct(array $configMailchimp)
    {
        $this->serviceMailchimp = new ApiClient();
        $this->setConfigMailchimp($configMailchimp);
        $this->pingServiceMailchimp();
    }

    /**
     * Set config Mailchimp.
     *
     * @param array $mailchimpSettings Mailchimp account settings.
     */
    private function setConfigMailchimp(array $mailchimpSettings): void
    {
        $this->serviceMailchimp->setConfig([
            'apiKey' => $mailchimpSettings['apiKey'],
            'server' => $mailchimpSettings['server']
        ]);
    }

    /**
     * Check successful connection to Mailchimp.
     *
     */
    private function pingServiceMailchimp(): void
    {
        try {
            $this->serviceMailchimp->ping->get();
        } catch (Exception $e) {
            exit('Connection error with Mailchimp API. Check your Mailchimp settings API in config');
        }
    }

    /**
     * Set birthday contacts in Mailchimp.
     *
     * @param array $savedAttachments saved attachments.
     */
    public function setBirthdayContacts(array $savedAttachments): void
    {
        $mailchimpListsApi = $this->serviceMailchimp->lists;
        $listId = $mailchimpListsApi->getAllLists()->lists[0]->id;

        $contactsMailchimp = $mailchimpListsApi->getListMembersInfo($listId)->members;

        $numberOfPatients = 0;
        $numberOfUpdatedContacts = 0;

        foreach ($savedAttachments as  $savedAttachment) {
            if (file_exists($savedAttachment) && ($csvFile = fopen($savedAttachment, 'r')) !== false) {
                while (($patientData = fgetcsv($csvFile, 0)) !== false) {
                    $numberOfPatients++;
                    if ($this->isValidRow($patientData)) {
                        $patientEmail = $patientData[self::EMAIL_COLUMN_NUMBER];
                        $patientBirthday = $patientData[self::BIRTHDAY_COLUMN_NUMBER];
                        foreach ($contactsMailchimp as $contactMailchimp) {
                            $contactEmail = $contactMailchimp->email_address;
                            if (($patientEmail === $contactEmail) && empty($contactMailchimp->merge_fields->BIRTHDAY)) {
                                $numberOfUpdatedContacts++;
                                $mailchimpListsApi->updateListMember($listId, $contactEmail, [
                                    'merge_fields' => [
                                        'BIRTHDAY' => $this->getBirthdayFormatMmDd($patientBirthday)
                                    ]
                                ]);
                            }
                        }
                    }
                }
                fclose($csvFile);
            } else {
                throw new Exception('Failed to open csv file. Check file name');
            }
        }
        echo "Number of treated patients from csv file - $numberOfPatients. Number of updated contact information in Mailchimp - $numberOfUpdatedContacts\n";
    }

    /**
     * Сsv file row validation check.
     *
     * @param  array $patientData patient info from csv file.
     * @return boolean if row valid - true, else - false.
     */
    private function isValidRow(array $patientData): bool
    {
        return (isset($patientData[self::BIRTHDAY_COLUMN_NUMBER]) && isset($patientData[self::EMAIL_COLUMN_NUMBER]));
    }

    /**
     * Get birthday in the format MM/DD.
     *
     * @param  string $patientBirthday patient birthday in format MM/DD/YYYY.
     * @return string birthday in the format MM/DD.
     */
    private function getBirthdayFormatMmDd(string $patientBirthday): string
    {
        if (!preg_match('@(\d{1,2}/\d{1,2})/\d{4}@', $patientBirthday, $contactBirthday)) {
            throw new Exception('Unable to get patient birthday in the format MM/DD');
        }

        return $contactBirthday[1];
    }
}
