# anti_bounce

「SES - SNS - アプリケーション」の連携でバウンス情報を取得し、アプリケーション側で対象のメールアドレスに対して処理を行うためのプラグインになります。

注意) アプリケーションサーバにIP制限などが掛かっていると、SNSから通知が来ませんので、SNSの通信制限を開放してやる必要があります。

SES, SNS のゾーンは US-EAST-1 (バージニア北部)

IP一覧: https://forums.aws.amazon.com/ann.jspa?annID=2347

## 1. プログラム側の準備

### 1-1. bounce_logs テーブルを用意する

バウンス履歴を保存するテーブルを作成してください。
（Model の作成は必要ありません）

### 1-2. composer.json で本プラグインと aws-php-sns-message-validator をインストール

    "repositories": [{
      "type": "package",
      "package": {
          "name": "plugins/anti_bounce",
          "version": "1.0",
          "type": "cakephp-plugin",
          "require": {
              "composer/installers": "*"
          },
          "source": {
              "url": "git@github.com:uluru/anti_bounce.git",
              "type": "git",
              "reference": "master"
          }
      }
    }]


    "extra": {
        "installer-paths": {
            "app/plugins/anti_bounce": ["plugins/anti_bounce"]
        }
    }


    "require": {
        "plugins/anti_bounce": "1.0",
        "aws/aws-php-sns-message-validator": "1.1"
    }


### 1-3. 設定ファイルの更新

**routes.php**

    Router::connect(
        '/anti_bounce/receive',
        array(
            'plugin' => 'AntiBounce',
            'controller' => 'AntiBounce',
            'action' => 'receive'
        )
    );

**bootstrap.php**

    Configure::write(
        'AntiBounce',
        array(
            'topic' => '*****',
            'mail' => '*****',
            'settings' => array(
                'stopSending' => false, // true = stop mail sending, false = just write log
                'email' => array(
                    'model' => 'User',
                    'key' => 'id',
                    'mailField' => 'email',
                ), // メールアドレスのレコードを指定する
                'updateFields' => array(
                    array(
                        'model' => 'User',
                        'key' => 'id',
                        'fields' => array(
                            'inform' => 0,
                            'action_mail' => 0,
                            'activity_report_mail' => 0,
                            'receive_magazine' => 0,
                            'favorite_client_inform' => 0,
                            'bookmark_end_mail' => 0,
                            'bookmark_few_mail' => 0
                        )
                    )
                ) // stopSending = true の場合に更新するレコードの設定
            )
        )
    );


## 2. SESにメールアドレスを登録

「Verify a New Email Address」→「Verify This Email Address」→送信されてきたメールアドレスのリンク押下し、メールアドレスを有効化

**有効化したメールアドレスを bootstrap.php の mail 項目へ記載する。**

## 3. SESコンソールでSNSトピックを作成

1. 「Email Addresses」→ 2で登録したメールアドレスをクリック
2. 「Notifications」タブの「Edit Notification Configuration」をクリック
3. 「Edit Notification Configuration」をクリック
4. 「Topic Name」「Display Name」を適宜入力
5. 「Create Topic」すると Bounce 項目のプルダウンに作成したTopicが表示されるのでそれを選択します。


## 4. SNSのTopicを有効化

3で作成したSNSトピックは、SNSのバージニア北部（ゾーン: US-EAST）に作成されています。

**Topic ARN 項目を bootstrap.php の topic 項目へ記載する。**


ARNリンクをクリック→「Create Subscription」をクリック

通信プロトコル : https

Endpoint : https://\*\*\*\*\*.\*\*\*\*/anti_bounce/receive

とする。
数秒後コンソールをリロードすると

    Subscription ID = PendingConfirmation
    ↓
    Subscription ID = arn:aws:sns:us-east-1:\*\*\*\*

となり Endpoint への通信確認が取れます。


## 5. bounceメールをテスト送信

これで SES - SNS - アプリケーション 連携の準備が整ったので、SESにてbounceメールのテスト送信を行います。

テストメール送信方法
* http://docs.aws.amazon.com/ja_jp/ses/latest/DeveloperGuide/getting-started-send-from-console.html
* https://docs.aws.amazon.com/ja_jp/ses/latest/DeveloperGuide/mailbox-simulator.html

そうすると、4で設定したEndpoint宛にバウンスしたメール情報が通知されるようになります。

以降はアプリケーション側から送信されるメールアドレス（バウンスしたメールアドレス）に紐付くレコードがあった場合 bootstrap.php で設定したモデルが更新されます。
