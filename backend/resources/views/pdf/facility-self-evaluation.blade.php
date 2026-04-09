<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>事業所における自己評価結果（別紙３） - {{ $classroom_name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'IPA Gothic', 'IPAGothic', 'Noto Sans JP', sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #333;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .annex-label {
            text-align: right;
            font-size: 9pt;
            margin-bottom: 4px;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: 700;
            letter-spacing: 2pt;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 4px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .info-table td {
            border: 1px solid #999;
            padding: 4px 8px;
            font-size: 8.5pt;
        }

        .info-table .label {
            background: #ecf0f1;
            font-weight: 700;
            width: 20%;
            white-space: nowrap;
        }

        .section-title {
            background: #2c3e50;
            color: #fff;
            padding: 5px 10px;
            font-size: 10pt;
            font-weight: 700;
            margin-top: 12px;
            margin-bottom: 0;
        }

        .strength-table, .weakness-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .strength-table th, .weakness-table th {
            background: #ecf0f1;
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 8pt;
            font-weight: 700;
            text-align: center;
        }

        .strength-table td, .weakness-table td {
            border: 1px solid #999;
            padding: 6px 8px;
            font-size: 8pt;
            vertical-align: top;
        }

        .strength-table td.num, .weakness-table td.num {
            text-align: center;
            width: 4%;
            font-weight: bold;
        }

        .footer {
            margin-top: 10px;
            font-size: 7pt;
            color: #666;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="annex-label">（別紙３）</div>

    <div class="header">
        <h1>事業所における自己評価結果（公表）</h1>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">事業所名</td>
            <td colspan="3">{{ $classroom_name }}</td>
        </tr>
        <tr>
            <td class="label">保護者評価実施期間</td>
            <td>{{ $guardian_period_start ?? '　　年　月　日' }}　～　{{ $guardian_period_end ?? '　　年　月　日' }}</td>
            <td class="label">保護者評価有効回答数</td>
            <td>（対象者数）{{ $guardian_total ?? '-' }}　（回答者数）{{ $guardian_respondents ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">従業者評価実施期間</td>
            <td>{{ $staff_period_start ?? '　　年　月　日' }}　～　{{ $staff_period_end ?? '　　年　月　日' }}</td>
            <td class="label">従業者評価有効回答数</td>
            <td>（対象者数）{{ $staff_total ?? '-' }}　（回答者数）{{ $staff_respondents ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">事業者向け自己評価表作成日</td>
            <td colspan="3">{{ $self_eval_date ?? now()->format('Y年m月d日') }}</td>
        </tr>
    </table>

    <div class="section-title">分析結果</div>

    <table class="strength-table">
        <thead>
            <tr>
                <th colspan="4" style="background: #27ae60; color: #fff;">事業所の強み（※）だと思われること　※より強化・充実を図ることが期待されること</th>
            </tr>
            <tr>
                <th style="width: 4%"></th>
                <th style="width: 32%">工夫していることや意識的に行っている取組等</th>
                <th style="width: 32%">さらに充実を図るための取組等</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < 3; $i++)
                @php
                    $item = $strengths[$i] ?? null;
                @endphp
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $item['current_status'] ?? '' }}</td>
                    <td>{{ $item['improvement_plan'] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <table class="weakness-table">
        <thead>
            <tr>
                <th colspan="4" style="background: #e74c3c; color: #fff;">事業所の弱み（※）だと思われること　※事業所の課題や改善が必要だと思われること</th>
            </tr>
            <tr>
                <th style="width: 4%"></th>
                <th style="width: 32%">事業所として考えている課題の要因等</th>
                <th style="width: 32%">改善に向けて必要な取組や工夫が必要な点等</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < 3; $i++)
                @php
                    $item = $weaknesses[$i] ?? null;
                @endphp
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $item['issues'] ?? '' }}</td>
                    <td>{{ $item['improvement_plan'] ?? '' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer">
        {{ $classroom_name }} — {{ now()->format('Y/m/d H:i') }} 出力
    </div>
</body>
</html>
