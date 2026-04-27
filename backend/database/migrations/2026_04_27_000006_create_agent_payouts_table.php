<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 代理店への支払い履歴。月次集計を管理する。
 *
 * 集計サイクル:
 *  - period_start / period_end: 集計対象月（4月分なら 2026-04-01 〜 2026-04-30）
 *  - due_date     : 支払期日（翌月末。4月分なら 2026-05-31）
 *  - paid_at      : 実際に銀行振込した日時（NULLなら未払い）
 *
 * 金額（最小通貨単位の整数、JPYなら円）:
 *  - gross_revenue : 紹介企業の Invoice.paid 総額（手数料計算前の売上）
 *  - stripe_fees   : 同期間の Stripe charge.balance_transaction.fee 合計
 *  - net_profit    : 利益 = gross_revenue - stripe_fees（手数料計算の基礎）
 *  - commission_amount: 代理店への支払い額 = net_profit × commission_rate
 *
 * commission_rate は集計時点での値（agents.default_commission_rate
 * または companies.commission_rate_override の加重平均）を保存する。
 *
 * status:
 *  - draft     : 集計中
 *  - finalized : 確定済み（金額fix、まだ未払い）
 *  - paid      : 支払い済み
 *  - canceled  : 取消（誤集計のリセット用）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');

            $table->unsignedBigInteger('gross_revenue')->default(0);
            $table->unsignedBigInteger('stripe_fees')->default(0);
            $table->bigInteger('net_profit')->default(0);
            $table->decimal('commission_rate', 5, 4)->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);

            $table->string('status', 32)->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->text('notes')->nullable();

            // 集計の根拠とする invoice の ID 配列（jsonb）。再現性のため。
            $table->jsonb('included_invoice_ids')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'period_start']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_payouts');
    }
};
