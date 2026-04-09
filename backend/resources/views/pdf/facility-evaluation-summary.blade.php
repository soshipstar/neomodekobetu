<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $type === 'staff' ? '事業所内評価集計結果' : '保護者評価集計結果' }} - {{ $classroom_name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm;
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

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 6px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: 700;
            letter-spacing: 2pt;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 8pt;
        }

        .category-title {
            background: #2c3e50;
            color: #fff;
            padding: 4px 8px;
            font-size: 10pt;
            font-weight: 700;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th, td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 8pt;
        }

        th {
            background: #ecf0f1;
            font-weight: 700;
            text-align: center;
        }

        td.center {
            text-align: center;
        }

        .yes-high { color: #27ae60; font-weight: bold; }
        .yes-mid  { color: #f39c12; font-weight: bold; }
        .yes-low  { color: #e74c3c; font-weight: bold; }

        .footer {
            margin-top: 12px;
            font-size: 7pt;
            color: #666;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $type === 'staff' ? '事業所における自己評価結果（従業者評価）' : '保護者等からの事業所評価の集計結果' }}</h1>
    </div>

    <div class="meta">
        <div>
            <strong>事業所名:</strong> {{ $classroom_name }}
        </div>
        <div>
            <strong>評価期間:</strong> {{ $period->title ?? '' }}（{{ $period->fiscal_year ?? '' }}年度）
        </div>
    </div>
    <div class="meta">
        <div>
            <strong>回答者数:</strong> {{ $total_respondents }}名
        </div>
        <div>
            <strong>出力日:</strong> {{ now()->format('Y年m月d日') }}
        </div>
    </div>

    @php
        $categories = [];
        foreach ($summary as $item) {
            $cat = $item->category ?: '未分類';
            $categories[$cat][] = $item;
        }
    @endphp

    @foreach ($categories as $cat => $items)
        <div class="category-title">{{ $cat }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%">No.</th>
                    <th style="width: 45%">質問</th>
                    <th style="width: 10%">はい</th>
                    <th style="width: 10%">どちらとも</th>
                    <th style="width: 10%">いいえ</th>
                    <th style="width: 10%">わからない</th>
                    <th style="width: 10%">はい%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php
                        $total = $item->yes_count + $item->neutral_count + $item->no_count;
                        $pct = $total > 0 ? round(($item->yes_count / $total) * 100, 1) : 0;
                        $cls = $pct >= 80 ? 'yes-high' : ($pct >= 50 ? 'yes-mid' : 'yes-low');
                    @endphp
                    <tr>
                        <td class="center">{{ $item->question_number }}</td>
                        <td>{{ $item->question_text }}</td>
                        <td class="center">{{ $item->yes_count }}</td>
                        <td class="center">{{ $item->neutral_count }}</td>
                        <td class="center">{{ $item->no_count }}</td>
                        <td class="center">{{ $item->unknown_count }}</td>
                        <td class="center {{ $cls }}">{{ $pct }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        {{ $classroom_name }} — {{ now()->format('Y/m/d H:i') }} 出力
    </div>
</body>
</html>
