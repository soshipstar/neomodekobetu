<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>個別支援計画書 - {{ $student->student_name ?? $plan->student_name }}</title>
    <style>

        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'IPA Gothic', 'IPAGothic', 'Noto Sans JP', sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            color: #333;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        /* ヘッダー */
        .header {
            text-align: center;
            margin-bottom: 6px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 4px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: 700;
            margin: 0;
            letter-spacing: 2pt;
        }

        /* メタ情報 */
        .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 8pt;
        }

        .meta-label {
            font-weight: bold;
        }

        /* 二列レイアウト */
        .two-col {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
        }

        .two-col > div {
            flex: 1;
        }

        /* セクション */
        .section-head {
            background: #4a5568;
            color: white;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 8pt;
        }

        .section-body {
            padding: 4px 6px;
            border: 1px solid #999;
            border-top: none;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 7.5pt;
            line-height: 1.3;
            min-height: 30px;
        }

        /* 目標 */
        .goal-date {
            font-size: 7pt;
            padding: 1px 6px;
        }

        /* 支援内容テーブル */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            table-layout: fixed;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #555;
            padding: 2px 4px;
            text-align: left;
            vertical-align: top;
        }

        .details-table th {
            background: #e2e8f0;
            font-weight: bold;
            text-align: center;
            font-size: 7pt;
        }

        .details-table td {
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.3;
            overflow: hidden;
        }

        .cat-honin { background: #f7fafc; }
        .cat-kazoku { background: #dbeafe; }
        .cat-chiiki { background: #d1fae5; }

        /* 署名欄 */
        .signature-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px solid #333;
        }

        .sig-center {
            display: flex;
            gap: 20px;
            flex: 1;
            justify-content: center;
            align-items: center;
        }

        .sig-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sig-label {
            font-weight: bold;
            font-size: 7.5pt;
            white-space: nowrap;
        }

        .sig-content {
            display: flex;
            align-items: center;
            gap: 4px;
            border-bottom: 1px solid #333;
            min-width: 120px;
            padding: 2px 4px;
        }

        .sig-img {
            max-height: 35px;
            max-width: 100px;
            vertical-align: middle;
        }

        .sig-name {
            font-size: 7.5pt;
        }

        .issuer {
            text-align: right;
            min-width: 180px;
        }

        .issuer-name {
            font-size: 9pt;
            font-weight: bold;
        }

        .issuer-details {
            font-size: 7pt;
            color: #333;
        }

        .footer {
            margin-top: 4px;
            text-align: right;
            font-size: 6pt;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>個別支援計画書</h1>
    </div>

    {{-- メタ情報 --}}
    <div class="meta">
        <div>
            <span class="meta-label">児童氏名：</span>
            {{ $student->student_name ?? $plan->student_name }}
        </div>
        <div>
            <span class="meta-label">同意日：</span>
            {{ $plan->consent_date ? $plan->consent_date->format('Y年m月d日') : '' }}
        </div>
    </div>

    {{-- 意向と方針（2列） --}}
    <div class="two-col">
        <div>
            <div class="section-head">利用児及び家族の生活に対する意向</div>
            <div class="section-body">{{ $plan->life_intention }}</div>
        </div>
        <div>
            <div class="section-head">総合的な支援の方針</div>
            <div class="section-body">{{ $plan->overall_policy }}</div>
        </div>
    </div>

    {{-- 長期目標と短期目標（2列） --}}
    <div class="two-col">
        <div>
            <div class="section-head">長期目標</div>
            @if ($plan->long_term_goal_date)
                <div class="goal-date">達成時期: {{ $plan->long_term_goal_date->format('Y年m月d日') }}</div>
            @endif
            <div class="section-body">{{ $plan->long_term_goal }}</div>
        </div>
        <div>
            <div class="section-head">短期目標</div>
            @if ($plan->short_term_goal_date)
                <div class="goal-date">達成時期: {{ $plan->short_term_goal_date->format('Y年m月d日') }}</div>
            @endif
            <div class="section-body">{{ $plan->short_term_goal }}</div>
        </div>
    </div>

    {{-- 支援内容明細 --}}
    <div class="section-head" style="margin-bottom: 0;">○支援目標及び具体的な支援内容等</div>
    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 9%;">項目</th>
                <th style="width: 17%;">支援目標<br><span style="font-size: 6pt; font-weight: normal;">（具体的な到達目標）</span></th>
                <th style="width: 30%;">支援内容<br><span style="font-size: 6pt; font-weight: normal;">（内容・5領域との関連性等）</span></th>
                <th style="width: 7%;">達成時期</th>
                <th style="width: 10%;">担当者</th>
                <th style="width: 20%;">留意事項</th>
                <th style="width: 5%;">優先</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($details as $detail)
            @php
                $cat = $detail->category ?? '';
                $catClass = str_contains($cat, '家族') ? 'cat-kazoku' : (str_contains($cat, '地域') ? 'cat-chiiki' : 'cat-honin');
            @endphp
            <tr class="{{ $catClass }}">
                <td style="font-size: 6.5pt;">
                    @if ($cat)<div style="font-weight: bold;">{{ $cat }}</div>@endif
                    {{ $detail->sub_category ?? $detail->domain ?? '' }}
                </td>
                <td>{!! nl2br(e($detail->support_goal ?? $detail->goal ?? '')) !!}</td>
                <td>{!! nl2br(e($detail->support_content ?? '')) !!}</td>
                <td style="text-align: center; font-size: 6.5pt;">
                    @if ($detail->achievement_date)
                        {{ \Carbon\Carbon::parse($detail->achievement_date)->format('Y/m/d') }}
                    @endif
                </td>
                <td style="font-size: 6.5pt;">{!! nl2br(e($detail->staff_organization ?? '')) !!}</td>
                <td>{!! nl2br(e($detail->notes ?? '')) !!}</td>
                <td style="text-align: center;">{{ $detail->priority ?? '' }}</td>
            </tr>
            @endforeach
            @if ($details->isEmpty())
            <tr>
                <td colspan="7" style="text-align: center; color: #999;">支援内容が登録されていません</td>
            </tr>
            @endif
        </tbody>
    </table>
    <p style="font-size: 5.5pt; color: #777; margin: 0 0 4px 0;">
        ※ 5領域の視点：「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」
    </p>

    {{-- 署名欄 --}}
    <div class="signature-row">
        <div class="sig-center">
            <div class="sig-item">
                <div class="sig-label">児童発達支援管理責任者</div>
                <div class="sig-content">
                @php $staffSig = $plan->staff_signature_image ?: $plan->staff_signature; @endphp
                @if ($staffSig && str_starts_with($staffSig, 'data:image'))
                    <img src="{{ $staffSig }}" alt="職員署名" class="sig-img" />
                    @if ($plan->staff_signer_name) <span class="sig-name">({{ $plan->staff_signer_name }})</span> @endif
                @else
                    <span class="sig-name">{{ $plan->staff_signer_name ?? $plan->manager_name ?? '' }}</span>
                @endif
                </div>
            </div>
            <div class="sig-item">
                <div class="sig-label">保護者署名</div>
                <div class="sig-content">
                @php $guardSig = $plan->guardian_signature_image ?: $plan->guardian_signature; @endphp
                @if ($guardSig && str_starts_with($guardSig, 'data:image'))
                    <img src="{{ $guardSig }}" alt="保護者署名" class="sig-img" />
                @else
                    <span class="sig-name">{{ $guardSig ?? '' }}</span>
                @endif
                </div>
            </div>
        </div>
        @if ($classroom)
        <div class="issuer">
            <div class="issuer-name">{{ $classroom->classroom_name ?? '' }}</div>
            <div class="issuer-details">
                @if ($classroom->address)
                    〒{{ $classroom->address }}
                @endif
                @if ($classroom->phone)
                    <br>TEL: {{ $classroom->phone }}
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="footer">
        出力日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
