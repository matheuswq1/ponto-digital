<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Holiday extends Model
{
    protected $fillable = [
        'name', 'date', 'scope', 'state', 'city', 'company_id', 'recurring',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'recurring' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Verifica se uma data é feriado, considerando feriados nacionais recorrentes
     * e opcionalmente feriados de uma empresa específica.
     */
    public static function isHoliday(Carbon|string $date, ?int $companyId = null): bool
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $dateStr = $carbon->toDateString();
        $md = $carbon->format('m-d'); // mês-dia para recorrentes

        $cacheKey = "holidays_{$dateStr}_{$companyId}";

        return Cache::remember($cacheKey, 3600, function () use ($dateStr, $md, $companyId) {
            return self::query()
                ->where(function ($q) use ($dateStr, $md, $companyId) {
                    // Feriado na data exacta
                    $q->where(function ($q2) use ($dateStr) {
                        $q2->where('date', $dateStr)->where('recurring', false);
                    })
                    // Feriado recorrente (mesmo mês-dia de qualquer ano)
                    ->orWhere(function ($q2) use ($md) {
                        $q2->where('recurring', true)
                           ->whereRaw("DATE_FORMAT(date, '%m-%d') = ?", [$md]);
                    });
                })
                ->where(function ($q) use ($companyId) {
                    $q->whereNull('company_id');
                    if ($companyId) {
                        $q->orWhere('company_id', $companyId);
                    }
                })
                ->exists();
        });
    }

    /**
     * Retorna todas as datas de feriado num período como array de strings 'Y-m-d'.
     */
    public static function datesInPeriod(string $from, string $to, ?int $companyId = null): array
    {
        $start  = Carbon::parse($from);
        $end    = Carbon::parse($to);
        $result = [];

        $current = $start->copy();
        while ($current->lte($end)) {
            if (self::isHoliday($current, $companyId)) {
                $result[] = $current->toDateString();
            }
            $current->addDay();
        }

        return $result;
    }
}
