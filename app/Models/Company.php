<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
// FraudAttempt é usado na relação abaixo

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
        'ibge_code',
        'logo_url',
        'active',
        'latitude',
        'longitude',
        'geofence_radius',
        'require_photo',
        'require_geolocation',
        'block_mock_location',
        'block_velocity_jump',
        'velocity_jump_threshold_kmh',
        'require_wifi',
        'allowed_wifi_ssids',
        'block_unknown_ip_city',
        'fraud_action',
        'work_start',
        'work_end',
        'lunch_duration',
        'max_daily_records',
    ];

    protected function casts(): array
    {
        return [
            'active'                     => 'boolean',
            'require_photo'              => 'boolean',
            'require_geolocation'        => 'boolean',
            'block_mock_location'        => 'boolean',
            'block_velocity_jump'        => 'boolean',
            'velocity_jump_threshold_kmh'=> 'integer',
            'require_wifi'               => 'boolean',
            'allowed_wifi_ssids'         => 'array',
            'block_unknown_ip_city'      => 'boolean',
            'latitude'                   => 'decimal:7',
            'longitude'                  => 'decimal:7',
            'max_daily_records'          => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function activeEmployees(): HasMany
    {
        return $this->hasMany(Employee::class)->where('active', true);
    }

    public function fraudAttempts(): HasMany
    {
        return $this->hasMany(FraudAttempt::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CompanyLocation::class);
    }

    public function activeLocations(): HasMany
    {
        return $this->hasMany(CompanyLocation::class)->where('active', true);
    }

    /** Verifica campos de geocerca legados (latitude/longitude directamente na empresa). */
    public function hasLegacyGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function hasGeofence(): bool
    {
        return $this->activeLocations()->exists() || $this->hasLegacyGeofence();
    }
}
