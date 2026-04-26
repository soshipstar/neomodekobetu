<?php

namespace App\Services\Billing;

use App\Models\Company;

/**
 * 企業管理者向けのレスポンスに「マスター管理者の表示制御」を適用する。
 *
 * 重要: クライアント側で hide するのではなく、APIレスポンスから項目そのものを
 * 除外する。「隠したつもりが DevTools で見えていた」事故を防ぐため。
 *
 * display_settings の構造例:
 *   {
 *     "plan_label": "Acme特別プラン",      // プラン名の上書き
 *     "show_amount": true,                  // 金額を見せるか（false なら "—"）
 *     "show_breakdown": false,              // 内訳明細
 *     "show_next_billing_date": true,       // 次回請求日
 *     "show_invoice_history": "all",        // all|last_12_months|hidden
 *     "allow_invoice_download": true,
 *     "allow_payment_method_edit": true,
 *     "allow_self_cancel": false,
 *     "announcement": { "level": "info", "title": "...", "body": "...", "shown_until": "2026-12-31" },
 *     "support_contact": { "name": "...", "email": "...", "phone": "..." }
 *   }
 *
 * 既定: display_settings が NULL/空 の場合は「全項目表示・全操作許可」とする。
 * これは「最小限表示」だと運用負荷が高い（全企業に毎回設定が必要）ため。
 */
class DisplaySettingsFilter
{
    private const DEFAULTS = [
        'plan_label' => null,
        'show_amount' => true,
        'show_breakdown' => true,
        'show_next_billing_date' => true,
        'show_invoice_history' => 'all',
        'allow_invoice_download' => true,
        'allow_payment_method_edit' => true,
        'allow_self_cancel' => false,
        'announcement' => null,
        'support_contact' => null,
    ];

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function settingsFor(Company $company): array
    {
        return array_replace(self::DEFAULTS, $company->display_settings ?? []);
    }

    /**
     * 企業管理者向けに subscription レスポンスをフィルタする。
     *
     * @param  array<string, mixed>  $payload  subscription/billing 情報
     */
    public static function applyToSubscription(Company $company, array $payload): array
    {
        $settings = self::settingsFor($company);

        if (!$settings['show_amount']) {
            unset($payload['amount'], $payload['custom_amount'], $payload['unit_amount']);
        }

        if (!$settings['show_breakdown']) {
            unset($payload['breakdown'], $payload['line_items']);
        }

        if (!$settings['show_next_billing_date']) {
            unset($payload['current_period_end'], $payload['next_billing_at']);
        }

        if (!$settings['allow_self_cancel']) {
            $payload['can_cancel'] = false;
        } else {
            $payload['can_cancel'] = $payload['can_cancel'] ?? true;
        }

        if (!$settings['allow_payment_method_edit']) {
            $payload['can_edit_payment_method'] = false;
        } else {
            $payload['can_edit_payment_method'] = $payload['can_edit_payment_method'] ?? true;
        }

        if ($settings['plan_label']) {
            $payload['plan_label'] = $settings['plan_label'];
        }

        $payload['announcement'] = self::activeAnnouncement($settings);
        $payload['support_contact'] = $settings['support_contact'];

        return $payload;
    }

    /**
     * 請求履歴の表示範囲を判定（クエリビルダで使う）。
     *
     * @return array{enabled: bool, since: ?\Carbon\CarbonInterface}
     */
    public static function invoiceHistoryScope(Company $company): array
    {
        $settings = self::settingsFor($company);
        $mode = $settings['show_invoice_history'];

        return match ($mode) {
            'hidden' => ['enabled' => false, 'since' => null],
            'last_12_months' => ['enabled' => true, 'since' => now()->subMonths(12)],
            default => ['enabled' => true, 'since' => null],
        };
    }

    public static function canDownloadInvoice(Company $company): bool
    {
        return self::settingsFor($company)['allow_invoice_download'];
    }

    public static function canSelfCancel(Company $company): bool
    {
        return self::settingsFor($company)['allow_self_cancel'];
    }

    public static function canEditPaymentMethod(Company $company): bool
    {
        return self::settingsFor($company)['allow_payment_method_edit'];
    }

    /**
     * shown_until が過去なら表示しない。
     */
    private static function activeAnnouncement(array $settings): ?array
    {
        $a = $settings['announcement'] ?? null;
        if (!$a) {
            return null;
        }
        if (!empty($a['shown_until'])) {
            try {
                if (now()->greaterThan($a['shown_until'])) {
                    return null;
                }
            } catch (\Throwable $e) {
                // パース失敗時は表示する側に倒す
            }
        }
        return $a;
    }
}
