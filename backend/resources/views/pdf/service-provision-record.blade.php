<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>サービス提供実績記録票 {{ $year_month }}</title>
<style>
  @page { size: A4 portrait; margin: 12mm 10mm; }
  body { font-family: 'IPAGothic', 'Noto Sans CJK JP', sans-serif; font-size: 9pt; color: #222; }
  h1   { font-size: 14pt; text-align: center; margin: 0 0 4px; }
  .sub { font-size: 9pt; text-align: center; color: #555; margin-bottom: 10px; }
  table.head { width: 100%; border: 1px solid #444; border-collapse: collapse; margin-bottom: 8px; }
  table.head td { border: 1px solid #888; padding: 3px 6px; }
  table.head .lbl { background: #e8e8e8; font-weight: bold; width: 18%; }
  table.calendar { width: 100%; border-collapse: collapse; font-size: 8pt; }
  table.calendar th, table.calendar td { border: 1px solid #888; padding: 2px; text-align: center; }
  table.calendar th { background: #e8e8e8; font-weight: bold; }
  table.calendar td.day { width: 3%; }
  table.calendar td.holiday { background: #fdecec; }
  table.calendar td.absent { background: #f8f8f8; color: #aaa; }
  .summary { margin-top: 8px; width: 100%; border-collapse: collapse; }
  .summary th, .summary td { border: 1px solid #888; padding: 4px 6px; }
  .summary th { background: #e8e8e8; text-align: left; width: 22%; }
  .signature { margin-top: 12px; display: flex; justify-content: space-between; gap: 10px; }
  .signature .box { flex: 1; border: 1px solid #888; padding: 6px; min-height: 36px; }
  .footer { margin-top: 8px; font-size: 7pt; color: #888; }
</style>
</head>
<body>

<h1>サービス提供実績記録票</h1>
<p class="sub">{{ $year_month }} ({{ $service_label }})</p>

<table class="head">
  <tr>
    <td class="lbl">事業所名</td><td>{{ $classroom_name }}</td>
    <td class="lbl">サービス種別</td><td>{{ $service_label }} ({{ $service_code }})</td>
  </tr>
  <tr>
    <td class="lbl">利用者氏名</td><td>{{ $student_name }}</td>
    <td class="lbl">受給者証番号</td><td>{{ $beneficiary_number }}</td>
  </tr>
  <tr>
    <td class="lbl">支給市町村</td><td>{{ $municipality_code }}</td>
    <td class="lbl">障害支援区分</td><td>{{ $disability_grade }}</td>
  </tr>
</table>

<table class="calendar">
  <thead>
    <tr>
      @for ($d = 1; $d <= $days_in_month; $d++)
        <th class="day">{{ $d }}</th>
      @endfor
    </tr>
  </thead>
  <tbody>
    <tr>
      @foreach ($daily_marks as $mark)
        @if ($mark['attended'])
          <td>○</td>
        @else
          <td class="absent">-</td>
        @endif
      @endforeach
    </tr>
  </tbody>
</table>

<table class="summary">
  <tr>
    <th>利用日数</th><td>{{ $usage_days }} 日</td>
    <th>合計単位数</th><td>{{ number_format($total_units) }}</td>
  </tr>
  <tr>
    <th>総費用</th><td>¥{{ number_format($total_amount) }}</td>
    <th>1単位単価</th><td>¥{{ number_format($unit_price, 2) }}</td>
  </tr>
  <tr>
    <th>公費負担額</th><td>¥{{ number_format($public_share) }}</td>
    <th>利用者負担額</th><td>¥{{ number_format($user_copay) }}</td>
  </tr>
  <tr>
    <th>月額負担上限額</th><td>{{ $monthly_copay_cap > 0 ? '¥' . number_format($monthly_copay_cap) : '-' }}</td>
    <th>上限管理事業所</th><td>{{ $copay_management_provider ?? '-' }}</td>
  </tr>
</table>

<div class="signature">
  <div class="box">
    <div style="font-size: 8pt; color: #666;">利用者・家族 署名</div>
  </div>
  <div class="box">
    <div style="font-size: 8pt; color: #666;">事業所代表者 署名 / 印</div>
  </div>
</div>

<p class="footer">
  ※ 本書は当事業所が作成した提供実績記録です。記載内容に相違がないことを確認のうえ署名をお願いします。
</p>

</body>
</html>
