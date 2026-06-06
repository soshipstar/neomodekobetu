<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ユーザーの規約・AI利用方針への同意レコード。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 への準拠目的で
 * 規約バージョンごとの個別同意を記録する。
 * (2026-05-17 — R3a 着手)
 */
class UserConsent extends Model
{
    protected $fillable = [
        'user_id',
        'consent_type',
        'version',
        'student_id',
        'granted',
        'granted_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'granted'     => 'boolean',
            'granted_at'  => 'datetime',
            'revoked_at'  => 'datetime',
        ];
    }

    // -----------------------------------------------------------------------
    // 規約 type の定数。新規同意項目を追加するときは、ここに定数を増やし、
    // CURRENT_VERSIONS にバージョンを並べて指定する。
    // -----------------------------------------------------------------------

    public const TYPE_PRIVACY_POLICY  = 'privacy_policy';
    public const TYPE_TERMS           = 'terms';
    public const TYPE_AI_USAGE        = 'ai_usage';
    public const TYPE_CHILD_AI        = 'child_ai_consent';

    /**
     * 現行バージョン一覧。規約改定時にここを上げると、
     * ConsentRequiredGate (FE) が新バージョンへの再同意を要求する。
     */
    public const CURRENT_VERSIONS = [
        self::TYPE_PRIVACY_POLICY => 'v1.0',
        self::TYPE_TERMS          => 'v1.0',
        self::TYPE_AI_USAGE       => 'v1.0',
        self::TYPE_CHILD_AI       => 'v1.0',
    ];

    /**
     * 全 user_type が同意すべき基本セット。
     * child_ai_consent は guardian のみが対象なので別扱い。
     */
    public const BASE_REQUIRED_FOR_ALL = [
        self::TYPE_PRIVACY_POLICY,
        self::TYPE_TERMS,
    ];

    /**
     * staff / admin が AI 機能を使うために必要な同意セット。
     */
    public const REQUIRED_FOR_STAFF_AI = [
        self::TYPE_PRIVACY_POLICY,
        self::TYPE_TERMS,
        self::TYPE_AI_USAGE,
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Student, self> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // -----------------------------------------------------------------------
    // Static helpers
    // -----------------------------------------------------------------------

    /**
     * 指定ユーザーが、指定タイプの「現行バージョン」に対して
     * 有効な同意を持っているか判定する。
     */
    public static function hasActiveConsent(int $userId, string $type, ?int $studentId = null): bool
    {
        $version = self::CURRENT_VERSIONS[$type] ?? null;
        if ($version === null) return false;

        $q = self::where('user_id', $userId)
            ->where('consent_type', $type)
            ->where('version', $version)
            ->where('granted', true)
            ->whereNull('revoked_at');

        if ($studentId !== null) {
            $q->where('student_id', $studentId);
        }

        return $q->exists();
    }

    /**
     * 指定ユーザーが必要な同意を全て取得済みか。
     */
    public static function hasAllConsents(int $userId, array $types): bool
    {
        foreach ($types as $type) {
            if (! self::hasActiveConsent($userId, $type)) return false;
        }
        return true;
    }
}
