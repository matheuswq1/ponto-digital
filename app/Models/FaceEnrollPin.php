<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceEnrollPin extends Model
{
    protected $fillable = [
        'pin',
        'employee_id',
        'company_id',
        'created_by',
        'used',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'used'       => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** PIN válido: não usado e não expirado. */
    public function isValid(): bool
    {
        return ! $this->used && $this->expires_at->isFuture();
    }
}
