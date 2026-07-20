<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileAppRelease extends Model
{
    use HasFactory;

    public const APP_SCOPE_BHW = 'bhw';
    public const PLATFORM_ANDROID = 'android';

    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_URL = 'url';

    public const UPDATE_OPTIONAL = 'optional';
    public const UPDATE_REQUIRED = 'required';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'app_scope',
        'platform',
        'version_name',
        'version_code',
        'release_title',
        'release_notes',
        'artifact_source',
        'artifact_path',
        'artifact_url',
        'update_mode',
        'status',
        'published_at',
        'created_by_user_id',
        'published_by_user_id',
        'rolled_back_from_release_id',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function rolledBackFrom()
    {
        return $this->belongsTo(self::class, 'rolled_back_from_release_id');
    }

    public function scopeForBhwAndroid($query)
    {
        return $query
            ->where('app_scope', self::APP_SCOPE_BHW)
            ->where('platform', self::PLATFORM_ANDROID);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_RETIRED => 'Retired',
            default => 'Draft',
        };
    }

    public function getUpdateModeLabelAttribute(): string
    {
        return match ($this->update_mode) {
            self::UPDATE_REQUIRED => 'Required Update',
            default => 'Optional Update',
        };
    }

    public function getArtifactSourceLabelAttribute(): string
    {
        return match ($this->artifact_source) {
            self::SOURCE_URL => 'Hosted Link',
            default => 'Uploaded APK',
        };
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->release_title ?: "HealthLink BHW {$this->version_name}";
    }

    public function getDownloadFilenameAttribute(): string
    {
        return sprintf(
            'healthlink-bhw-android-v%s-%s.apk',
            str_replace(' ', '-', $this->version_name),
            $this->version_code
        );
    }
}
