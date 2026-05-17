@php
    /**
     * テーマカラーから派生色を計算する。
     * 全部 inline で完結させて XSS / CSS injection を避ける
     * (theme 配列は Controller で正規化済み = hex 6 桁 / 'transparent' のみ)。
     */
    $primary = $theme['primary'];
    $bg      = $theme['bg'];
    $fg      = $theme['fg'];
    $mode    = $theme['mode']; // light | dark
    $radiusMap = ['none' => '0', 'sm' => '6px', 'md' => '10px', 'lg' => '16px'];
    $radius = $radiusMap[$theme['radius']];

    $hex2rgb = function (string $hex) {
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    };
    [$pr, $pg, $pb] = $hex2rgb($primary);
    $primarySoft = sprintf('rgba(%d,%d,%d,0.1)', $pr, $pg, $pb);

    if ($mode === 'dark') {
        $border = 'rgba(255,255,255,0.1)';
        $muted = 'rgba(255,255,255,0.55)';
    } else {
        $border = '#e5e7eb';
        $muted = '#6b7280';
    }
    $bgValue = $bg === 'transparent' ? 'transparent' : '#' . $bg;
    $fgValue = '#' . $fg;
    $primaryValue = '#' . $primary;

    // 横並び (h) が既定。compact=1 のときは縦並びにフォールバック (狭幅ウィジェット向け)。
    $effectiveLayout = $theme['compact'] ? 'v' : $layout;

    // 予想が1つでもあるか (フッターの免責表示判定用)
    $hasPrediction = false;
    foreach ($payload['days'] as $d) {
        if (!empty($d['prediction'])) { $hasPrediction = true; break; }
    }
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['classroom']['name'] }} 空き状況</title>
    <meta http-equiv="refresh" content="300">
    <style>
        :root {
            --bg: {{ $bgValue }};
            --fg: {{ $fgValue }};
            --primary: {{ $primaryValue }};
            --primary-soft: {{ $primarySoft }};
            --muted: {{ $muted }};
            --border: {{ $border }};
            --radius: {{ $radius }};
            --open-bg: rgba(16,185,129,0.18);
            --open-fg: #047857;
            --limited-bg: rgba(245,158,11,0.20);
            --limited-fg: #b45309;
            --full-bg: rgba(239,68,68,0.16);
            --full-fg: #b91c1c;
            --closed-bg: rgba(107,114,128,0.14);
            --closed-fg: #6b7280;
        }
        @if($mode === 'dark')
        :root {
            --open-fg: #34d399;
            --limited-fg: #fbbf24;
            --full-fg: #f87171;
            --closed-fg: #9ca3af;
        }
        @endif
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: var(--bg);
            color: var(--fg);
            font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", "Meiryo", sans-serif;
            font-size: 14px;
            line-height: 1.4;
            -webkit-font-smoothing: antialiased;
        }
        .widget {
            max-width: {{ $effectiveLayout === 'h' ? '720px' : '420px' }};
            margin: 0 auto;
            padding: 10px 12px;
        }
        @if($theme['header'])
        header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 5px;
            margin-bottom: 8px;
            gap: 8px;
            flex-wrap: wrap;
        }
        header h1 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
        }
        header .updated {
            font-size: 0.65rem;
            color: var(--muted);
        }
        @endif

        /* =============== 横並び (デフォルト) =============== */
        .h-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .h-grid .col {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 6px 2px 8px;
            border-right: 1px solid var(--border);
            min-height: 70px;
        }
        .h-grid .col:last-child { border-right: 0; }
        .h-grid .day {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--primary);
            background: var(--primary-soft);
            width: 100%;
            text-align: center;
            padding: 4px 0;
            margin: -6px -2px 6px;
        }
        .h-grid .col[data-day="sunday"] .day { color: #ef4444; }
        .h-grid .col[data-day="saturday"] .day { color: var(--primary); }
        .h-grid .icon {
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1;
            margin-top: 4px;
        }
        .h-grid .icon.open    { color: var(--open-fg); }
        .h-grid .icon.limited { color: var(--limited-fg); }
        .h-grid .icon.full    { color: var(--full-fg); }
        .h-grid .icon.closed  { color: var(--closed-fg); font-size: 1.1rem; padding-top: 8px; }
        .h-grid .pred {
            margin-top: 3px;
            font-size: 0.6rem;
            color: var(--muted);
            text-align: center;
            line-height: 1.1;
        }

        /* =============== 縦並び =============== */
        .v-list {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .v-list .row {
            display: grid;
            grid-template-columns: 60px 1fr auto;
            align-items: center;
            padding: 6px 10px;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .v-list .row:last-child { border-bottom: 0; }
        .v-list .row:nth-child(odd) { background: rgba(0,0,0,0.02); }
        @if($mode === 'dark')
        .v-list .row:nth-child(odd) { background: rgba(255,255,255,0.03); }
        @endif
        .v-list .day {
            font-weight: 700;
            color: var(--primary);
        }
        .v-list .row[data-day="sunday"] .day { color: #ef4444; }
        .v-list .row[data-day="saturday"] .day { color: var(--primary); }
        .v-list .pred {
            font-size: 0.7rem;
            color: var(--muted);
        }
        .v-list .icon {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1;
            min-width: 28px;
            text-align: center;
        }
        .v-list .icon.open    { color: var(--open-fg); }
        .v-list .icon.limited { color: var(--limited-fg); }
        .v-list .icon.full    { color: var(--full-fg); }
        .v-list .icon.closed  { color: var(--closed-fg); font-size: 0.95rem; }

        /* 凡例 + 注記 */
        .legend {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 6px;
            font-size: 0.7rem;
            color: var(--muted);
            flex-wrap: wrap;
        }
        .legend span { display: inline-flex; align-items: center; gap: 2px; }
        .legend .lg-open    { color: var(--open-fg); font-weight: 700; }
        .legend .lg-limited { color: var(--limited-fg); font-weight: 700; }
        .legend .lg-full    { color: var(--full-fg); font-weight: 700; }
        .legend .lg-closed  { color: var(--closed-fg); font-weight: 700; }

        .note {
            margin-top: 6px;
            font-size: 0.65rem;
            color: var(--muted);
            line-height: 1.4;
        }
        .disclaimer {
            margin-top: 4px;
            font-size: 0.6rem;
            color: var(--muted);
            font-style: italic;
        }
        .reloading {
            display: none;
            font-size: 0.65rem;
            color: var(--primary);
            margin-left: 4px;
        }
        .reloading.show { display: inline; }
    </style>
</head>
<body>
    <div class="widget" id="widget-root">
        @if($theme['header'])
        <header>
            <h1>{{ $payload['classroom']['name'] }} 空き状況<span class="reloading" id="reloading">更新中…</span></h1>
            <span class="updated" id="updated">更新: {{ \Carbon\Carbon::parse($payload['updated_at'])->format('n/j H:i') }}</span>
        </header>
        @endif

        @if($effectiveLayout === 'h')
        <div class="h-grid" id="grid-h">
            @foreach($payload['days'] as $d)
                <div class="col" data-day="{{ $d['day'] }}">
                    <div class="day">{{ $d['label'] }}</div>
                    <div class="icon {{ $d['status'] }}">{{ $d['status_icon'] }}</div>
                    @if($predict && !empty($d['prediction']))
                        <div class="pred">{{ $d['prediction']['month_label'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
        @else
        <div class="v-list" id="grid-v">
            @foreach($payload['days'] as $d)
                <div class="row" data-day="{{ $d['day'] }}">
                    <div class="day">{{ $d['label'] }}</div>
                    <div class="pred">
                        @if($predict && !empty($d['prediction']))
                            {{ $d['prediction']['month_label'] }} に空く見込み
                        @endif
                    </div>
                    <div class="icon {{ $d['status'] }}">{{ $d['status_icon'] }}</div>
                </div>
            @endforeach
        </div>
        @endif

        <div class="legend">
            <span><span class="lg-open">〇</span>空きあり</span>
            <span><span class="lg-limited">△</span>わずか</span>
            <span><span class="lg-full">×</span>満席</span>
            <span><span class="lg-closed">休</span>休業</span>
        </div>

        @if($predict && $hasPrediction)
        <p class="disclaimer">
            ※「○月頃」はあくまで現時点での推測です。実際の空き時期を確約するものではありません。
        </p>
        @endif

        <p class="note">{{ $payload['note'] }}</p>
    </div>

    <script>
        // 60 秒ごとに最新の空き状況を取得して画面を書き換える。
        (function () {
            const DATA_URL = {!! json_encode(url('/api/widget/vacancy/' . $token . '/data') . ($predict ? '?predict=1' : '')) !!};
            const INTERVAL = 60 * 1000;
            const PREDICT  = {{ $predict ? 'true' : 'false' }};
            const LAYOUT   = {!! json_encode($effectiveLayout) !!};

            const reloading = document.getElementById('reloading');
            const updatedEl = document.getElementById('updated');

            function formatTime(iso) {
                const d = new Date(iso);
                return (d.getMonth() + 1) + '/' + d.getDate() + ' ' +
                    String(d.getHours()).padStart(2, '0') + ':' +
                    String(d.getMinutes()).padStart(2, '0');
            }

            function renderH(days) {
                return days.map(d => {
                    const pred = (PREDICT && d.prediction) ? `<div class="pred">${d.prediction.month_label}</div>` : '';
                    return `<div class="col" data-day="${d.day}">
                        <div class="day">${d.label}</div>
                        <div class="icon ${d.status}">${d.status_icon}</div>
                        ${pred}
                    </div>`;
                }).join('');
            }

            function renderV(days) {
                return days.map(d => {
                    const pred = (PREDICT && d.prediction) ? `${d.prediction.month_label} に空く見込み` : '';
                    return `<div class="row" data-day="${d.day}">
                        <div class="day">${d.label}</div>
                        <div class="pred">${pred}</div>
                        <div class="icon ${d.status}">${d.status_icon}</div>
                    </div>`;
                }).join('');
            }

            async function refresh() {
                if (reloading) reloading.classList.add('show');
                try {
                    const res = await fetch(DATA_URL, { cache: 'no-store' });
                    if (!res.ok) return;
                    const payload = await res.json();
                    const target = document.getElementById(LAYOUT === 'h' ? 'grid-h' : 'grid-v');
                    if (target) target.innerHTML = LAYOUT === 'h' ? renderH(payload.days) : renderV(payload.days);
                    if (updatedEl) updatedEl.textContent = '更新: ' + formatTime(payload.updated_at);
                } catch (e) { /* 次回再試行 */ }
                finally { if (reloading) reloading.classList.remove('show'); }
            }
            setInterval(refresh, INTERVAL);
        })();
    </script>
</body>
</html>
