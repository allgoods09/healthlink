<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
        'is_editable',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_editable' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include active settings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include editable settings.
     */
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    /**
     * Scope a query to filter by group.
     */
    public function scopeGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the setting value as a specific type.
     */
    public function getTypedValueAttribute()
    {
        $value = $this->attributes['value'] ?? null;
        
        // If it's null, return null
        if (is_null($value)) {
            return null;
        }
        
        // If it's already an array/object from JSON cast, return it
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        
        // If it's a string, try to decode JSON
        if (is_string($value)) {
            $trimmed = trim($value);
            
            // Check if it looks like JSON (starts with { or [)
            if (strlen($trimmed) > 0) {
                $firstChar = $trimmed[0];
                if ($firstChar === '{' || $firstChar === '[') {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }
            }
            
            return $value;
        }
        
        return $value;
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->active()->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->typed_value;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, $value, array $options = [])
    {
        $setting = self::where('key', $key)->first();
        
        if ($setting) {
            $setting->update(['value' => $value]);
            return $setting;
        }
        
        return self::create(array_merge([
            'key' => $key,
            'value' => $value,
            'group' => 'general',
        ], $options));
    }

    /**
     * Get all settings in a group.
     */
    public static function getGroup(string $group)
    {
        return self::where('group', $group)->active()->get();
    }
}