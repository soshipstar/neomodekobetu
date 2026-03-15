<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>かけはし - {{ $student->student_name ?? '' }}</title>
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

        .header-subtitle {
            font-size: 9pt;
            color: #555;
        }

        /* メタ情報 */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .meta-table td {
            padding: 3px 8px;
            font-size: 9pt;
            border: none;
        }

        .meta-label {
            font-weight: bold;
        }

        .status-submitted {
            color: #10b981;
            font-weight: bold;
        }

        .status-draft {
            color: #f59e0b;
            font-weight: bold;
        }

        /* セクション */
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 6px;
        }

        .section-content {
            padding: 6px 10px;
            border: 1px solid #ccc;
            min-height: 30px;
            background: #f9f9f9;
        }

        .empty-text {
            color: #999;
            font-style: italic;
        }

        /* 2列比較テーブル */
        .compare-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .compare-table th,
        .compare-table td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 9pt;
        }

        .compare-table th {
            background: #e2e8f0;
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
            margin-bottom: 6px;
        }

        .domain-table th,
        .domain-table td {
            border: 1px solid #ccc;
            padding: 4px 8px;
            vertical-align: top;
            font-size: 9pt;
        }

        .domain-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: left;
            width: 18%;
        }

        .domain-table td {
            width: 41%;
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
            font-size: 8pt;
            padding: 3px;
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
            margin-top: 15px;
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
        <h1>かけはし（スタッフ・保護者 統合版）</h1>
        <div class="header-subtitle">個別支援計画用 かけはし記録</div>
    </div>

    {{-- メタ情報 --}}
    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">児童氏名：</span>
                {{ $student->student_name ?? '' }}
            </td>
            <td>
                <span class="meta-label">教室：</span>
                {{ $classroom->classroom_name ?? '' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="meta-label">対象期間：</span>
                {{ $period->start_date ? $period->start_date->format('Y年m月d日') : '' }}
                ～
                {{ $period->end_date ? $period->end_date->format('Y年m月d日') : '' }}
            </td>
            <td>
                <span class="meta-label">提出期限：</span>
                {{ $period->submission_deadline ? $period->submission_deadline->format('Y年m月d日') : '' }}
            </td>
        </tr>
    </table>

    @php
        $staffEntry = $staffEntries->first();
        $guardianEntry = $guardianEntries->first();
    @endphp

    {{-- 提出状態 --}}
    <table class="meta-table" style="margin-bottom: 12px;">
        <tr>
            <td>
                <span class="meta-label">スタッフ：</span>
                @if ($staffEntry && $staffEntry->is_submitted)
                    <span class="status-submitted">提出済み</span>
                @else
                    <span class="status-draft">下書き</span>
                @endif
            </td>
            <td>
                <span class="meta-label">保護者：</span>
                @if ($guardianEntry && $guardianEntry->is_submitted)
                    <span class="status-submitted">提出済み</span>
                @else
                    <span class="status-draft">未提出</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- 本人の願い --}}
    <div class="section">
        <div class="section-title">本人の願い</div>
        <table class="compare-table">
            <tr>
                <th class="guardian-header">保護者</th>
                <th class="staff-header">スタッフ</th>
            </tr>
            <tr>
                <td>{{ $guardianEntry->home_situation ?? '（未入力）' }}</td>
                <td>{{ $staffEntry->student_wish ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 目標 --}}
    <div class="section">
        <div class="section-title">短期目標（6か月）</div>
        <table class="compare-table">
            <tr>
                <th class="guardian-header">保護者</th>
                <th class="staff-header">スタッフ</th>
            </tr>
            <tr>
                <td>{{ $guardianEntry->concerns ?? '（未入力）' }}</td>
                <td>{{ $staffEntry->short_term_goal ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">長期目標（1年以上）</div>
        <table class="compare-table">
            <tr>
                <th class="guardian-header">保護者</th>
                <th class="staff-header">スタッフ</th>
            </tr>
            <tr>
                <td>{{ $guardianEntry->requests ?? '（未入力）' }}</td>
                <td>{{ $staffEntry->long_term_goal ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 五領域の課題 --}}
    <div class="section">
        <div class="section-title">五領域の課題</div>
        <table class="domain-table">
            <tr>
                <th></th>
                <td class="col-header col-header-guardian">保護者</td>
                <td class="col-header col-header-staff">スタッフ</td>
            </tr>
            <tr>
                <th>健康・生活</th>
                <td class="guardian-col">{{ $guardianEntry->home_situation ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry->health_life ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>運動・感覚</th>
                <td class="guardian-col">{{ $guardianEntry->concerns ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry->motor_sensory ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>認知・行動</th>
                <td class="guardian-col">{{ $guardianEntry->requests ?? '（未入力）' }}</td>
                <td class="staff-col">{{ $staffEntry->cognitive_behavior ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>言語・コミュニケーション</th>
                <td class="guardian-col">-</td>
                <td class="staff-col">{{ $staffEntry->language_communication ?? '（未入力）' }}</td>
            </tr>
            <tr>
                <th>人間関係・社会性</th>
                <td class="guardian-col">-</td>
                <td class="staff-col">{{ $staffEntry->social_relations ?? '（未入力）' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
