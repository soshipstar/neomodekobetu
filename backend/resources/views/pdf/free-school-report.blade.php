<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>フリースクール活動報告書 - {{ $student->student_name }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'IPA Gothic', 'IPAGothic', 'Hiragino Kaku Gothic Pro', 'Noto Sans JP', sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #222;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .doc-header {
            text-align: center;
            border-bottom: 3px double #234;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .doc-title {
            font-size: 16pt;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 2pt;
        }
        .doc-issuer {
            font-size: 9pt;
            color: #666;
            margin-top: 4px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta td {
            padding: 6px 8px;
            font-size: 9.5pt;
            border: 1px solid #bbb;
        }
        .meta .lbl {
            background: #f4f6f8;
            font-weight: 600;
            color: #444;
            width: 22%;
            white-space: nowrap;
        }
        section.body-section {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        section.body-section h2 {
            font-size: 11.5pt;
            color: #fff;
            background: #4a76a8;
            padding: 4px 10px;
            margin-bottom: 6px;
            border-radius: 2px;
        }
        section.body-section .content {
            white-space: pre-wrap;
            word-wrap: break-word;
            padding: 4px 4px 4px 6px;
            border-left: 3px solid #4a76a8;
            min-height: 28pt;
        }
        .footer {
            position: fixed;
            bottom: 6mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="doc-title">フリースクール活動報告書</div>
        <div class="doc-issuer">発行: {{ $classroom->classroom_name ?? '' }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="lbl">児童名</td>
            <td>{{ $student->student_name }}</td>
            <td class="lbl">活動日</td>
            <td>{{ optional($report->report_date)->format('Y年n月j日') }}</td>
        </tr>
        @if(!empty($report->title))
        <tr>
            <td class="lbl">表題</td>
            <td colspan="3">{{ $report->title }}</td>
        </tr>
        @endif
    </table>

    <section class="body-section">
        <h2>1. 活動概要</h2>
        <div class="content">{{ $report->activity_summary }}</div>
    </section>

    <section class="body-section">
        <h2>2. 支援内容と五領域への配慮</h2>
        <div class="content">{{ $report->support_consideration }}</div>
    </section>

    <section class="body-section">
        <h2>3. 本人の様子・取り組み</h2>
        <div class="content">{{ $report->child_observation }}</div>
    </section>

    <section class="body-section">
        <h2>4. 評価・今後の課題</h2>
        <div class="content">{{ $report->evaluation_and_next }}</div>
    </section>

    <div class="footer">
        {{ $classroom->classroom_name ?? '' }} / 発行 {{ now()->format('Y年n月j日') }}
    </div>
    @include('pdf._watermark')
</body>
</html>
