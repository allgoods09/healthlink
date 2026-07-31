<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangayOfficial extends Model
{
    use HasFactory;

    public const ROLE_PUNONG_BARANGAY = 'punong_barangay';
    public const ROLE_BARANGAY_SECRETARY = 'barangay_secretary';
    public const ROLE_BARANGAY_TREASURER = 'barangay_treasurer';

    public const ROLE_DEFINITIONS = [
        self::ROLE_PUNONG_BARANGAY => ['title' => 'Punong Barangay', 'order' => 1],
        self::ROLE_BARANGAY_SECRETARY => ['title' => 'Barangay Secretary', 'order' => 2],
        self::ROLE_BARANGAY_TREASURER => ['title' => 'Barangay Treasurer', 'order' => 3],
        'kagawad_1' => ['title' => 'Barangay Kagawad 1', 'order' => 4],
        'kagawad_2' => ['title' => 'Barangay Kagawad 2', 'order' => 5],
        'kagawad_3' => ['title' => 'Barangay Kagawad 3', 'order' => 6],
        'kagawad_4' => ['title' => 'Barangay Kagawad 4', 'order' => 7],
        'kagawad_5' => ['title' => 'Barangay Kagawad 5', 'order' => 8],
        'kagawad_6' => ['title' => 'Barangay Kagawad 6', 'order' => 9],
        'kagawad_7' => ['title' => 'Barangay Kagawad 7', 'order' => 10],
    ];

    protected $fillable = [
        'barangay_id',
        'role_key',
        'official_title',
        'official_name',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function defaults(): array
    {
        return collect(self::ROLE_DEFINITIONS)
            ->map(fn (array $definition, string $roleKey) => [
                'role_key' => $roleKey,
                'official_title' => $definition['title'],
                'display_order' => $definition['order'],
            ])
            ->values()
            ->all();
    }
}
