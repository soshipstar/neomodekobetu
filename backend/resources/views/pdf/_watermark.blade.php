{{--
    PDF ウォーターマーク 共通パーシャル

    用途:
      流出/不正コピー時の発信元特定 + 心理的抑止。
      全 PDF テンプレートの末尾で @include('pdf._watermark') する。

    表示内容:
      - 発行事業所名 (classroom_name) - 任意で渡す
      - 発行日時 (now)
      - 発行ユーザー ID (auth()->id())  ← 流出ログ照合の決め手
      - 発行アプリ識別子 (kiduri.xyz)

    レイアウト:
      A4 の左下隅に小さな灰色フォントで配置。本文には干渉しない。
      position: fixed なので Puppeteer の各ページに同じものが出る
      (1 ページ目だけでなく全ページに刻印したい設計)。

    入力変数:
      $classroom (optional) - Classroom モデル または ['classroom_name' => '...']
                              指定があれば classroom_name を表示
--}}
<style>
    .__kiduri_watermark {
        position: fixed;
        bottom: 4mm;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 6.5pt;
        color: #aaa;
        font-family: 'IPA Gothic', 'IPAGothic', 'Noto Sans JP', sans-serif;
        letter-spacing: 0.3pt;
        z-index: 9999;
        pointer-events: none;
    }
    .__kiduri_watermark .sep { color: #ccc; margin: 0 5pt; }
</style>
<div class="__kiduri_watermark">
    {{-- 流出特定用ハッシュ: 発行ユーザー ID + 発行時刻 (秒精度) --}}
    @php
        $__wm_uid = optional(auth()->user())->id ?? 'anon';
        $__wm_time = now()->format('Y-m-d H:i:s');
        // 教室名を Blade の共通変数候補から自動で拾う:
        //  $classroom (Model or array) / $classroom_name (string) / $classroomName /
        //  ネストして $plan->classroom / $newsletter->classroom など
        $__wm_cls = '';
        try {
            if (isset($classroom_name) && is_string($classroom_name))       $__wm_cls = $classroom_name;
            elseif (isset($classroomName) && is_string($classroomName))     $__wm_cls = $classroomName;
            elseif (isset($classroom)) {
                if (is_object($classroom) && isset($classroom->classroom_name)) $__wm_cls = $classroom->classroom_name;
                elseif (is_array($classroom) && isset($classroom['classroom_name'])) $__wm_cls = $classroom['classroom_name'];
            }
        } catch (\Throwable $e) { /* watermark の取得失敗で本文を壊さない */ }
    @endphp
    kiduri.xyz
    @if($__wm_cls)
        <span class="sep">|</span>{{ $__wm_cls }}
    @endif
    <span class="sep">|</span>発行 {{ $__wm_time }}
    <span class="sep">|</span>uid:{{ $__wm_uid }}
</div>
