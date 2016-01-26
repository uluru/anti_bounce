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
    public $uses = ['AntiBounce.BounceLog'];
    private $config;

    const TYPE_SUBSCRIPTION = 'SubscriptionConfirmation';
    const TYPE_NOTIFICATION = 'Notification';
    const NUM_BOUNCE_LIMIT = 3;

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

        $this->config = Configure::read('AntiBounce');

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
            // Insert bounce log
            $this->execute($detail['bounce']['bouncedRecipients'][0]['emailAddress'], $message['Message']);
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
        if ($this->isSubscription) {
            return $topic == $this->config['topic'];
        } else {
            return $topic == $this->config['topic'] && $email == $this->config['mail'];
        }
    }

    /**
     * Execute update
     *
     * @param string $targetEmail
     * @param string $message
     * @return void
     */
    private function execute($targetEmail, $message)
    {
        $settings = $this->config['settings'];
        $this->{$settings['email']['model']} = ClassRegistry::init($settings['email']['model']);

        $keyValue = $this->getPrimaryValueByEmail($targetEmail);

        try {
            $this->BounceLog->begin();

            $count = $this->BounceLog->saveLog(
                strtolower($settings['email']['model']) . '_id',
                $keyValue,
                $message
            );

            if ($count == false) {
                throw new Exception('Error: Failed insertLog(). %s = %d');
            }

            // Update mail sending setting
            if ($settings['stopSending'] && $count > self::NUM_BOUNCE_LIMIT) {
                foreach ($settings['updateFields'] as $updateField) {
                    $update = $this->{$updateField['model']}->updateAll(
                        $updateField['fields'],
                        ["{$updateField['key']}" => $keyValue]
                    );
                    if (! $update) {
                        throw new Exception('Error: Failed stop mail(). %s = %d');
                    }
                }
            }

            $this->BounceLog->commit();
        } catch (Exception $e) {
            $this->BounceLog->rollback();
            $this->log(
                sprintf(
                    $e->getMessage(),
                    strtolower($settings['email']['model']) . '_id',
                    $keyValue
                )
            );
        }
    }

    /**
     * Get primary key by email address for update key.
     *
     * @param string $email
     * @return integer
     */
    private function getPrimaryValueByEmail($email)
    {
        $settings = $this->config['settings']['email'];
        $result = $this->{$settings['model']}->find(
            'first',
            [
                'recursive' => -1,
                'fields' => $settings['key'],
                'conditions' => [
                    "{$settings['mailField']}" => $email
                ]
            ]
        );
        return $result[$settings['model']][$settings['key']];
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
