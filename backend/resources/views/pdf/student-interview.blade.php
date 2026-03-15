<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>面談記録 - {{ $student->student_name }}</title>
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

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .meta-table td {
            padding: 4px 6px;
            font-size: 10pt;
            border: 1px solid #ccc;
        }

        .meta-label {
            font-weight: bold;
            background: #f0f0f0;
            width: 20%;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 5px;
            margin-top: 10px;
        }

        .section-content {
            padding: 8px;
            border: 1px solid #999;
            min-height: 60px;
            margin-bottom: 10px;
            white-space: pre-wrap;
            font-size: 9pt;
            line-height: 1.6;
        }

        .check-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .check-table td {
            padding: 4px 6px;
            font-size: 9pt;
            border: 1px solid #ccc;
            vertical-align: top;
        }

        .check-label {
            font-weight: bold;
            background: #f0f0f0;
            width: 25%;
        }

        .check-status {
            width: 15%;
            text-align: center;
        }

        .check-yes {
            color: #16a34a;
            font-weight: bold;
        }

        .check-no {
            color: #999;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 7pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>生徒面談記録</h1>
    </div>

    {{-- メタ情報 --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">生徒名</td>
            <td>{{ $student->student_name }}</td>
            <td class="meta-label">面談日</td>
            <td>{{ $interview->interview_date ? $interview->interview_date->format('Y年m月d日') : '' }}</td>
        </tr>
        <tr>
            <td class="meta-label">面談者</td>
            <td colspan="3">{{ $interview->interviewer->full_name ?? '' }}</td>
        </tr>
    </table>

    {{-- 面談内容 --}}
    <div class="section-title">面談内容</div>
    <div class="section-content">{!! nl2br(e($interview->interview_content ?? '')) !!}</div>

    {{-- 本人の希望 --}}
    @if ($interview->child_wish)
    <div class="section-title">本人の希望</div>
    <div class="section-content">{!! nl2br(e($interview->child_wish)) !!}</div>
    @endif

    {{-- チェック項目 --}}
    <div class="section-title">確認項目</div>
    <table class="check-table">
        <tr>
            <td class="check-label">学校での様子</td>
            <td class="check-status">
                @if ($interview->check_school)
                    <span class="check-yes">あり</span>
                @else
                    <span class="check-no">なし</span>
                @endif
            </td>
            <td>{!! nl2br(e($interview->check_school_notes ?? '')) !!}</td>
        </tr>
        <tr>
            <td class="check-label">家庭での様子</td>
            <td class="check-status">
                @if ($interview->check_home)
                    <span class="check-yes">あり</span>
                @else
                    <span class="check-no">なし</span>
                @endif
            </td>
            <td>{!! nl2br(e($interview->check_home_notes ?? '')) !!}</td>
        </tr>
        <tr>
            <td class="check-label">困りごと</td>
            <td class="check-status">
                @if ($interview->check_troubles)
                    <span class="check-yes">あり</span>
                @else
                    <span class="check-no">なし</span>
                @endif
            </td>
            <td>{!! nl2br(e($interview->check_troubles_notes ?? '')) !!}</td>
        </tr>
    </table>

    {{-- その他 --}}
    @if ($interview->other_notes)
    <div class="section-title">その他メモ</div>
    <div class="section-content">{!! nl2br(e($interview->other_notes)) !!}</div>
    @endif

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
