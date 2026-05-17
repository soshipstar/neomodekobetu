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

    // primary 色を base に薄/濃を生成 (HSL 風だが簡易: 1byteづつ操作)
    $hex2rgb = function (string $hex) {
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    };
    [$pr, $pg, $pb] = $hex2rgb($primary);
    $primarySoft = sprintf('rgba(%d,%d,%d,0.1)', $pr, $pg, $pb);

    // dark mode の補正
    if ($mode === 'dark') {
        $border = 'rgba(255,255,255,0.1)';
        $muted = 'rgba(255,255,255,0.5)';
        $rowAlt = 'rgba(255,255,255,0.03)';
    } else {
        $border = '#e5e7eb';
        $muted = '#6b7280';
        $rowAlt = '#fafafa';
    }
    // bg=transparent のときは subtle な薄背景を CSS で吸収
    $bgValue = $bg === 'transparent' ? 'transparent' : '#' . $bg;
    $fgValue = '#' . $fg;
    $primaryValue = '#' . $primary;
    $compact = $theme['compact'];
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload['classroom']['name'] }} 空き状況</title>
    {{-- meta refresh のバックアップ (5 分ごと) と、JS ポーリング (60 秒) の二段構え --}}
    <meta http-equiv="refresh" content="300">
    <style>
        :root {
            --bg: {{ $bgValue }};
            --fg: {{ $fgValue }};
            --primary: {{ $primaryValue }};
            --primary-soft: {{ $primarySoft }};
            --muted: {{ $muted }};
            --border: {{ $border }};
            --row-alt: {{ $rowAlt }};
            --radius: {{ $radius }};
            --open-bg: rgba(16,185,129,0.15);
            --open-fg: #047857;
            --limited-bg: rgba(245,158,11,0.18);
            --limited-fg: #b45309;
            --full-bg: rgba(239,68,68,0.15);
            --full-fg: #b91c1c;
            --closed-bg: rgba(107,114,128,0.15);
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
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        .widget {
            max-width: {{ $compact ? '320px' : '640px' }};
            margin: 0 auto;
            padding: 16px;
        }
        @if($theme['header'])
        header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 8px;
            margin-bottom: 12px;
            gap: 8px;
            flex-wrap: wrap;
        }
        header h1 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
        }
        header .updated {
            font-size: 0.7rem;
            color: var(--muted);
        }
        @endif
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        th, td {
            padding: {{ $compact ? '8px 6px' : '10px 8px' }};
            text-align: center;
            border-bottom: 1px solid var(--border);
            font-size: 0.95rem;
        }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:nth-child(even) td { background: var(--row-alt); }
        th {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
            font-size: 0.78rem;
            letter-spacing: 0.05em;
        }
        td.day {
            font-weight: 700;
            color: var(--fg);
            width: 18%;
        }
        td.count {
            color: var(--muted);
            font-size: 0.78rem;
            width: 32%;
        }
        td.count strong { color: var(--fg); font-size: 1rem; }
        td.status {
            width: 50%;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
            min-width: 76px;
        }
        .badge.open    { background: var(--open-bg);    color: var(--open-fg); }
        .badge.limited { background: var(--limited-bg); color: var(--limited-fg); }
        .badge.full    { background: var(--full-bg);    color: var(--full-fg); }
        .badge.closed  { background: var(--closed-bg);  color: var(--closed-fg); }
        .note {
            margin-top: 10px;
            font-size: 0.7rem;
            color: var(--muted);
            line-height: 1.4;
        }
        footer {
            margin-top: 10px;
            font-size: 0.65rem;
            color: var(--muted);
            text-align: right;
            opacity: 0.7;
        }
        .reloading {
            display: none;
            font-size: 0.7rem;
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
        <table>
            <thead>
                <tr>
                    <th>曜日</th>
                    <th>利用</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody id="rows">
            @foreach($payload['days'] as $d)
                <tr>
                    <td class="day">{{ $d['label'] }}</td>
                    <td class="count">
                        @if($d['is_open'])
                            <strong>{{ $d['enrolled'] }}</strong> / {{ $d['max_capacity'] }}名
                        @else
                            ―
                        @endif
                    </td>
                    <td class="status">
                        <span class="badge {{ $d['status'] }}">{{ $d['status_label'] }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="note">{{ $payload['note'] }}</p>
        <footer>powered by KIDURI</footer>
    </div>

    <script>
        // 60 秒ごとに最新の空き状況を取得して画面を書き換える。
        (function () {
            const ENDPOINT = {!! json_encode(url('/api/widget/vacancy/' . $token . '/data')) !!};
            const INTERVAL = 60 * 1000;

            const reloading = document.getElementById('reloading');
            const updatedEl = document.getElementById('updated');
            const rowsEl    = document.getElementById('rows');

            function formatTime(iso) {
                const d = new Date(iso);
                return (d.getMonth() + 1) + '/' + d.getDate() + ' ' +
                    String(d.getHours()).padStart(2, '0') + ':' +
                    String(d.getMinutes()).padStart(2, '0');
            }

            function render(payload) {
                if (!payload || !payload.days) return;
                const html = payload.days.map(d => {
                    const countCell = d.is_open
                        ? '<strong>' + d.enrolled + '</strong> / ' + d.max_capacity + '名'
                        : '―';
                    return '<tr>' +
                        '<td class="day">' + d.label + '</td>' +
                        '<td class="count">' + countCell + '</td>' +
                        '<td class="status"><span class="badge ' + d.status + '">' + d.status_label + '</span></td>' +
                        '</tr>';
                }).join('');
                rowsEl.innerHTML = html;
                if (updatedEl) updatedEl.textContent = '更新: ' + formatTime(payload.updated_at);
            }

            async function refresh() {
                if (reloading) reloading.classList.add('show');
                try {
                    const res = await fetch(ENDPOINT, { cache: 'no-store' });
                    if (!res.ok) return;
                    const payload = await res.json();
                    render(payload);
                } catch (e) {
                    /* ネットワーク失敗時は次回 (60秒後) に再試行 */
                } finally {
                    if (reloading) reloading.classList.remove('show');
                }
            }

            setInterval(refresh, INTERVAL);
        })();
    </script>
</body>
</html>
