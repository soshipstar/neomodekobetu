<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価システム P0: 評価マスタ(ものさし)テーブル群。
 *
 * 放課後等デイサービス向け「発達段階別/高卒標準・発展 能力評価」体系のマスタ。
 * docs/評価表/「能力評価データベース(マスタ・記録様式).xlsx」を正本として取り込む。
 * 文科省学習指導要領・特別支援学校自立活動・放デイガイドライン等の公的枠組みに基づく
 * 共通の評価基準であり、全事業所(company)共通の参照マスタとする(company_id を持たない)。
 *
 * 構成: 評価ツール4 / 軸12 / 項目80 / 到達目安246 / 評価基準11(0〜10) /
 *       支援コード7(SUP0〜6) / 才能サイン14 / 才能観察課題14 / 才能判定基準56。
 * 日々の観察記録(T_観察記録)・評価スコア(T_評価スコア)は後続フェーズ(P2/P3)で追加する。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        // M_評価ツール: DEV(発達段階別)/ADV(高卒標準・発展)/WRK(就業)/UNV(大学研究)
        Schema::create('ability_eval_tools', function (Blueprint $table) {
            $table->string('tool_id', 8)->primary()->comment('DEV/ADV/WRK/UNV');
            $table->string('name')->comment('評価ツール名');
            $table->string('target')->nullable()->comment('使う対象児童');
            $table->string('axis_type', 20)->nullable()->comment('成長段階/到達水準/時期');
            $table->timestamps();
        });

        // M_軸: S1〜S6(成長段階)/L1〜L4(到達水準)/P1〜P2(時期)
        Schema::create('ability_eval_axes', function (Blueprint $table) {
            $table->string('axis_id', 8)->primary()->comment('S1〜S6, L1〜L4, P1〜P2');
            $table->string('axis_type', 20)->comment('成長段階/到達水準/時期');
            $table->string('name')->comment('軸の表示名');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // M_項目: 例 DEV-1-1, ADV-A-1, WRK-J-1, UNV-U-1
        Schema::create('ability_eval_items', function (Blueprint $table) {
            $table->string('item_id', 20)->primary()->comment('例: DEV-1-1');
            $table->string('tool_id', 8)->comment('M_評価ツール');
            $table->string('domain')->comment('領域(5領域/4領域/分類)');
            $table->string('name')->comment('達成すべき目標(能力項目)');
            $table->text('definition')->nullable()->comment('この項目で見る力');
            $table->text('perspective')->nullable()->comment('身についたと判断する観点・行動指標');
            $table->text('source')->nullable()->comment('根拠となる公的枠組み(出典)');
            $table->timestamps();

            $table->foreign('tool_id')->references('tool_id')->on('ability_eval_tools')->cascadeOnDelete();
            $table->index('tool_id');
            $table->index('domain');
        });

        // M_到達目安: (項目ID×軸ID) で「この姿が安定して見られれば到達」基準
        Schema::create('ability_eval_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 20);
            $table->string('axis_id', 8);
            $table->text('benchmark')->comment('到達目安');
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('ability_eval_items')->cascadeOnDelete();
            $table->foreign('axis_id')->references('axis_id')->on('ability_eval_axes')->cascadeOnDelete();
            $table->unique(['item_id', 'axis_id']);
        });

        // M_評価基準: 0〜10点の判定基準
        Schema::create('ability_eval_score_criteria', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->primary()->comment('0〜10');
            $table->string('name')->comment('短い名前');
            $table->text('criteria')->nullable()->comment('これが確認できたらこの点');
            $table->text('guardian_words')->nullable()->comment('保護者向けのことば');
            $table->text('example')->nullable()->comment('具体例(朝の着替え)');
            $table->text('evidence')->nullable()->comment('点数の根拠となる記録');
            $table->timestamps();
        });

        // M_支援コード: SUP0〜SUP6(提供した支援の種類)
        Schema::create('ability_support_codes', function (Blueprint $table) {
            $table->string('code', 8)->primary()->comment('SUP0〜SUP6');
            $table->string('content')->comment('支援内容');
            $table->string('score_band')->nullable()->comment('この支援で成功した場合の点数の目安');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // M_才能サイン: TAL-01〜(特性を生かした強みの兆候)
        Schema::create('ability_talent_signs', function (Blueprint $table) {
            $table->string('sign_id', 10)->primary()->comment('TAL-01〜');
            $table->string('strength')->comment('強みの名称');
            $table->text('sign')->nullable()->comment('観察される行動例');
            $table->text('grow_activities')->nullable()->comment('伸ばす活動例');
            $table->text('careers')->nullable()->comment('活かせる進路例');
            $table->string('related_item_id', 20)->nullable()->comment('関連項目ID(D領域等・ソフト参照)');
            $table->timestamps();
        });

        // M_才能観察課題: 才能サインの活動内チェック方法(1サイン=1行)
        Schema::create('ability_talent_observation_tasks', function (Blueprint $table) {
            $table->string('sign_id', 10)->primary();
            $table->string('strength')->comment('強みの名称');
            $table->text('method')->nullable()->comment('活動内チェック方法(観察課題)');
            $table->text('notes')->nullable()->comment('留意点');
            $table->timestamps();

            $table->foreign('sign_id')->references('sign_id')->on('ability_talent_signs')->cascadeOnDelete();
        });

        // M_才能判定基準: 才能サイン×水準(1〜4)の判定基準
        Schema::create('ability_talent_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('sign_id', 10);
            $table->unsignedTinyInteger('level')->comment('水準 1〜4');
            $table->string('level_name')->nullable()->comment('兆候/明確な強み/顕著/卓越');
            $table->text('criteria')->nullable()->comment('この姿が確認できたらこの水準');
            $table->timestamps();

            $table->foreign('sign_id')->references('sign_id')->on('ability_talent_signs')->cascadeOnDelete();
            $table->unique(['sign_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_talent_criteria');
        Schema::dropIfExists('ability_talent_observation_tasks');
        Schema::dropIfExists('ability_talent_signs');
        Schema::dropIfExists('ability_support_codes');
        Schema::dropIfExists('ability_eval_score_criteria');
        Schema::dropIfExists('ability_eval_benchmarks');
        Schema::dropIfExists('ability_eval_items');
        Schema::dropIfExists('ability_eval_axes');
        Schema::dropIfExists('ability_eval_tools');
    }
};
