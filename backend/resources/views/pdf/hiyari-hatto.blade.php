<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ヒヤリハット記録</title>
    <style>
        @page { size: A4; margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "Noto Sans JP", "IPAGothic", "Hiragino Sans", sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #222;
        }
        h1 { font-size: 16pt; text-align: center; margin: 0 0 12pt; letter-spacing: 2pt; }
        .meta { font-size: 9pt; text-align: right; color: #555; margin-bottom: 6pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        th, td { border: 1px solid #333; padding: 4pt 6pt; vertical-align: top; }
        th {
            background: #f2f2f2;
            font-weight: bold;
            text-align: left;
            width: 22%;
            white-space: nowrap;
        }
        .severity-low { color: #0a7b28; font-weight: bold; }
        .severity-medium { color: #c08400; font-weight: bold; }
        .severity-high { color: #c00000; font-weight: bold; }
        .section-title {
            background: #3a6ea5;
            color: white;
            padding: 4pt 6pt;
            font-weight: bold;
            margin-top: 10pt;
        }
        .sign-row td { height: 50pt; }
        .checkbox { display: inline-block; width: 10pt; height: 10pt; border: 1px solid #555; text-align: center; line-height: 10pt; margin-right: 3pt; }
        .note { font-size: 8pt; color: #666; margin-top: 2pt; }
    </style>
</head>
<body>
    <h1>ヒヤリハット記録</h1>
    <div class="meta">記録 ID: {{ $record->id }} / 記録日時: {{ $record->created_at->format('Y年n月j日 H:i') }}</div>

    {{-- 基本情報 --}}
    <div class="section-title">1. 基本情報</div>
    <table>
        <tr>
            <th>発生日時</th>
            <td>{{ $record->occurred_at->format('Y年n月j日 (D) H:i') }}</td>
            <th>発生場所</th>
            <td>{{ $record->location ?? '—' }}</td>
        </tr>
        <tr>
            <th>対象児童</th>
            <td>
                {{ $record->student?->student_name ?? '—' }}
                @if($record->student?->grade_level)
                    ({{ $record->student->grade_level }})
                @endif
            </td>
            <th>事業所</th>
            <td>{{ $record->classroom?->classroom_name ?? '—' }}</td>
        </tr>
        <tr>
            <th>記録者</th>
            <td>{{ $record->reporter?->full_name ?? '—' }}</td>
            <th>確認者</th>
            <td>{{ $record->confirmedBy?->full_name ?? '' }}</td>
        </tr>
        <tr>
            <th>危険度</th>
            <td>
                <span class="severity-{{ $record->severity }}">
                    {{ $severities[$record->severity] ?? $record->severity }}
                </span>
            </td>
            <th>事故分類</th>
            <td>{{ $categories[$record->category] ?? $record->category ?? '—' }}</td>
        </tr>
    </table>

    {{-- 発生状況 --}}
    <div class="section-title">2. 発生状況</div>
    <table>
        <tr>
            <th>発生前の活動</th>
            <td>{!! nl2br(e($record->activity_before ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>児童の状態</th>
            <td>{!! nl2br(e($record->student_condition ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>発生状況の詳細</th>
            <td>{!! nl2br(e($record->situation)) !!}</td>
        </tr>
    </table>

    {{-- 原因分析 --}}
    <div class="section-title">3. 原因分析</div>
    <table>
        <tr>
            <th>環境要因</th>
            <td>{!! nl2br(e($record->cause_environmental ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>人的要因</th>
            <td>{!! nl2br(e($record->cause_human ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>その他要因</th>
            <td>{!! nl2br(e($record->cause_other ?? '—')) !!}</td>
        </tr>
    </table>

    {{-- 対応 --}}
    <div class="section-title">4. 対応</div>
    <table>
        <tr>
            <th>即時対応</th>
            <td>{!! nl2br(e($record->immediate_response ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>怪我の有無・内容</th>
            <td>{!! nl2br(e($record->injury_description ?? '無し')) !!}</td>
        </tr>
        <tr>
            <th>医療機関受診</th>
            <td>
                <span class="checkbox">{{ $record->medical_treatment ? '✓' : '' }}</span> 受診した
                @if($record->medical_detail)
                    <div class="note">{!! nl2br(e($record->medical_detail)) !!}</div>
                @endif
            </td>
        </tr>
        <tr>
            <th>保護者連絡</th>
            <td>
                <span class="checkbox">{{ $record->guardian_notified ? '✓' : '' }}</span>
                連絡済み
                @if($record->guardian_notified_at)
                    ({{ $record->guardian_notified_at->format('Y年n月j日 H:i') }})
                @endif
                @if($record->guardian_notification_content)
                    <div class="note">{!! nl2br(e($record->guardian_notification_content)) !!}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- 再発防止策 --}}
    <div class="section-title">5. 再発防止策</div>
    <table>
        <tr>
            <th>改善策</th>
            <td>{!! nl2br(e($record->prevention_measures ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>環境整備</th>
            <td>{!! nl2br(e($record->environment_improvements ?? '—')) !!}</td>
        </tr>
        <tr>
            <th>スタッフ間共有</th>
            <td>{!! nl2br(e($record->staff_sharing_notes ?? '—')) !!}</td>
        </tr>
    </table>

    {{-- 署名 --}}
    <div class="section-title">6. 確認欄</div>
    <table>
        <tr class="sign-row">
            <th>記録者</th>
            <td>{{ $record->reporter?->full_name ?? '' }}</td>
            <th>管理者確認</th>
            <td>{{ $record->confirmedBy?->full_name ?? '' }}</td>
        </tr>
    </table>
</body>
</html>
