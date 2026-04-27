<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id',
        'description', 'properties', 'ip_address', 'user_agent', 'company_id',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    public function subjectLabel(): string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return '—';
        }
        $s = $this->relationLoaded('subject') ? $this->subject : $this->subject()->first();
        if (! $s) {
            return '#'.$this->subject_id;
        }
        if ($s instanceof Employee) {
            return $s->user?->name ?? 'Colaborador #'.$s->id;
        }
        if ($s instanceof Company) {
            return $s->name;
        }
        if ($s instanceof Holiday) {
            return $s->name.' ('.$s->date->format('d/m/Y').')';
        }
        if ($s instanceof TimeRecord) {
            return 'Ponto #'.$s->id;
        }
        if ($s instanceof TimeRecordEdit) {
            return 'Correção #'.$s->id;
        }
        if ($s instanceof HourBankTransaction) {
            return 'Mov. banco de horas #'.$s->id;
        }
        if ($s instanceof HourBankRequest) {
            return 'Solicitação folga #'.$s->id;
        }

        return class_basename($this->subject_type).' #'.$this->subject_id;
    }
}
