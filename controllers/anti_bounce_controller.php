<?php
/*
                  #   #   #     #   #   ####    #   #
                  #   #   #     #   #   #   #   #   #
                  #   #   #     #   #   ####    #   #
                  #   #   #     #   #   #   #   #   #
                   ###    ####   ###    #   #    ###

             Copyright 2013 ULURU.CO.,LTD. All Rights Reserved.

*/

/**
 * AntiBounce Controller
 *
 * @package     app
 * @subpackage  Controller
 * @since       2015/08/12
 * @author      t_maehira@uluru.jp
 * @version     Shufti 12.0.0
 */

require VENDORS . 'autoload.php';
use Aws\Sns\Message as Message;
use Aws\Sns\MessageValidator as MessageValidator;
use Aws\Sns\Exception\InvalidSnsMessageException as InvalidSnsMessageException;

class AntiBounceController extends AntiBounceAppController
{
    public $name = 'AntiBounce';

    const TYPE_SUBSCRIPTION = 'SubscriptionConfirmation';
    const TYPE_NOTIFICATION = 'Notification';

    public function beforeFilter()
    {
        $this->Auth->allow('*');
    }

    public function __construct($request = null, $response = null)
    {
        parent::__construct($request, $response);
        $this->modelClass = null;
        $this->isSubscription = false;
    }

    /**
     * Update record from received bounce mail.
     */
    public function receive()
    {
        $message = Message::fromRawPostData();

        // Get from SNS notifiate.
        if (! $message) {
            $this->log('Error: Failed checkNotificateFromSns().');
            exit;
        }

        // Check from SNS notificate.
        if (! $this->checkNotificateFromSns($message)) {
            $this->log('Error: Failed checkNotificateFromSns().');
            exit;
        }

        // Check SNS TopicArn. (TopicArn is unique in AWS)
        if ($message['Type'] == self::TYPE_SUBSCRIPTION) {
            $topic = $message['TopicArn'];
            $this->isSubscription = $email = true;
        } elseif ($message['Type'] == self::TYPE_NOTIFICATION) {
            $topic = $message['TopicArn'];
            $detail = json_decode($message['Message'], true);
            $email = $detail['mail']['source'];
            $this->isSubscription = false;
        } else {
            $this->log('Error: Failed approved message type.');
            exit;
        }
        if (! $this->checkSnsTopic($topic, $email)) {
            $this->log('Error: Failed checkSnsTopic().');
            exit;
        }

        if ($this->isSubscription) {
            // enable end point
            $this->SubscriptionEndPoint($message);
        } else {
            // Update records.
            $this->insertLog($detail['bounce']['bouncedRecipients'][0]['emailAddress']);
        }
    }

    /**
     * Check notificate from sns.
     *
     * @param object $message
     * @return boolean
     */
    private function checkNotificateFromSns($message)
    {
        $messageValidator = new MessageValidator();
        return $messageValidator->isValid($message);
    }

    /**
     * Check receive notificate and config file.
     *
     * @param string $topic
     * @param string $email
     * @return array
     */
    private function checkSnsTopic($topic, $email)
    {
        $settings = Configure::read('AntiBounce');

        if ($this->isSubscription) {
            return $topic == $settings['topic'];
        } else {
            return $topic == $settings['topic'] && $email == $settings['mail'];
        }
    }

    /**
     * Insert bounce log.
     *
     * @param string $targetEmail
     * @return array
     */
    private function insertLog($targetEmail)
    {
        $saveData = array();
        extract(Configure::read('AntiBounce.data'));

        $primaryId = $this->getPrimaryValueByEmail(
            $model,
            $primaryKey,
            $mailField,
            $targetEmail
        );

        $logModel = ClassRegistry::init($log['model']);
        $logModel->create();
        $logModel->set(
            array(
                "{$log['key']}" => $primaryId
            )
        );
        if (! $logModel->save()) {
            $this->log('Error: Failed insertLog().');
            $this->log($logModel->validationErrors);
        }
    }

    /**
     * Get primary key by email address for update key.
     *
     * @param string $model
     * @param intger $primaryKey
     * @param string $mailField
     * @param string $email
     * @return integer
     */
    private function getPrimaryValueByEmail($model, $primaryKey, $mailField, $email)
    {
        $result = ClassRegistry::init($model)->find(
            'first',
            array(
                'recursive' => -1,
                'fields' => $primaryKey,
                'conditions' => array(
                    $mailField => $email
                )
            )
        );
        return $result[$model][$primaryKey];
    }

    /**
     * Notify end point.
     */
    private function SubscriptionEndPoint($message)
    {
        $conn = curl_init();
        $url = $message['SubscribeURL'];

        curl_setopt($conn, CURLOPT_URL, $url);
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, true);

        $response = curl_exec($conn);

        curl_close($conn);
    }
}

/* vim: set et ts=4 sts=4 sw=4 fenc=utf-8 ff=unix : */
