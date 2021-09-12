<?php

/**
 * @file classes/mail/SMTPMailer.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SMTPMailer
 * @ingroup mail
 *
 * @brief Class defining a simple SMTP mail client (reference RFCs 821 and 2821).
 *
 * TODO: TLS support
 */


import('lib.pkp.classes.mail.Mail');


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If necessary, modify the path in the require statement below to refer to the
// location of your Composer autoload.php file.
require 'vendor/autoload.php';

class SMTPMailer
{

    /** @var $server string SMTP server hostname (default localhost) */
    var $server;

    /** @var $port string SMTP server port (default 25) */
    var $port;

    /** @var $auth string Authentication mechanism (optional) (PLAIN | LOGIN | CRAM-MD5 | DIGEST-MD5) */
    var $auth;

    /** @var $username string Username for authentication (optional) */
    var $username;

    /** @var $password string Password for authentication (optional) */
    var $password;

    /** @var $socket int SMTP socket */
    var $socket;

    /**
     * Constructor.
     */
    function SMTPMailer()
    {
        $this->server = 'email-smtp.us-east-1.amazonaws.com';
        $this->port = 587;
        $this->auth = true;
        $this->username = 'Username';
        $this->password = 'password';
        if (!$this->server)
            $this->server = 'localhost';
        if (!$this->port)
            $this->port = 25;
    }

    /**
     * Send mail.
     * @param $mail Mailer
     * @param $recipients string
     * @param $subject string
     * @param $body string
     * @param $headers string
     */
    function mail(&$mail, $recipients, $subject, $body, $headers = '')
    {

        try {
            $email = new PHPMailer(true);
            $email->isSMTP();
            $from = $mail->getFrom();
            $email->addReplyTo($from['email'],$from['name']);
            $currentUserEmail = 'mails@ijme.in';
            $currentUserName = $from['name'];

            $email->setFrom($currentUserEmail, $currentUserName);

            $email->Username = $this->username;
            $email->Password = $this->password;
            $email->Host = $this->server;
            $email->Port = $this->port;
            $email->SMTPAuth = true;
            $email->SMTPSecure = 'tls';
            //  $email->addCustomHeader('X-SES-CONFIGURATION-SET', $configurationSet);


            // Specify the message recipients.
            $to_email = $mail->getData('recipients')[0]['email'];

            $email->addAddress($to_email);
            import('classes.file.TemporaryFileManager');
            $temporaryFileManager = new TemporaryFileManager();

            $path = $temporaryFileManager->getBasePath();
            //Multiple attachment
            if (!empty($attachments = $mail->persistAttachments)) {
                foreach ($attachments as $attachment) {
                    $fileName = $attachment->getData('fileName');
                    $originalFileName = $attachment->getData('originalFileName');
                    $fileType = $attachment->getData('filetype');
                    $email->addAttachment($path . $fileName, $originalFileName, 'base64', $fileType);
                }
            }


            //Add CCs
            if (!empty($ccs = $mail->getData('ccs'))) {
                foreach ($ccs as $cc) {
                    $email->AddCC($cc['email'], $cc['name']);
                }
            }
            //Add BCCs
            if (!empty($bccs = $mail->getData('bccs'))) {
                foreach ($bccs as $bcc) {
                    $email->AddBCC($bcc['email'], $bcc['name']);
                }
            }
            // Specify the content of the message.


            $email->Subject = $subject;

            if (!empty($mail->getData('body'))) {

                $email_body = $mail->getData('body');
            }
            echo 'body';
            $email->Body = $email_body;

            $email->Send();
//            echo "Email sent!", PHP_EOL;

        } catch (phpmailerException $e) {
            $error = "An error occurred. ";
            error_log('OJS SMTPMailer: ' . $error);
        } catch (Exception $e) {

            $error = "Email not sent.";
            error_log('OJS SMTPMailer: ' . $error);
        }


        return 1;


    }
}

?>
