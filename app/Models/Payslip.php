<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'reference_month',
        'reference_year',
        'file_url',
        'file_name',
        'file_size',
        'description',
        'notified',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'integer',
            'reference_year'  => 'integer',
            'file_size'       => 'integer',
            'notified'        => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getReferenceLabel(): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];

        return ($months[$this->reference_month] ?? $this->reference_month).' '.$this->reference_year;
    }

    public function getFileSizeFormatted(): string
    {
        if (! $this->file_size) {
            return '';
        }
        if ($this->file_size < 1024) {
            return $this->file_size.' B';
        }
        if ($this->file_size < 1048576) {
            return round($this->file_size / 1024, 1).' KB';
        }

        return round($this->file_size / 1048576, 1).' MB';
    }
}
