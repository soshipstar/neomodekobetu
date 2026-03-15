<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>モニタリング記録 - {{ $student->student_name ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: ipag, 'IPA Gothic', 'IPAGothic', sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 16pt;
            margin-bottom: 5px;
        }

        .header-meta {
            font-size: 9pt;
            color: #555;
        }

        .header-meta span {
            margin: 0 10px;
        }

        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background: #e8e8e8;
            padding: 4px 10px;
            margin-bottom: 6px;
            border-left: 3px solid #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th, td {
            border: 1px solid #999;
            padding: 4px 6px;
            vertical-align: top;
            text-align: left;
            font-size: 9pt;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }

        .label-cell {
            background: #f9f9f9;
            font-weight: bold;
            width: 100px;
            font-size: 8pt;
        }

        .content-cell {
            word-break: break-word;
        }

        .empty {
            color: #999;
            font-style: italic;
        }

        .overall-content {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 8px;
            line-height: 1.7;
            font-size: 9pt;
            margin-bottom: 10px;
        }

        .achievement-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8pt;
            font-weight: bold;
            color: white;
        }

        .achievement-high { background: #10b981; }
        .achievement-mid { background: #f59e0b; }
        .achievement-low { background: #ef4444; }

        .signature-table {
            width: 100%;
            margin-top: 15px;
            border-top: 1px solid #333;
        }

        .signature-table td {
            border: none;
            padding: 5px 8px;
            vertical-align: middle;
            font-size: 9pt;
        }

        .footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #ccc;
            font-size: 7pt;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>モニタリング記録</h1>
        <div class="header-meta">
            <span>児童氏名: {{ $student->student_name ?? '' }}</span>
            <span>教室: {{ $classroom->classroom_name ?? '' }}</span>
            <span>実施日: {{ $record->monitoring_date ? $record->monitoring_date->format('Y年m月d日') : '' }}</span>
        </div>
    </div>

    {{-- 計画情報 --}}
    @if ($plan)
    <div class="section">
        <div class="section-title">対象計画の情報</div>
        <table>
            <tr>
                <td class="label-cell">計画作成日</td>
                <td class="content-cell">{{ $plan->created_date ? $plan->created_date->format('Y年m月d日') : '（未設定）' }}</td>
                <td class="label-cell">同意日</td>
                <td class="content-cell">{{ $plan->consent_date ? $plan->consent_date->format('Y年m月d日') : '（未設定）' }}</td>
            </tr>
            <tr>
                <td class="label-cell">長期目標</td>
                <td class="content-cell" colspan="3">{{ $plan->long_term_goal ?: '（未設定）' }}</td>
            </tr>
            <tr>
                <td class="label-cell">短期目標</td>
                <td class="content-cell" colspan="3">{{ $plan->short_term_goal ?: '（未設定）' }}</td>
            </tr>
        </table>
    </div>
    @endif

    {{-- 目標達成状況 --}}
    <div class="section">
        <div class="section-title">目標達成状況</div>
        <table>
            <tr>
                <td class="label-cell">短期目標達成度</td>
                <td class="content-cell">{{ $record->short_term_goal_achievement ?? '（未評価）' }}</td>
                <td class="label-cell">長期目標達成度</td>
                <td class="content-cell">{{ $record->long_term_goal_achievement ?? '（未評価）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 総合所見 --}}
    <div class="section">
        <div class="section-title">総合所見</div>
        <div class="overall-content">{!! nl2br(e($record->overall_comment ?: '（未記入）')) !!}</div>
    </div>

    {{-- モニタリング明細 --}}
    @if ($details && $details->count() > 0)
    <div class="section">
        <div class="section-title">領域別評価</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">領域</th>
                    <th style="width: 12%;">達成度</th>
                    <th style="width: 40%;">コメント</th>
                    <th style="width: 33%;">次のアクション</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($details as $detail)
                <tr>
                    <td style="font-weight: bold;">{{ $detail->domain }}</td>
                    <td style="text-align: center;">{{ $detail->achievement_level ?? '未評価' }}</td>
                    <td>{!! nl2br(e($detail->comment ?: '')) !!}</td>
                    <td>{!! nl2br(e($detail->next_action ?: '')) !!}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 対象計画の支援内容との対照 --}}
    @if ($plan && $plan->details && $plan->details->count() > 0)
    <div class="section">
        <div class="section-title">計画の支援内容（参考）</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">領域</th>
                    <th style="width: 25%;">目標</th>
                    <th style="width: 35%;">支援内容</th>
                    <th style="width: 15%;">達成状況</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->details->sortBy('sort_order') as $planDetail)
                <tr>
                    <td>{{ $planDetail->domain }}</td>
                    <td>{!! nl2br(e($planDetail->goal ?: '')) !!}</td>
                    <td>{!! nl2br(e($planDetail->support_content ?: '')) !!}</td>
                    <td style="text-align: center;">{{ $planDetail->achievement_status ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 署名 --}}
    <table class="signature-table">
        <tr>
            <td>
                <strong>記録者：</strong>
                {{ $record->creator->full_name ?? $record->staff_signature ?? '' }}
            </td>
            <td>
                <strong>保護者確認：</strong>
                @if ($record->guardian_confirmed)
                    確認済み（{{ $record->guardian_confirmed_at ? $record->guardian_confirmed_at->format('Y/m/d') : '' }}）
                    {{ $record->guardian_signature ?? '' }}
                @else
                    未確認
                @endif
            </td>
            <td style="text-align: right;">
                <strong>{{ $classroom->classroom_name ?? '' }}</strong>
            </td>
        </tr>
    </table>

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
