<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'title',
        'body',
        'type',
        'pinned',
        'push_sent',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned'       => 'boolean',
            'push_sent'    => 'boolean',
            'published_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getTypeBadgeClass(): string
    {
        return match ($this->type) {
            'urgente' => 'bg-rose-100 text-rose-700 border-rose-200',
            'aviso'   => 'bg-amber-100 text-amber-700 border-amber-200',
            default   => 'bg-blue-100 text-blue-700 border-blue-200',
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'urgente' => 'Urgente',
            'aviso'   => 'Aviso',
            default   => 'Informativo',
        };
    }
}
