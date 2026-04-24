<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'type',
        'datetime',
        'latitude',
        'longitude',
        'accuracy',
        'photo_url',
        'ip_address',
        'device_info',
        'device_id',
        'is_mock_location',
        'offline',
        'synced_at',
        'status',
        'rejection_reason',
        'is_edited',
        'original_record_id',
    ];

    protected $appTimezone;

    protected function casts(): array
    {
        return [
            'datetime'        => 'datetime',
            'synced_at'       => 'datetime',
            'latitude'        => 'decimal:7',
            'longitude'       => 'decimal:7',
            'accuracy'        => 'decimal:2',
            'is_mock_location' => 'boolean',
            'offline'         => 'boolean',
            'is_edited'       => 'boolean',
        ];
    }

    /**
     * Lê o campo datetime do banco (armazenado em UTC) e interpreta
     * como UTC antes de qualquer conversão de timezone.
     */
    protected function asDateTime($value): Carbon
    {
        $carbon = parent::asDateTime($value);
        // O banco guarda em UTC; forçamos o timezone a UTC para que
        // conversões posteriores (ex: setTimezone) funcionem corretamente.
        return $carbon->setTimezone('UTC');
    }

    /**
     * Retorna o datetime convertido para o timezone da aplicação (BRT).
     */
    public function getDatetimeLocalAttribute(): ?Carbon
    {
        return $this->datetime?->copy()->setTimezone(config('app.timezone', 'America/Sao_Paulo'));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(TimeRecordEdit::class);
    }

    public function originalRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class, 'original_record_id');
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'entrada' => 'Entrada',
            'saida' => 'Saída',
            default => $this->type,
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pendente';
    }

    public function isApproved(): bool
    {
        return $this->status === 'aprovado';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pendente');
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('datetime', $date);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
