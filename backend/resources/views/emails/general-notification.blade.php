<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Meiryo', sans-serif; line-height: 1.6; color: #333333; background-color: #f0f2f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0f2f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 600; color: #ffffff; letter-spacing: 0.5px;">
                                {{ $facilityName ?? 'きづり' }}
                            </h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px 30px;">
                            <p style="font-size: 16px; margin: 0 0 20px 0;">
                                {{ $recipientName }} 様
                            </p>

                            <h2 style="font-size: 18px; margin: 0 0 16px 0; color: #333333; font-weight: 600;">
                                {{ $title }}
                            </h2>

                            <div style="font-size: 15px; color: #444444; line-height: 1.8;">
                                {!! nl2br(e($body)) !!}
                            </div>

                            {{-- Optional CTA Button --}}
                            @if(!empty($actionUrl) && !empty($actionLabel))
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 28px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $actionUrl }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 25px; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;">
                                                {{ $actionLabel }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

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
