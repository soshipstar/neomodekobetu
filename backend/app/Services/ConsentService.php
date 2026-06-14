<?php

namespace App\Services;

use App\Exceptions\ConsentDefinitionMissingException;
use App\Models\Company;
use App\Models\ConsentDefinition;
use App\Models\ConsentRecord;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI学習基盤 同意基盤: 同意の判定と記録(企画書 §12)。
 *
 * 判定はこのサービスに一本化する。非正規化フラグ(companies/students)は高速判定用キャッシュで、
 * 正史は append-only の consent_records。記録時はフラグと履歴を同一トランザクションで更新する。
 * 既定はすべて未同意(オプトイン)。判定は fail-closed(不明なら不許可)。
 */
class ConsentService
{
    public const KEY_AGGREGATE = 'improvement_aggregate'; // 施設: Layer2統計
    public const KEY_LEARNING = 'model_learning';         // 保護者・本人: Layer3学習

    /** 施設がLayer2集計に同意しているか。 */
    public function canAggregate(Company $company): bool
    {
        return (bool) $company->ai_consent_aggregate;
    }

    /**
     * 児童の記録をLayer3学習に使ってよいか(AND条件)。
     * 施設のimprovement_aggregate かつ 児童のmodel_learning の両方が必要。
     */
    public function canUseForLearning(Student $student): bool
    {
        if (! $student->ai_consent_learning) {
            return false;
        }
        $student->loadMissing('classroom.company');
        $company = $student->classroom?->company;

        return $company !== null && (bool) $company->ai_consent_aggregate;
    }

    /** 施設の集計同意を記録(grant/revoke)。フラグと履歴を同一Txで更新。 */
    public function recordCompanyConsent(Company $company, bool $granted, ?int $userId = null, string $role = 'company_admin', string $method = 'web_ui', ?string $evidence = null, ?string $note = null): void
    {
        DB::transaction(function () use ($company, $granted, $userId, $role, $method, $evidence, $note) {
            $this->appendRecord(self::KEY_AGGREGATE, 'company', $company->id, $company->id, $granted, $userId, $role, $method, $evidence, null, $note);
            $company->update([
                'ai_consent_aggregate' => $granted,
                'ai_consent_aggregate_at' => $granted ? Carbon::now() : null,
            ]);
        });
    }

    /** 児童(保護者・本人)の学習同意を記録(grant/revoke)。$note=取得時の備考(代理記録の根拠等)。 */
    public function recordStudentConsent(Student $student, bool $granted, ?int $userId = null, string $role = 'guardian', string $method = 'web_ui', ?string $evidence = null, ?string $note = null): void
    {
        $version = $this->activeVersion(self::KEY_LEARNING);
        $student->loadMissing('classroom');
        $companyId = $student->classroom?->company_id;

        DB::transaction(function () use ($student, $granted, $userId, $role, $method, $evidence, $note, $version, $companyId) {
            $this->appendRecord(self::KEY_LEARNING, 'student', $student->id, $companyId, $granted, $userId, $role, $method, $evidence, $version, $note);
            $student->update([
                'ai_consent_learning' => $granted,
                'ai_consent_learning_at' => $granted ? Carbon::now() : null,
                'ai_consent_learning_version' => $granted ? $version : null,
            ]);
        });
    }

    /** consent_records へ1行追記する(append-only)。 */
    private function appendRecord(string $key, string $subjectType, int $subjectId, ?int $companyId, bool $granted, ?int $userId, string $role, string $method, ?string $evidence, ?int $version = null, ?string $note = null): void
    {
        $version ??= $this->activeVersion($key);
        if ($version === null) {
            // 同意定義(consent_definitions)が未投入。version/定義IDがNULLの granted レコードは
            // 「どの文面版に同意したか」を後から立証できず、append-only のため修正不可。
            if ($granted) {
                // fail-closed: 壊れた「同意」を積まない。Tx ごとロールバックし、利用者に対処を促す。
                throw new ConsentDefinitionMissingException($key);
            }
            // 撤回(revoke)は本人の権利として常に通す。定義欠落でもブロックしないが、検知用に警告を残す。
            Log::warning("ConsentService: consent_definitions 未登録のまま撤回を記録します(consent_key={$key})。ConsentDefinitionSeeder の投入を確認してください。");
        }
        ConsentRecord::create([
            'consent_definition_id' => $this->definitionId($key, $version),
            'consent_key' => $key,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'company_id' => $companyId,
            'state' => $granted ? 'granted' : 'revoked',
            'version' => $version,
            'granted_by_user_id' => $userId,
            'granted_by_role' => $role,
            'acquisition_method' => $method,
            'evidence_ref' => $evidence,
            'acquired_at' => Carbon::now(),
            'effective_from' => Carbon::now(),
            'note' => $note,
        ]);
    }

    private function activeVersion(string $key): ?int
    {
        return ConsentDefinition::where('consent_key', $key)->where('is_active', true)
            ->max('version');
    }

    private function definitionId(string $key, ?int $version): ?int
    {
        if ($version === null) {
            return null;
        }

        return ConsentDefinition::where('consent_key', $key)->where('version', $version)->value('id');
    }
}
