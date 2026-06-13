<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習知識基盤 S0: 統一スキーマ(正典)。
 *
 * 企画書 docs/kiduri-AI学習基盤_企画書.md §10 に基づく単一ER。
 * 「AI生成文(before) → 人間修正(after) → 差分 → 修正理由」をセクション単位で蓄積する。
 * すべて新規・追記専用(append-only)テーブルで、既存テーブルは一切変更しない(後方互換)。
 *
 * PIIレイヤ(鉄則):
 *  - ai_generation_events.generated_payload = マスク済(OpenAIへ送ったものの写し)
 *  - ai_revision_events.before_text/after_text = 実名(Layer1原本。モデルで encrypted cast)
 *  - Layer2集計/Layer3学習(learning_corpus等)は後続フェーズ(S5/S8)で追加
 *
 * 本マイグレーションはテーブル作成のみ。フック挿入・同意ゲートは後続(S1/S2)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        // 生成イベント(before の源泉。マスク済payloadを保存) ------------------------------
        Schema::create('ai_generation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_generation_log_id')->nullable()
                ->constrained('ai_generation_logs')->nullOnDelete()
                ->comment('既存生成ログへの後付けリンク');
            $table->string('document_type', 40)->comment('support_plan/monitoring/assessment_staff/assessment_guardian/integrated_note/ability_eval');
            $table->unsignedBigInteger('document_id')->nullable()->comment('適用先の元PK(生成直後はnull可)');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('生成を起動した職員');
            $table->string('generation_type', 50)->nullable()->comment('既存 AiGenerationLog.generation_type と同値');
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 64)->nullable()->comment('sha1(system_prompt)等。プロンプト版の識別');
            $table->jsonb('sources_used')->nullable()->comment('{assessment_period_id, monitoring_record_id, prev_plan_id, record_count} 等');
            $table->jsonb('generated_payload')->nullable()->comment('AI生出力(section_key→text)。★マスク済で保存');
            $table->boolean('pii_masked')->default(true);
            $table->timestampTz('generated_at')->useCurrent();

            $table->index(['document_type', 'document_id']);
            $table->index('student_id');
            $table->index('generation_type');
        });

        // 人間修正イベント(after。Layer1原本=実名。セクション単位。学習の主単位) ----------
        Schema::create('ai_revision_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->string('section_key', 128)->comment('long_term_goal / detail:health_life / overall_comment 等');
            $table->foreignId('ai_generation_event_id')->nullable()
                ->constrained('ai_generation_events')->nullOnDelete();
            // ★実名を含む。モデルで encrypted cast(保存時暗号化)。長くなるため text。
            $table->text('before_text')->nullable()->comment('AI原案(該当セクション)。暗号化保存');
            $table->text('after_text')->nullable()->comment('人間最終(該当セクション)。暗号化保存');
            $table->jsonb('diff')->nullable()->comment('構造化差分 {algo, ops:[{op,text}]}');
            $table->float('change_ratio')->nullable()->comment('0.0(無変更)〜1.0(全置換)');
            $table->boolean('changed')->default(true);
            $table->string('edit_kind', 30)->nullable()->comment('save_draft/submit/publish/revised_draft/official');
            $table->foreignId('editor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('editor_role', 20)->nullable()->comment('staff/guardian/ai_revision');
            $table->string('sensitivity', 16)->default('raw')->comment('raw(実名)/pseudonymized');
            $table->timestampTz('created_at')->useCurrent(); // append-only

            $table->index(['document_type', 'document_id', 'section_key']);
            $table->index('student_id');
            $table->index('ai_generation_event_id');
            $table->index('changed');
        });

        // 修正理由カテゴリ(固定11 + 動的追加。チップ) --------------------------------------
        Schema::create('ai_edit_reason_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->comment('安定キー: too_abstract 等');
            $table->string('label_ja', 100)->comment('チップ表示名');
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('company_id')->nullable()->comment('NULL=全社共通(固定/横断昇格), 値=その法人内のみ');
            $table->boolean('is_seeded')->default(false)->comment('初期分類(削除/code変更不可)');
            $table->string('status', 16)->default('active')->comment('active/archived');
            $table->unsignedInteger('sort_order')->default(100);
            $table->unsignedInteger('usage_count')->default(0)->comment('集計キャッシュ(並び順用)');
            $table->jsonb('centroid_meta')->nullable()->comment('代表埋め込みの統計(本体はvector_embeddings)');
            $table->unsignedBigInteger('promoted_from_candidate_id')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status', 'sort_order']);
        });

        // 修正理由(イベント×理由。チップ+自由記述) ----------------------------------------
        Schema::create('ai_edit_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_revision_event_id')->constrained('ai_revision_events')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('ai_edit_reason_categories')->nullOnDelete();
            $table->text('free_text')->nullable()->comment('自由記述(任意)。外部送信前はマスク必須');
            $table->string('reason_source', 30)->default('human_manual')
                ->comment('human_manual/ai_annotation/guardian_comment/meeting_minutes/monitoring_data');
            $table->jsonb('source_ref')->nullable()->comment('由来の具体(annotation_type, guardian_comment_excerpt 等)');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent(); // append-only

            $table->index('ai_revision_event_id');
            $table->index('category_id');
            $table->index('reason_source');
        });

        // 新カテゴリ候補(昇格待ち) ----------------------------------------------------------
        Schema::create('ai_edit_reason_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('normalized_text', 255)->comment('正規化後の代表フレーズ');
            $table->jsonb('member_texts')->nullable()->comment('束ねた自由記述サンプル(マスク済)');
            $table->unsignedInteger('frequency')->default(1);
            $table->unsignedInteger('distinct_users')->default(1)->comment('関与ユーザー数(1人の口癖を弾く)');
            $table->float('nearest_category_sim')->nullable();
            $table->string('status', 16)->default('pending')->comment('pending/approved/rejected/merged');
            $table->unsignedBigInteger('merged_into_category_id')->nullable();
            $table->jsonb('detection_meta')->nullable()->comment('判定方式/しきい値/モデル等の根拠');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_edit_reason_candidates');
        Schema::dropIfExists('ai_edit_reasons');
        Schema::dropIfExists('ai_edit_reason_categories');
        Schema::dropIfExists('ai_revision_events');
        Schema::dropIfExists('ai_generation_events');
    }
};
