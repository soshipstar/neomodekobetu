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
            --bg: #ffffff;
            --fg: #1a1a1a;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #14a898;
            --brand-soft: #e0f4f1;
            --open-bg: #d1fae5;
            --open-fg: #047857;
            --limited-bg: #fef3c7;
            --limited-fg: #b45309;
            --full-bg: #fee2e2;
            --full-fg: #b91c1c;
            --closed-bg: #f3f4f6;
            --closed-fg: #6b7280;
        }
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
            max-width: 640px;
            margin: 0 auto;
            padding: 16px;
        }
        header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            border-bottom: 2px solid var(--brand);
            padding-bottom: 8px;
            margin-bottom: 12px;
            gap: 8px;
            flex-wrap: wrap;
        }
        header h1 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--brand);
        }
        header .updated {
            font-size: 0.7rem;
            color: var(--muted);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            font-size: 0.95rem;
        }
        th {
            background: var(--brand-soft);
            color: var(--brand);
            font-weight: 600;
            font-size: 0.8rem;
        }
        td.day {
            font-weight: 700;
            color: var(--fg);
            width: 18%;
        }
        td.count {
            color: var(--muted);
            font-size: 0.8rem;
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
        .badge.open   { background: var(--open-bg);    color: var(--open-fg); }
        .badge.limited { background: var(--limited-bg); color: var(--limited-fg); }
        .badge.full   { background: var(--full-bg);    color: var(--full-fg); }
        .badge.closed { background: var(--closed-bg);  color: var(--closed-fg); }
        footer {
            margin-top: 12px;
            font-size: 0.7rem;
            color: var(--muted);
            text-align: right;
        }
        .note {
            margin-top: 8px;
            font-size: 0.7rem;
            color: var(--muted);
            line-height: 1.4;
        }
        .reloading {
            display: none;
            font-size: 0.7rem;
            color: var(--brand);
            margin-left: 4px;
        }
        .reloading.show { display: inline; }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #1f2937;
                --fg: #f9fafb;
                --muted: #9ca3af;
                --border: #374151;
                --brand-soft: #134e4a;
            }
        }
    </style>
</head>
<body>
    <div class="widget" id="widget-root">
        <header>
            <h1>{{ $payload['classroom']['name'] }} 空き状況<span class="reloading" id="reloading">更新中…</span></h1>
            <span class="updated" id="updated">更新: {{ \Carbon\Carbon::parse($payload['updated_at'])->format('n/j H:i') }}</span>
        </header>
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
        // ページ遷移は発生しないので iframe 内で滑らかに見える。
        // 取得失敗時は meta refresh (5 分) によりフォールバック再読込される。
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
                updatedEl.textContent = '更新: ' + formatTime(payload.updated_at);
            }

            async function refresh() {
                reloading.classList.add('show');
                try {
                    const res = await fetch(ENDPOINT, { cache: 'no-store' });
                    if (!res.ok) return;
                    const payload = await res.json();
                    render(payload);
                } catch (e) {
                    /* ネットワーク失敗時は次回 (60秒後) に再試行 */
                } finally {
                    reloading.classList.remove('show');
                }
            }

            setInterval(refresh, INTERVAL);
        })();
    </script>
</body>
</html>
