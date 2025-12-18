<?php
/**
 * メール送信ヘルパー関数
 */

/**
 * メール送信設定
 * 本番環境では適切なSMTP設定に変更してください
 */
function getEmailConfig() {
    return [
        'from_email' => 'info@narze.xyz',
        'from_name' => '個別支援連絡帳システム きづり',
        'smtp_host' => 'localhost', // 本番環境では適切なSMTPサーバーを設定
        'smtp_port' => 25,
        'smtp_username' => '',
        'smtp_password' => '',
        'use_smtp' => false, // trueにするとSMTPを使用
    ];
}

/**
 * シンプルなメール送信（PHP mail関数使用）
 *
 * @param string $to 送信先メールアドレス
 * @param string $subject 件名
 * @param string $message 本文（HTML対応）
 * @param bool $isHtml HTMLメールかどうか
 * @return bool 送信成功かどうか
 */
function sendEmail($to, $subject, $message, $isHtml = true) {
    $config = getEmailConfig();

    // ヘッダー設定
    $headers = [];
    $headers[] = 'From: ' . mb_encode_mimeheader($config['from_name']) . ' <' . $config['from_email'] . '>';
    $headers[] = 'Reply-To: ' . $config['from_email'];
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    if ($isHtml) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }

    // 件名をエンコード
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

    // メール送信
    try {
        if ($config['use_smtp']) {
            // SMTP送信（PHPMailer等のライブラリ使用を推奨）
            return sendEmailViaSMTP($to, $encodedSubject, $message, $headers, $config);
        } else {
            // PHP標準のmail関数を使用
            return mail($to, $encodedSubject, $message, implode("\r\n", $headers));
        }
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * SMTP経由でメール送信（将来的な拡張用）
 */
function sendEmailViaSMTP($to, $subject, $message, $headers, $config) {
    // TODO: PHPMailerなどのライブラリを使用したSMTP送信を実装
    // 現時点では標準のmail関数にフォールバック
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * チャットメッセージの通知メールを送信
 *
 * @param string $recipientEmail 受信者のメールアドレス
 * @param string $recipientName 受信者の名前
 * @param string $senderName 送信者の名前
 * @param string $studentName 生徒の名前
 * @param string $messagePreview メッセージのプレビュー
 * @param string $chatUrl チャット画面のURL
 * @return bool 送信成功かどうか
 */
function sendChatNotificationEmail($recipientEmail, $recipientName, $senderName, $studentName, $messagePreview, $chatUrl) {
    if (empty($recipientEmail)) {
        return false;
    }

    $subject = "【新着メッセージ】{$studentName}さんのチャットに新しいメッセージがあります";

    // HTMLメール本文
    $message = createChatNotificationEmailBody(
        $recipientName,
        $senderName,
        $studentName,
        $messagePreview,
        $chatUrl
    );

    return sendEmail($recipientEmail, $subject, $message, true);
}

/**
 * チャット通知メールのHTML本文を作成
 */
function createChatNotificationEmailBody($recipientName, $senderName, $studentName, $messagePreview, $chatUrl) {
    // メッセージプレビューを100文字に制限
    $preview = mb_strlen($messagePreview) > 100
        ? mb_substr($messagePreview, 0, 100) . '...'
        : $messagePreview;

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新着チャットメッセージ</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .message-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .message-box .sender {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 8px;
        }
        .message-box .preview {
            color: #555;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 25px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📬 新着メッセージ</h1>
        </div>
        <div class="content">
            <div class="greeting">
                {$recipientName} 様
            </div>
            <p>
                <strong>{$studentName}</strong>さんのチャットに新しいメッセージが届きました。
            </p>
            <div class="message-box">
                <div class="sender">💬 {$senderName}</div>
                <div class="preview">{$preview}</div>
            </div>
            <p style="text-align: center;">
                <a href="{$chatUrl}" class="button">チャットを開く</a>
            </p>
            <p style="font-size: 14px; color: #666; margin-top: 30px;">
                このメッセージは自動送信されています。<br>
                チャット画面から直接返信してください。
            </p>
        </div>
        <div class="footer">
            <p>&copy; 個別支援連絡帳システム きづり</p>
            <p>このメールに返信しないでください</p>
        </div>
    </div>
</body>
</html>
HTML;

    return $html;
}

/**
 * メールアドレスの形式チェック
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * テストメール送信（動作確認用）
 */
function sendTestEmail($to) {
    $subject = "【テスト】個別支援連絡帳システム きづり - メール送信テスト";
    $message = <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✅ メール送信テスト成功</h2>
        </div>
        <p>このメールが届いた場合、メール送信機能が正常に動作しています。</p>
        <p><strong>送信日時:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
HTML;

    return sendEmail($to, $subject, $message, true);
}
?>
