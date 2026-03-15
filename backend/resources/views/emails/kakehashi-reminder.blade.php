<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>かけはし提出期限のお知らせ</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Meiryo', sans-serif; line-height: 1.6; color: #333333; background-color: #f0f2f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0f2f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 600; color: #ffffff; letter-spacing: 0.5px;">
                                {{ $facilityName ?? 'きづり' }}
                            </h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.85);">
                                かけはし提出期限のお知らせ
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px 30px;">
                            <p style="font-size: 16px; margin: 0 0 20px 0;">
                                {{ $recipientName }} 様
                            </p>

                            <p style="font-size: 15px; margin: 0 0 24px 0; color: #444444;">
                                <strong>{{ $studentName }}</strong> さんのかけはしの提出期限が近づいています。
                            </p>

                            {{-- Deadline display --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 0 0 24px 0;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="background-color: #fff3e0; border: 2px solid #ff9800; border-radius: 12px; padding: 0;">
                                            <tr>
                                                <td style="padding: 20px 32px; text-align: center;">
                                                    <p style="margin: 0 0 4px 0; font-size: 13px; color: #e65100; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                                        提出期限
                                                    </p>
                                                    <p style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700; color: #e65100;">
                                                        {{ $deadline }}
                                                    </p>
                                                    @if($daysRemaining <= 0)
                                                        <span style="display: inline-block; background-color: #f44336; color: #ffffff; padding: 4px 16px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                                            期限を過ぎています
                                                        </span>
                                                    @elseif($daysRemaining === 1)
                                                        <span style="display: inline-block; background-color: #f44336; color: #ffffff; padding: 4px 16px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                                            明日が期限です
                                                        </span>
                                                    @else
                                                        <span style="display: inline-block; background-color: #ff9800; color: #ffffff; padding: 4px 16px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                                            あと {{ $daysRemaining }} 日
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $kakehashiUrl }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 25px; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;">
                                            かけはしを記入する
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 13px; color: #888888; margin: 28px 0 0 0; text-align: center;">
                                このメッセージは自動送信されています。
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 4px 0; font-size: 13px; color: #666666;">
                                &copy; {{ date('Y') }} {{ $facilityName ?? 'きづり' }} - 個別支援連絡帳システム
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                このメールに直接返信することはできません
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
