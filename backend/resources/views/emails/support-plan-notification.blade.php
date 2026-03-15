<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個別支援計画のお知らせ</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', 'Meiryo', sans-serif; line-height: 1.6; color: #333333; background-color: #f0f2f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0f2f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%); padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 600; color: #ffffff; letter-spacing: 0.5px;">
                                {{ $facilityName ?? 'きづり' }}
                            </h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.85);">
                                個別支援計画のお知らせ
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px 30px;">
                            <p style="font-size: 16px; margin: 0 0 20px 0;">
                                {{ $recipientName }} 様
                            </p>

                            {{-- Plan type indicator --}}
                            @php
                                $actionLabels = [
                                    'review' => '確認依頼',
                                    'confirmation' => '承認依頼',
                                    'updated' => '更新通知',
                                    'created' => '新規作成',
                                ];
                                $actionLabel = $actionLabels[$actionType] ?? $actionType;

                                $badgeColors = [
                                    'review' => '#ff9800',
                                    'confirmation' => '#f44336',
                                    'updated' => '#2196f3',
                                    'created' => '#4caf50',
                                ];
                                $badgeColor = $badgeColors[$actionType] ?? '#667eea';
                            @endphp

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <span style="display: inline-block; background-color: {{ $badgeColor }}; color: #ffffff; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-bottom: 12px;">
                                                        {{ $actionLabel }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top: 12px;">
                                                    <p style="margin: 0 0 8px 0; font-size: 14px; color: #888888;">対象児童</p>
                                                    <p style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #333333;">{{ $studentName }}</p>

                                                    <p style="margin: 0 0 8px 0; font-size: 14px; color: #888888;">計画期間</p>
                                                    <p style="margin: 0; font-size: 16px; color: #333333;">{{ $planPeriod }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 15px; margin: 24px 0; color: #444444;">
                                @if($actionType === 'review')
                                    上記の個別支援計画の内容をご確認ください。
                                @elseif($actionType === 'confirmation')
                                    上記の個別支援計画の承認をお願いいたします。
                                @elseif($actionType === 'updated')
                                    上記の個別支援計画が更新されました。内容をご確認ください。
                                @else
                                    上記の個別支援計画が作成されました。内容をご確認ください。
                                @endif
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 8px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $planUrl }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 25px; font-weight: 600; font-size: 15px; letter-spacing: 0.5px;">
                                            支援計画を確認する
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
