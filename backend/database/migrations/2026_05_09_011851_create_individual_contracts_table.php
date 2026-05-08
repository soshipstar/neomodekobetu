<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 個別契約書 (3者間: 株式会社ソーシップ × 代理店 × 顧客企業) を保管する。
 *
 * 採用した仮定 (要件確認なしで進めた):
 *  - 顧客 = 既存 companies テーブル (事業所) と紐付け
 *  - 1代理店 × 1顧客 = 1契約書 (unique constraint)
 *  - 3者の署名状態を個別に bool で保持し、PDF (押印済) は contract_document_path にアップロード
 *
 * 認可ルール:
 *  - 代理店ユーザー: 自代理店分のみ CRUD 可能 (agent_id = user.agent_id)
 *  - マスター管理者: 全件閲覧/編集可能
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // 契約メタ情報
            $table->date('contract_date')->nullable()->comment('契約日');
            $table->date('start_date')->nullable()->comment('契約開始日');
            $table->date('end_date')->nullable()->comment('契約終了日 (無期限なら null)');
            $table->text('terms')->nullable()->comment('特約条項・備考');
            $table->integer('monthly_fee')->nullable()->comment('月額料金 (円, 税抜)');
            $table->decimal('commission_rate', 5, 4)->nullable()->comment('この契約固有の手数料率 (0.0000 〜 1.0000)');

            // 3者の署名状態
            $table->boolean('soship_signed')->default(false)->comment('株式会社ソーシップ署名済');
            $table->timestampTz('soship_signed_at')->nullable();
            $table->boolean('agent_signed')->default(false)->comment('代理店署名済');
            $table->timestampTz('agent_signed_at')->nullable();
            $table->boolean('customer_signed')->default(false)->comment('顧客企業署名済');
            $table->timestampTz('customer_signed_at')->nullable();

            // 押印済PDF (3者全員サイン後)
            $table->string('contract_document_path', 500)->nullable();

            // 作成・更新者
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['agent_id']);
            $table->index(['company_id']);
            // 1代理店 × 1顧客 = 1契約 のユニーク制約
            $table->unique(['agent_id', 'company_id'], 'individual_contracts_agent_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_contracts');
    }
};
