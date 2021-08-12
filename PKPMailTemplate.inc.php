<?php

// $Id$


import('mail.Mail');

define('MAIL_ERROR_INVALID_EMAIL', 0x000001);

class PKPMailTemplate extends Mail
{

    var $emailKey;

    var $locale;

    var $enabled;

    var $errorMessages;

    var $persistAttachments;
    var $attachmentsEnabled;

    var $skip;

    var $bccSender;

    var $addressFieldsEnabled;

    function PKPMailTemplate($emailKey = null, $locale = null, $enableAttachments = null, $includeSignature = true)
    {
        parent::Mail();
        $this->emailKey = isset($emailKey) ? $emailKey : null;

        // Use current user's locale if none specified
        $this->locale = isset($locale) ? $locale : AppLocale::getLocale();

        // Record whether or not to BCC the sender when sending message
        $this->bccSender = Request::getUserVar('bccSender');

        // If enableAttachments is null, use the default value from the
        // configuration file
        if ($enableAttachments === null) {
            $enableAttachments = Config::getVar('email', 'enable_attachments') ? true : false;
        }

        $user =& Request::getUser();
        if ($enableAttachments && $user) {
            $this->_handleAttachments($user->getId());
        } else {
            $this->attachmentsEnabled = false;
        }

        $this->addressFieldsEnabled = true;
    }

    function _handleAttachments($userId)
    {
        import('file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();

        $this->attachmentsEnabled = true;
        $this->persistAttachments = array();

        $deleteAttachment = Request::getUserVar('deleteAttachment');

        if (Request::getUserVar('persistAttachments') != null) foreach (Request::getUserVar('persistAttachments') as $fileId) {
            $temporaryFile = $temporaryFileManager->getFile($fileId, $userId);
            if (!empty($temporaryFile)) {
                if ($deleteAttachment != $temporaryFile->getId()) {
                    $this->persistAttachments[] = $temporaryFile;
                } else {
                    // This file is being deleted.
                    $temporaryFileManager->deleteFile($temporaryFile->getId(), $userId);
                }
            }
        }

        if (Request::getUserVar('addAttachment') && $temporaryFileManager->uploadedFileExists('newAttachment')) {
            $user =& Request::getUser();

            $this->persistAttachments[] = $temporaryFileManager->handleUpload('newAttachment', $user->getId());
        }
    }

    function hasErrors()
    {
        return ($this->errorMessages != null);
    }

    function isEnabled()
    {
        return $this->enabled;
    }

    function &processAddresses($currentList, &$newAddresses)
    {
        foreach ($newAddresses as $newAddress) {
            $regs = array();
            // Match the form "My Name <my_email@my.domain.com>"
            if (String::regexp_match_get('/^([^<>' . "\n" . ']*[^<> ' . "\n" . '])[ ]*<(?P<email>' . PCRE_EMAIL_ADDRESS . ')>$/i', $newAddress, $regs)) {
                $currentList[] = array('name' => $regs[1], 'email' => $regs['email']);

            } elseif (String::regexp_match_get('/^<?(?P<email>' . PCRE_EMAIL_ADDRESS . ')>?$/i', $newAddress, $regs)) {
                $currentList[] = array('name' => '', 'email' => $regs['email']);

            } elseif ($newAddress != '') {
                $this->errorMessages[] = array('type' => MAIL_ERROR_INVALID_EMAIL, 'address' => $newAddress);
            }
        }
        return $currentList;
    }

    function displayEditForm($formActionUrl, $hiddenFormParams = null, $alternateTemplate = null, $additionalParameters = array())
    {
        import('form.Form');
        $form = new Form($alternateTemplate != null ? $alternateTemplate : 'email/email.tpl');

        $form->setData('formActionUrl', $formActionUrl);
        $form->setData('subject', $this->getSubject());
        $form->setData('body', $this->getBody());

        $form->setData('to', $this->getRecipients());
        $form->setData('cc', $this->getCcs());
        $form->setData('bcc', $this->getBccs());
        $form->setData('blankTo', Request::getUserVar('blankTo'));
        $form->setData('blankCc', Request::getUserVar('blankCc'));
        $form->setData('blankBcc', Request::getUserVar('blankBcc'));
        $form->setData('from', $this->getFromString(false));

        $form->setData('addressFieldsEnabled', $this->getAddressFieldsEnabled());

        $user =& Request::getUser();
        if ($user) {
            $form->setData('senderEmail', $user->getEmail());
            $form->setData('bccSender', $this->bccSender);
        }

        if ($this->attachmentsEnabled) {
            $form->setData('attachmentsEnabled', true);
            $form->setData('persistAttachments', $this->persistAttachments);
        }

        $form->setData('errorMessages', $this->errorMessages);

        if ($hiddenFormParams != null) {
            $form->setData('hiddenFormParams', $hiddenFormParams);
        }

        foreach ($additionalParameters as $key => $value) {
            $form->setData($key, $value);
        }

        $form->display();
    }

    function getAddressFieldsEnabled()
    {
        return $this->addressFieldsEnabled;
    }

    function setAddressFieldsEnabled($addressFieldsEnabled)
    {
        $this->addressFieldsEnabled = $addressFieldsEnabled;
    }

    function sendWithParams($paramArray)
    {
        $savedHeaders = $this->getHeaders();
        $savedSubject = $this->getSubject();
        $savedBody = $this->getBody();

        $this->assignParams($paramArray);

        $ret = $this->send();

        $this->setHeaders($savedHeaders);
        $this->setSubject($savedSubject);
        $this->setBody($savedBody);

        return $ret;
    }

    function assignParams($paramArray = array())
    {
        $subject = $this->getSubject();
        $body = $this->getBody();

        // Replace variables in message with values
        if ($subject == '[IJME] Password Reset Confirmation' || $subject == '[IJME] Password Reset' ) {
            $bodyText = array();
            foreach ($paramArray as $key => $value) {
                if (!is_object($value)) {
                    $subject = str_replace('{$' . $key . '}', $value, $subject);
                    $text = $this->addProperName($key);
                    $text .= $value;
                    array_push($bodyText, $text);
                }
            }
            $newBody = '';
            foreach ($bodyText as $e) {
                $newBody .= $e . PHP_EOL;
            }
            $this->setSubject($subject);
            $this->setBody($newBody);

        } else {
            foreach ($paramArray as $key => $value) {
                if (!is_object($value)) {
                    $subject = str_replace('{$' . $key . '}', $value, $subject);
                    $body = str_replace('{$' . $key . '}', $value, $body);
                }
            }
            $this->setSubject($subject);
            $this->setBody($body);
        }
    }

    function addProperName($key)
    {
        switch ($key) {
            case "url":
                return "Link : ";
                break;
            case "siteTitle":
                return "";
                break;
            case "journalName":
                return "";
                break;
            case "principalContactSignature":
                return "";
                break;
            case "journalUrl":
                return "";
                break;
            case "userFullName":
                return "User Fullname : ";
                break;
            default :
                return $key . ' : ';

        }
    }

    function send($clearAttachments = true)
    {
        if ($this->attachmentsEnabled) {
            foreach ($this->persistAttachments as $persistentAttachment) {
                $this->addAttachment(
                    $persistentAttachment->getFilePath(),
                    $persistentAttachment->getOriginalFileName(),
                    $persistentAttachment->getFileType()
                );
            }
        }

        $user =& Request::getUser();

        if ($user && $this->bccSender) {
            $this->addBcc($user->getEmail(), $user->getFullName());
        }

        if (isset($this->skip) && $this->skip) {
            $result = true;
        } else {
            $result = parent::send();
        }

        if ($clearAttachments && $this->attachmentsEnabled) {
            $this->_clearAttachments($user->getId());
        }

        return $result;
    }

    function _clearAttachments($userId)
    {
        import('file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();

        $persistAttachments = Request::getUserVar('persistAttachments');
        if (is_array($persistAttachments)) foreach ($persistAttachments as $fileId) {
            $temporaryFile = $temporaryFileManager->getFile($fileId, $userId);
            if (!empty($temporaryFile)) {
                $temporaryFileManager->deleteFile($temporaryFile->getId(), $userId);
            }
        }
    }

    function clearRecipients($clearHeaders = true)
    {
        $this->setData('recipients', null);
        $this->setData('ccs', null);
        $this->setData('bccs', null);
        if ($clearHeaders) {
            $this->setData('headers', null);
        }
    }

    function addPersistAttachment($temporaryFile)
    {
        $this->persistAttachments[] = $temporaryFile;
    }

    function getAttachmentFiles()
    {
        if ($this->attachmentsEnabled) return $this->persistAttachments;
        return array();
    }
}

?>
