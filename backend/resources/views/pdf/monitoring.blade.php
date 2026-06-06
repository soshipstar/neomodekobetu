<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>モニタリング記録 - {{ $student->student_name ?? '' }}</title>
    <style>
        
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'IPA Gothic', 'IPAGothic', 'Noto Sans JP', sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #333;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 16pt;
            margin-bottom: 5px;
        }

        .header-meta {
            font-size: 9pt;
            color: #555;
        }

        .header-meta span {
            margin: 0 10px;
        }

        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background: #e8e8e8;
            padding: 4px 10px;
            margin-bottom: 6px;
            border-left: 3px solid #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th, td {
            border: 1px solid #999;
            padding: 4px 6px;
            vertical-align: top;
            text-align: left;
            font-size: 9pt;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }

        .label-cell {
            background: #f9f9f9;
            font-weight: bold;
            width: 100px;
            font-size: 8pt;
        }

        .content-cell {
            word-break: break-word;
        }

        .empty {
            color: #999;
            font-style: italic;
        }

        .overall-content {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 8px;
            line-height: 1.7;
            font-size: 9pt;
            margin-bottom: 10px;
        }

        .achievement-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8pt;
            font-weight: bold;
            color: white;
        }

        .achievement-high { background: #10b981; }
        .achievement-mid { background: #f59e0b; }
        .achievement-low { background: #ef4444; }

        .signature-table {
            width: 100%;
            margin-top: 15px;
            border-top: 1px solid #333;
        }

        .signature-table td {
            border: none;
            padding: 5px 8px;
            vertical-align: middle;
            font-size: 9pt;
        }

        .footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #ccc;
            font-size: 7pt;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        // 事業所のサービス種別に応じて呼称 (生徒/利用者、保護者/家族、児発管/サ管) を切替える
        $serviceType = $classroom->service_type ?? 'after_school';
        $terms = \App\Services\ServiceTypeRegistry::terms($serviceType);
    @endphp
    <div class="header">
        <h1>モニタリング記録</h1>
        <div class="header-meta">
            <span>{{ $terms['client'] }}氏名: {{ $student->student_name ?? '' }}</span>
            <span>事業所: {{ $classroom->classroom_name ?? '' }}</span>
            <span>実施日: {{ $record->monitoring_date ? $record->monitoring_date->format('Y年m月d日') : '' }}</span>
        </div>
    </div>

    {{-- 計画情報 --}}
    @if ($plan)
    <div class="section">
        <div class="section-title">対象計画の情報</div>
        <table>
            <tr>
                <td class="label-cell">計画作成日</td>
                <td class="content-cell">{{ $plan->created_date ? $plan->created_date->format('Y年m月d日') : '（未設定）' }}</td>
                <td class="label-cell">同意日</td>
                <td class="content-cell">{{ $plan->consent_date ? $plan->consent_date->format('Y年m月d日') : '（未設定）' }}</td>
            </tr>
            <tr>
                <td class="label-cell">長期目標</td>
                <td class="content-cell" colspan="3">{{ $plan->long_term_goal ?: '（未設定）' }}</td>
            </tr>
            <tr>
                <td class="label-cell">短期目標</td>
                <td class="content-cell" colspan="3">{{ $plan->short_term_goal ?: '（未設定）' }}</td>
            </tr>
        </table>
    </div>
    @endif

    {{-- 目標達成状況 --}}
    <div class="section">
        <div class="section-title">目標達成状況</div>
        <table>
            <tr>
                <td class="label-cell">短期目標達成度</td>
                <td class="content-cell">{{ $record->short_term_goal_achievement ?? '（未評価）' }}</td>
                <td class="label-cell">長期目標達成度</td>
                <td class="content-cell">{{ $record->long_term_goal_achievement ?? '（未評価）' }}</td>
            </tr>
        </table>
    </div>

    {{-- 総合所見 --}}
    <div class="section">
        <div class="section-title">総合所見</div>
        <div class="overall-content">{!! nl2br(e($record->overall_comment ?: '（未記入）')) !!}</div>
    </div>

    {{-- 強み（才能）チェック推移 --}}
    @php
        $strengthsSummary = $record->strengths_summary;
        $strengthsTrends = is_array($strengthsSummary) ? ($strengthsSummary['trends'] ?? []) : [];
    @endphp
    @if (!empty($strengthsTrends))
    <div class="section">
        <div class="section-title">
            強み（才能）チェック推移
            <span style="font-weight: normal; font-size: 9pt;">
                ({{ $strengthsSummary['from'] ?? '' }} 〜 {{ $strengthsSummary['to'] ?? '' }} / {{ $strengthsSummary['record_count'] ?? 0 }}件)
            </span>
        </div>
        @php
            $monthKeys = collect($strengthsTrends)->flatMap(fn ($t) => array_keys($t['monthly_averages'] ?? []))->unique()->sort()->values()->all();
        @endphp
        <table>
            <thead>
                <tr>
                    <th style="width: 18%;">項目</th>
                    <th style="width: 12%;">領域</th>
                    <th style="width: 10%;">平均</th>
                    <th style="width: 12%;">推移</th>
                    @foreach ($monthKeys as $m)
                        <th style="text-align: right;">{{ $m }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($strengthsTrends as $t)
                    @php
                        $arrow = ($t['trend'] ?? 'stable') === 'up' ? '↑' : (($t['trend'] ?? '') === 'down' ? '↓' : '→');
                        $sign  = ($t['change'] ?? 0) >= 0 ? '+' : '';
                    @endphp
                    <tr>
                        <td style="font-weight: bold;">{{ $t['label'] ?? '' }}</td>
                        <td>{{ $t['domain'] ?? '-' }}</td>
                        <td style="text-align: right;">{{ $t['overall_average'] ?? '-' }}</td>
                        <td style="text-align: right;">{{ $arrow }} {{ $sign }}{{ $t['change'] ?? 0 }}</td>
                        @foreach ($monthKeys as $m)
                            <td style="text-align: right;">{{ $t['monthly_averages'][$m] ?? '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php
            $growing = collect($strengthsTrends)->where('trend', 'up')->sortByDesc('change')->values();
            $declining = collect($strengthsTrends)->where('trend', 'down')->sortBy('change')->values();
        @endphp
        @if ($growing->isNotEmpty())
            <div style="margin-top: 4px; font-size: 9pt;">
                <strong>★成長:</strong>
                {{ $growing->map(fn ($t) => "{$t['label']}(+{$t['change']})")->implode('、') }}
            </div>
        @endif
        @if ($declining->isNotEmpty())
            <div style="margin-top: 2px; font-size: 9pt;">
                <strong>※低下:</strong>
                {{ $declining->map(fn ($t) => "{$t['label']}({$t['change']})")->implode('、') }}
            </div>
        @endif
    </div>
    @endif

    {{-- 就労 A/B 用メトリクス (employment_metrics) --}}
    @php
        $employment = is_array($strengthsSummary) ? ($strengthsSummary['employment_metrics'] ?? null) : null;
    @endphp
    @if (!empty($employment))
    <div class="section">
        <div class="section-title">
            就労メトリクス
            <span style="font-weight: normal; font-size: 9pt;">
                ({{ $strengthsSummary['from'] ?? '' }} 〜 {{ $strengthsSummary['to'] ?? '' }})
            </span>
        </div>
        <table>
            <tr>
                <td class="label-cell">工賃対象時間 合計</td>
                <td class="content-cell">{{ $employment['total_wage_eligible_hours'] ?? 0 }}時間</td>
                <td class="label-cell">1日平均</td>
                <td class="content-cell">{{ $employment['average_wage_eligible_hours'] ?? 0 }}時間</td>
            </tr>
            <tr>
                <td class="label-cell">出勤率</td>
                <td class="content-cell">{{ $employment['attendance_rate'] ?? 0 }}%</td>
                <td class="label-cell">平均出退勤</td>
                <td class="content-cell">
                    {{ $employment['average_clock_in'] ?? '-' }} 〜 {{ $employment['average_clock_out'] ?? '-' }}
                </td>
            </tr>
        </table>
        @php
            $works = $employment['work_content_categories'] ?? [];
            arsort($works);
            $topWorks = array_slice($works, 0, 5, true);
        @endphp
        @if (!empty($topWorks))
            <div style="margin-top: 4px; font-size: 9pt;">
                <strong>主な作業内容:</strong>
                @foreach ($topWorks as $cat => $cnt)
                    {{ $cat }}({{ $cnt }}回){{ !$loop->last ? '、' : '' }}
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- 就労移行 用メトリクス (transition_metrics) --}}
    @php
        $transition = is_array($strengthsSummary) ? ($strengthsSummary['transition_metrics'] ?? null) : null;
    @endphp
    @if (!empty($transition))
    <div class="section">
        <div class="section-title">
            就労移行メトリクス
            <span style="font-weight: normal; font-size: 9pt;">
                ({{ $strengthsSummary['from'] ?? '' }} 〜 {{ $strengthsSummary['to'] ?? '' }})
            </span>
        </div>
        @if (!empty($transition['practice_contents']))
            <div style="font-size: 10pt; margin-bottom: 4px;">
                <strong>実習内容:</strong>
                {{ implode('、', array_slice($transition['practice_contents'], 0, 10)) }}
            </div>
        @endif
        @if (!empty($transition['job_search_records']))
            <div style="font-size: 10pt; margin-bottom: 4px;">
                <strong>就職活動記録:</strong>
                {{ implode('、', array_slice($transition['job_search_records'], 0, 10)) }}
            </div>
        @endif
        @if ($transition['average_business_manner_score'] !== null)
            <div style="font-size: 10pt;">
                <strong>ビジネスマナー評価平均:</strong>
                {{ $transition['average_business_manner_score'] }}/5
            </div>
        @endif
    </div>
    @endif

    {{-- モニタリング明細 --}}
    @if ($details && $details->count() > 0)
    <div class="section">
        <div class="section-title">領域別評価</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">領域</th>
                    <th style="width: 12%;">達成度</th>
                    <th style="width: 40%;">コメント</th>
                    <th style="width: 33%;">次のアクション</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($details as $detail)
                <tr>
                    <td style="font-weight: bold;">{{ $detail->domain }}</td>
                    <td style="text-align: center;">{{ $detail->achievement_level ?? '未評価' }}</td>
                    <td>{!! nl2br(e($detail->comment ?: '')) !!}</td>
                    <td>{!! nl2br(e($detail->next_action ?: '')) !!}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 対象計画の支援内容との対照 --}}
    @if ($plan && $plan->details && $plan->details->count() > 0)
    <div class="section">
        <div class="section-title">計画の支援内容（参考）</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">領域</th>
                    <th style="width: 25%;">目標</th>
                    <th style="width: 35%;">支援内容</th>
                    <th style="width: 15%;">達成状況</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plan->details->sortBy('sort_order') as $planDetail)
                <tr>
                    <td>{{ $planDetail->domain }}</td>
                    <td>{!! nl2br(e($planDetail->goal ?: '')) !!}</td>
                    <td>{!! nl2br(e($planDetail->support_content ?: '')) !!}</td>
                    <td style="text-align: center;">{{ $planDetail->achievement_status ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 署名 --}}
    <table class="signature-table">
        <tr>
            <td>
                <strong>{{ $terms['service_manager'] }}：</strong>
                @if ($record->staff_signature && str_starts_with($record->staff_signature, 'data:image'))
                    <img src="{{ $record->staff_signature }}" alt="職員署名" style="max-height: 35px; max-width: 100px; vertical-align: middle;" />
                    @if ($record->staff_signer_name)
                        ({{ $record->staff_signer_name }})
                    @endif
                @else
                    {{ $record->staff_signer_name ?? $record->creator->full_name ?? '' }}
                @endif
            </td>
            <td>
                <strong>保護者確認：</strong>
                @if ($record->guardian_confirmed)
                    @if ($record->guardian_signature && str_starts_with($record->guardian_signature, 'data:image'))
                        <img src="{{ $record->guardian_signature }}" alt="保護者署名" style="max-height: 35px; max-width: 100px; vertical-align: middle;" />
                    @else
                        確認済み
                    @endif
                    （{{ $record->guardian_confirmed_at ? $record->guardian_confirmed_at->format('Y/m/d') : '' }}）
                @else
                    未確認
                @endif
            </td>
            <td style="text-align: right;">
                <strong>{{ $classroom->classroom_name ?? '' }}</strong>
            </td>
        </tr>
    </table>

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
        {{-- AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R4 (2026-05-17): V4 ハイリスク利用への対処 --}}
        <div style="margin-top: 4px; font-size: 9px; color: #666;">
            {{ \App\Services\AiPromptDirectives::medicalDisclaimerFooter() }}
        </div>
    </div>
</body>
</html>
