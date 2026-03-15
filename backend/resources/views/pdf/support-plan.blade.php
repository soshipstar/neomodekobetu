<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>個別支援計画書 - {{ $student->student_name ?? $plan->student_name }}</title>
    <style>
        @font-face { font-family: "ipag"; src: url("file:///var/www/html/storage/fonts/ipag.ttf"); font-weight: normal; font-style: normal; }
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: ipag, 'IPA Gothic', 'IPAGothic', sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #333;
        }

        /* ヘッダー */
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 16pt;
            margin: 0;
        }

        /* メタ情報テーブル */
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .meta-table td {
            padding: 2px 5px;
            font-size: 10pt;
            border: none;
        }

        .meta-label {
            font-weight: bold;
        }

        /* セクションタイトル */
        .section-title {
            background: #4a5568;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 5px;
        }

        .section-content {
            padding: 5px 8px;
            border: 1px solid #999;
            min-height: 40px;
            margin-bottom: 10px;
        }

        /* 2列レイアウト用テーブル */
        .two-col-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .two-col-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        .two-col-table td:first-child {
            padding-right: 5px;
        }

        .two-col-table td:last-child {
            padding-left: 5px;
        }

        /* 目標セクション */
        .goal-date {
            font-weight: bold;
            margin-bottom: 3px;
            font-size: 9pt;
        }

        /* 支援内容明細テーブル */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #333;
            padding: 3px 5px;
            text-align: left;
            vertical-align: top;
        }

        .details-table th {
            background: #e2e8f0;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
        }

        .details-table td {
            line-height: 1.4;
        }

        .category-self {
            background: #f7fafc;
        }

        .category-family {
            background: #ebf8ff;
        }

        .category-community {
            background: #f0fff4;
        }

        /* 署名フッター */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border-top: 1px solid #333;
        }

        .signature-table td {
            padding: 5px 8px;
            vertical-align: middle;
            border: none;
        }

        .signature-label {
            font-weight: bold;
            font-size: 9pt;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            min-width: 120px;
            padding: 3px 5px;
            font-size: 9pt;
        }

        .issuer-name {
            font-size: 10pt;
            font-weight: bold;
        }

        .issuer-details {
            font-size: 8pt;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>個別支援計画書</h1>
    </div>

    {{-- メタ情報 --}}
    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">児童氏名：</span>
                {{ $student->student_name ?? $plan->student_name }}
            </td>
            <td style="text-align: right;">
                <span class="meta-label">同意日：</span>
                {{ $plan->consent_date ? $plan->consent_date->format('Y年m月d日') : '' }}
            </td>
        </tr>
    </table>

    {{-- 意向と方針（2列） --}}
    <table class="two-col-table">
        <tr>
            <td>
                <div class="section-title">利用児及び家族の生活に対する意向</div>
                <div class="section-content">{{ $plan->life_intention }}</div>
            </td>
            <td>
                <div class="section-title">総合的な支援の方針</div>
                <div class="section-content">{{ $plan->overall_policy }}</div>
            </td>
        </tr>
    </table>

    {{-- 長期目標と短期目標（2列） --}}
    <table class="two-col-table">
        <tr>
            <td>
                <div class="section-title">長期目標</div>
                <div class="section-content">{{ $plan->long_term_goal }}</div>
            </td>
            <td>
                <div class="section-title">短期目標</div>
                <div class="section-content">{{ $plan->short_term_goal }}</div>
            </td>
        </tr>
    </table>

    {{-- 支援内容明細 --}}
    <div class="section-title">支援内容</div>
    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 12%;">領域</th>
                <th style="width: 20%;">現状</th>
                <th style="width: 20%;">目標</th>
                <th style="width: 30%;">支援内容</th>
                <th style="width: 10%;">達成状況</th>
                <th style="width: 8%;">順序</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($details as $detail)
            <tr>
                <td>{{ $detail->domain }}</td>
                <td>{!! nl2br(e($detail->current_status)) !!}</td>
                <td>{!! nl2br(e($detail->goal)) !!}</td>
                <td>{!! nl2br(e($detail->support_content)) !!}</td>
                <td style="text-align: center;">{{ $detail->achievement_status }}</td>
                <td style="text-align: center;">{{ $detail->sort_order }}</td>
            </tr>
            @endforeach
            @if ($details->isEmpty())
            <tr>
                <td colspan="6" style="text-align: center; color: #999;">支援内容が登録されていません</td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- 署名欄フッター --}}
    <table class="signature-table">
        <tr>
            <td>
                <span class="signature-label">児童発達支援管理責任者：</span>
                <span class="signature-line">{{ $plan->staff_signature ?? '' }}</span>
            </td>
            <td>
                <span class="signature-label">保護者署名：</span>
                <span class="signature-line">{{ $plan->guardian_signature ?? '' }}</span>
            </td>
            <td style="text-align: right;">
                <div class="issuer-name">{{ $classroom->classroom_name ?? '' }}</div>
                <div class="issuer-details">
                    @if ($classroom && $classroom->address)
                        〒{{ $classroom->address }}
                    @endif
                    @if ($classroom && $classroom->phone)
                        <br>TEL: {{ $classroom->phone }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- フッター --}}
    <div style="text-align: center; margin-top: 10px; font-size: 7pt; color: #999;">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
