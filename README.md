# studioesthetique-mailchimp-dob-import

PHP version: 7.4

Рабочий проект для возможности автоматического обновления данных о ваших контактах в [Mailchimp](https://mailchimp.com/).

## About
The script is designed to set the date of birth of users in the Mailchimp service. First, the script receives a csv file from Gmail using the Gmail API, which contains data on the date of birth of users. The script processes the csv file, connects to the Mailchimp account using the Mailchimp API, and puts the dates of birth in the contacts existing there. The desired contact is determined by email.

## Dependency

### Mailchimp Marketing API

To authenticate a request to the Marketing API, an API key is used, which is created through the Mailchimp account in the Extras->API keys section. Copy the created API key and paste it into the config.php
You also need a server prefix, which is taken from the URL of the account page. For example, you will see https://us19.admin.mailchimp.com/, the `us19` part is the server prefix. Copy the server prefix and paste it into the config.php.
To verify that everything is set up correctly, you need to make a simple request to the Ping endpoint. Hitting this endpoint acts as a health check on the Mailchimp API service
If everything was configured correctly and the Ping request was successful, the response should look like this:
  ```sh
  {
   "health_status": "Everything's Chimpy!"
  }
  ```
