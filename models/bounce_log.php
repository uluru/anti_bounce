<?php
/*
                  #   #   #     #   #   ####    #   #
                  #   #   #     #   #   #   #   #   #
                  #   #   #     #   #   ####    #   #
                  #   #   #     #   #   #   #   #   #
                   ###    ####   ###    #   #    ###

             Copyright 2015 ULURU.CO.,LTD. All Rights Reserved.

*/

/**
 * BounceLog Model
 *
 * Leave the log when it fails mailing.
 *
 * @package     app
 * @subpackage  Model
 * @since       2015/12/22
 * @author      ISHIGE Kotoe <k_ishige@uluru.jp>
 * @version     Shufti 13.0.0
 */
class BounceLog extends AppModel
{
    public $name = 'BounceLog';

    public function saveLog($key, $value, $message)
    {
        $this->validate = [
            "{$key}" => [
                'numeric' => [
                    'rule' => 'numeric',
                    'message' => 'キーを指定してください。',
                    'required' => true,
                    'allowEmpty' => false
                ],
            ]
        ];
        $this->create();
        $this->set(
            [
                "{$key}" => $value,
                'message' => $message
            ]
        );
        if (! $this->save()) {
            return false;
        }

        return $this->find(
            'count',
            [
                'conditions' => [
                    "{$key}" => $value
                ],
                'recursive' => -1
            ]
        );
    }
}
