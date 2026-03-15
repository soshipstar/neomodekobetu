<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_name',
        'address',
        'phone',
        'logo_path',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /** @return HasMany<User> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Student> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** @return HasMany<Newsletter> */
    public function newsletters(): HasMany
    {
        return $this->hasMany(Newsletter::class);
    }

    /** @return HasMany<Event> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<WeeklyPlan> */
    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(WeeklyPlan::class);
    }

    /** @return HasMany<WorkDiary> */
    public function workDiaries(): HasMany
    {
        return $this->hasMany(WorkDiary::class);
    }

    /** @return HasMany<ClassroomTag> */
    public function tags(): HasMany
    {
        return $this->hasMany(ClassroomTag::class);
    }

    /** @return HasMany<ClassroomCapacity> */
    public function capacity(): HasMany
    {
        return $this->hasMany(ClassroomCapacity::class);
    }

    /** @return HasMany<DailyRoutine> */
    public function routines(): HasMany
    {
        return $this->hasMany(DailyRoutine::class);
    }

    /** @return HasMany<ActivityType> */
    public function activityTypes(): HasMany
    {
        return $this->hasMany(ActivityType::class);
    }

    /** @return HasMany<Holiday> */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /** @return HasOne<NewsletterSetting> */
    public function newsletterSetting(): HasOne
    {
        return $this->hasOne(NewsletterSetting::class);
    }

    /** @return HasMany<DailyRecord> */
    public function dailyRecords(): HasMany
    {
        return $this->hasMany(DailyRecord::class);
    }

    /** @return HasMany<SchoolHolidayActivity> */
    public function schoolHolidayActivities(): HasMany
    {
        return $this->hasMany(SchoolHolidayActivity::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
