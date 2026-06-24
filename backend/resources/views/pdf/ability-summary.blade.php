<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>評価状況の全体像</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'IPAGothic', 'Noto Sans CJK JP', sans-serif; color: #1a1a1a; font-size: 11px; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .sub { color: #555; font-size: 11px; margin-bottom: 12px; }
        .meta { margin-bottom: 14px; }
        .meta span { margin-right: 18px; }
        .note { color: #555; font-size: 10px; margin-bottom: 14px; }
        .domain { margin-bottom: 16px; page-break-inside: avoid; }
        .domain-head { display: flex; align-items: center; justify-content: space-between;
            background: #eef2f7; padding: 5px 8px; border-left: 4px solid #3b82f6; font-weight: bold; }
        .bar-wrap { background: #e5e7eb; height: 8px; border-radius: 4px; width: 160px; overflow: hidden; }
        .bar { background: #3b82f6; height: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-weight: bold; }
        .score { text-align: center; font-weight: bold; width: 38px; }
        .review { color: #b45309; font-size: 10px; }
        .empty { color: #777; padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <h1>評価状況の全体像（別添）</h1>
    <div class="sub">個別支援計画 別添資料 ／ 客観的評価（支援者記録による能力評価）</div>

    <div class="meta">
        <span>児童名: {{ $student->student_name ?? '' }}</span>
        <span>事業所: {{ $classroom->classroom_name ?? '' }}</span>
        <span>作成日: {{ $generatedOn }}</span>
    </div>

    @if (empty($summary['has_data']))
        <div class="empty">まだ評価スコアがありません。日々の観察記録が貯まると自動的に反映されます。</div>
    @else
        <div class="note">
            点数は0〜10（個人内評価。他児との比較ではなく「過去の自分からの成長」を見ます）。
            「要確認」は前回から変動が大きく支援者の確認が望ましい項目です。
            評価済み {{ $summary['counts']['scored'] }} 項目 ／ 要確認 {{ $summary['counts']['needs_review'] }} 項目。
        </div>

        @foreach ($summary['domains'] as $d)
            <div class="domain">
                <div class="domain-head">
                    <span>{{ $d['domain'] }}</span>
                    <span style="display:flex; align-items:center; gap:8px;">
                        @if ($d['average'] !== null)
                            <span class="bar-wrap"><span class="bar" style="width: {{ $d['average'] * 10 }}%"></span></span>
                            平均 {{ $d['average'] }}
                        @else
                            <span style="color:#777;">客観評価はまだありません</span>
                        @endif
                    </span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>項目</th>
                            <th>段階・水準</th>
                            <th class="score">客観</th>
                            <th class="score">主観</th>
                            <th>保護者向けのことば</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['items'] as $it)
                            <tr>
                                <td>{{ $it['item_name'] }}@if ($it['needs_review'])<span class="review">（要確認）</span>@endif</td>
                                <td>{{ $it['axis_name'] ?? '' }}</td>
                                <td class="score">{{ $it['score'] }}</td>
                                <td class="score">{{ $it['subjective'] ?? '—' }}</td>
                                <td>{{ $it['guardian_words'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        {{-- 到達マップ(項目×学年帯) — 客観スコアがある場合のみ --}}
        @if (($summary['counts']['scored'] ?? 0) > 0 && !empty(($map ?? [])['tools'] ?? []))
            <h2 style="font-size:14px; margin:18px 0 4px; page-break-before: always;">到達マップ（項目×学年帯）</h2>
            <div class="note">各セルの到達状況: ✓=到達 ／ ★=般化 ／ △=途上 ／ ・=未着手。期間ごとの伸びは画面の到達マップで確認できます。</div>
            @foreach ($map['tools'] as $tool)
                <div class="domain">
                    <div class="domain-head"><span>{{ $tool['tool_id'] }}</span></div>
                    <table>
                        <thead>
                            <tr>
                                <th>能力項目</th>
                                @foreach ($tool['axes'] as $a)
                                    <th class="score" title="{{ $a['name'] }}">{{ $a['axis_id'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tool['domains'] as $dom)
                                <tr><td colspan="{{ count($tool['axes']) + 1 }}" style="background:#f1f5f9; font-weight:bold;">{{ $dom['domain'] }}</td></tr>
                                @foreach ($dom['items'] as $item)
                                    @php $byAxis = collect($item['cells'])->keyBy('axis_id'); @endphp
                                    <tr>
                                        <td>{{ $item['item_name'] }}</td>
                                        @foreach ($tool['axes'] as $a)
                                            @php
                                                $cell = $byAxis->get($a['axis_id']);
                                                $st = $cell['status'] ?? 'not_started';
                                                $sym = ['not_started' => '・', 'in_progress' => '△', 'achieved' => '✓', 'generalized' => '★'][$st] ?? '・';
                                            @endphp
                                            <td class="score">{{ $sym }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    @endif
</body>
</html>
