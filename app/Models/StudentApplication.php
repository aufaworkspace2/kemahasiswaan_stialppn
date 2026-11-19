<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'form_id', 'form_version_id', 'service_id', 'service_category_id', 'study_program_id', 'cohort_id', 'student_number', 'full_name', 'reply_channel', 'email', 'whatsapp', 'answers', 'status', 'idempotency_key', 'receipt_path', 'submitted_at'])]
class StudentApplication extends Model
{
    use HasFactory;

    protected $table = 'applications';

    protected function casts(): array
    {
        return ['answers' => 'array', 'submitted_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ApplicationAttachment::class, 'application_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class, 'application_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class, 'application_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'application_id');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class, 'application_id');
    }
}
