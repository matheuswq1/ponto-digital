<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'cnpj',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zipcode',
        'logo_url',
        'active',
        'latitude',
        'longitude',
        'geofence_radius',
        'require_photo',
        'require_geolocation',
        'work_start',
        'work_end',
        'lunch_duration',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'require_photo' => 'boolean',
            'require_geolocation' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function activeEmployees(): HasMany
    {
        return $this->hasMany(Employee::class)->where('active', true);
    }

    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
