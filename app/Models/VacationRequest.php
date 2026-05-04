<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'company_id',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date'  => 'date',
            'end_date'    => 'date',
            'days'        => 'integer',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'aprovado'  => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'rejeitado' => 'bg-rose-100 text-rose-700 border-rose-200',
            default     => 'bg-amber-100 text-amber-700 border-amber-200',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'aprovado'  => 'Aprovado',
            'rejeitado' => 'Rejeitado',
            default     => 'Pendente',
        };
    }
}
