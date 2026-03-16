<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>かけはし - {{ $student->student_name ?? '' }}</title>
    <style>
        
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'ipag', 'IPA Gothic', 'IPAGothic', 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            line-height: 1.35;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 6px;
            border-bottom: 2px solid #333;
            padding-bottom: 4px;
        }

        .header h1 {
            font-size: 14pt;
            margin-bottom: 2px;
        }

        .header-subtitle {
            font-size: 8pt;
            color: #555;
        }

        .meta-row {
            display: table;
            width: 100%;
            margin-bottom: 4px;
            font-size: 8pt;
        }

        .meta-cell {
            display: table-cell;
            width: 50%;
            padding: 1px 4px;
        }

        .meta-label {
            font-weight: bold;
        }

        .status-submitted { color: #10b981; font-weight: bold; }
        .status-draft { color: #f59e0b; font-weight: bold; }

        /* セクション */
        .section {
            margin-bottom: 5px;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 2px 8px;
            font-weight: bold;
            font-size: 8pt;
            margin-bottom: 0;
        }

        /* 2列比較テーブル */
        .compare-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .compare-table th,
        .compare-table td {
            border: 1px solid #bbb;
            padding: 2px 5px;
            vertical-align: top;
            font-size: 7.5pt;
        }

        .compare-table th {
            font-weight: bold;
            text-align: center;
            width: 50%;
        }

        .compare-table th.guardian-header {
            background: #fce4ec;
            color: #880e4f;
        }

        .compare-table th.staff-header {
            background: #e8f5e9;
            color: #1b5e20;
        }

        /* 五領域テーブル */
        .domain-table {
            width: 100%;
            border-collapse: collapse;
        }

        .domain-table th,
        .domain-table td {
            border: 1px solid #bbb;
            padding: 2px 5px;
            vertical-align: top;
            font-size: 7.5pt;
        }

        .domain-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
            width: 16%;
        }

        .domain-table td {
            width: 42%;
        }

        .domain-table td.guardian-col {
            background: #fef7f7;
        }

        .domain-table td.staff-col {
            background: #f7fef7;
        }

        .col-header {
            text-align: center;
            font-weight: bold;
            font-size: 7pt;
            padding: 2px;
        }

        .col-header-guardian {
            background: #fce4ec;
            color: #880e4f;
        }

        .col-header-staff {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .footer {
            margin-top: 6px;
            padding-top: 3px;
            border-top: 1px solid #ccc;
            font-size: 6pt;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>かけはし</h1>
        <div class="header-subtitle">個別支援計画用 スタッフ・保護者 統合記録</div>
    </div>

    {{-- メタ情報 --}}
    <div class="meta-row">
        <div class="meta-cell">
            <span class="meta-label">児童氏名：</span>{{ $student->student_name ?? '' }}
        </div>
        <div class="meta-cell">
            <span class="meta-label">教室：</span>{{ $classroom->classroom_name ?? '' }}
        </div>
    </div>
    <div class="meta-row">
        <div class="meta-cell">
            <span class="meta-label">対象期間：</span>
            {{ $period->start_date ? $period->start_date->format('Y/m/d') : '' }}
            ～
            {{ $period->end_date ? $period->end_date->format('Y/m/d') : '' }}
        </div>
        <div class="meta-cell">
            <span class="meta-label">提出期限：</span>
            {{ $period->submission_deadline ? $period->submission_deadline->format('Y/m/d') : '' }}
        </div>
    </div>

    @php
        $staffEntry = $staffEntries->first();
        $guardianEntry = $guardianEntries->first();
    @endphp

    <div class="meta-row" style="margin-bottom: 6px;">
        <div class="meta-cell">
            <span class="meta-label">スタッフ：</span>
            @if ($staffEntry && $staffEntry->is_submitted)
                <span class="status-submitted">提出済み</span>
            @else
                <span class="status-draft">{{ $staffEntry ? '下書き' : '未入力' }}</span>
            @endif
        </div>
        <div class="meta-cell">
            <span class="meta-label">保護者：</span>
            @if ($guardianEntry && $guardianEntry->is_submitted)
                <span class="status-submitted">提出済み</span>
            @else
                <span class="status-draft">未提出</span>
            @endif
        </div>
    </div>

    {{-- 本人の願い --}}
    <div class="section">
        <div class="section-title">本人の願い</div>
        <table class="compare-table">
            <tr>
                <th class="guardian-header">保護者</th>
                <th class="staff-header">スタッフ</th>
            </tr>
            <tr>
                <td>{{ $guardianEntry?->home_situation ?? '（未入力）' }}</td>
                <td>{{ $staffEntry?->student_wish ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 短期目標 --}}
    <div class="section">
        <div class="section-title">短期目標（6か月）</div>
        <table class="compare-table">
            <tr>
                <th class="guardian-header">保護者</th>
                <th class="staff-header">スタッフ</th>
            </tr>
            <tr>
                <td>{{ $guardianEntry?->concerns ?? '（未入力）' }}</td>
                <td>{{ $staffEntry?->short_term_goal ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 長期目標 --}}
    <div class="section">
        <div class="section-title">長期目標（1年以上）</div>
        <table class="compare-table">
            <tr>
                <th class="guardian-header">保護者</th>
                <th class="staff-header">スタッフ</th>
            </tr>
            <tr>
                <td>{{ $guardianEntry?->requests ?? '（未入力）' }}</td>
                <td>{{ $staffEntry?->long_term_goal ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 五領域 --}}
    <div class="section">
        <div class="section-title">五領域</div>
        <table class="domain-table">
            <tr>
                <th></th>
                <td class="col-header col-header-guardian">保護者</td>
                <td class="col-header col-header-staff">スタッフ</td>
            </tr>
            <tr>
                <th>健康・生活</th>
                <td class="guardian-col">{{ $guardianEntry?->domain_health_life ?? $guardianEntry?->home_situation ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry?->health_life ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>運動・感覚</th>
                <td class="guardian-col">{{ $guardianEntry?->domain_motor_sensory ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry?->motor_sensory ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>認知・行動</th>
                <td class="guardian-col">{{ $guardianEntry?->domain_cognitive_behavior ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry?->cognitive_behavior ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>言語・コミュニケーション</th>
                <td class="guardian-col">{{ $guardianEntry?->domain_language_communication ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry?->language_communication ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>人間関係・社会性</th>
                <td class="guardian-col">{{ $guardianEntry?->domain_social_relations ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry?->social_relations ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        出力日時: {{ now()->format('Y/m/d H:i') }} | {{ $period->period_name ?? '' }}
    </div>
</body>
</html>
