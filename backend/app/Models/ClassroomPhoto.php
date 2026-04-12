<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * 事業所写真ライブラリのモデル。
 * 本体ファイルはストレージに 1 箇所だけ保存し、チャット/通信からは
 * file_path を参照共有する設計。
 */
class ClassroomPhoto extends Model
{
    use HasFactory;

    /** 事業所の最大ストレージ (100MB) */
    public const STORAGE_LIMIT_BYTES = 100 * 1024 * 1024;

    /** 1 ファイルあたりの目標サイズ (100KB) */
    public const TARGET_FILE_SIZE = 100 * 1024;

    protected $fillable = [
        'classroom_id',
        'uploader_id',
        'file_path',
        'file_size',
        'mime',
        'width',
        'height',
        'activity_description',
        'activity_date',
        'day_of_week',
        'grade_level',
        'activity_tag_id',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
        ];
    }

    protected $appends = ['url'];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function activityTag(): BelongsTo
    {
        return $this->belongsTo(ClassroomTag::class, 'activity_tag_id');
    }

    public function students(): BelongsToMany
    {
        // pivot には timestamps を持たせていないので withTimestamps() は付けない
        return $this->belongsToMany(Student::class, 'classroom_photo_student');
    }

    /**
     * フロント向け URL (public disk 経由)
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * 事業所の現在の使用容量 (バイト)
     */
    public static function classroomStorageUsed(int $classroomId): int
    {
        return (int) static::where('classroom_id', $classroomId)->sum('file_size');
    }
}
