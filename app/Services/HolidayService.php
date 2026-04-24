<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza feriados a partir de fontes GRATUITAS e sem chave:
 *
 *  1. BrasilAPI (brasilapi.com.br)  — nacionais (inclui móveis como Carnaval)
 *  2. GitHub Raw joaopbini/feriados-brasil — estaduais (filtrando por UF)
 *  3. GitHub Raw joaopbini/feriados-brasil — municipais (filtrando por código IBGE)
 *
 * Os dados são gravados na tabela `holidays` como cache local.
 * A sincronização deve correr 1× por ano (agendada ou manualmente).
 */
class HolidayService
{
    // BrasilAPI — feriados nacionais (inclui carnaval, sexta-santa, corpus christi)
    private string $brasilApiUrl = 'https://brasilapi.com.br/api/feriados/v1';

    // GitHub Raw — estaduais e municipais 2010–2026+
    private string $ghBase = 'https://raw.githubusercontent.com/joaopbini/feriados-brasil/master/dados/feriados';

    /* ------------------------------------------------------------------ */

    /**
     * Sincroniza nacionais + estaduais + municipais para uma empresa e ano.
     * Retorna total de registos inseridos/atualizados.
     */
    public function syncForCompany(Company $company, int $year): int
    {
        $total = 0;

        // 1) Nacionais (BrasilAPI — sem chave)
        $total += $this->syncNacionais($year);

        // 2) Estaduais (GitHub Raw — sem chave)
        $uf = strtoupper(trim($company->state ?? ''));
        if ($uf) {
            $total += $this->syncEstaduais($uf, $year, $company->id);
        }

        // 3) Municipais via código IBGE (GitHub Raw — sem chave)
        $ibge = trim($company->ibge_code ?? '');
        if ($ibge) {
            $total += $this->syncMunicipais($ibge, $year, $company->id);
        }

        $company->updateQuietly(['holidays_synced_at' => now()]);

        return $total;
    }

    /* ------------------------------------------------------------------ */
    /*  FONTES                                                              */
    /* ------------------------------------------------------------------ */

    /** Feriados nacionais via BrasilAPI (gratuito, sem key, inclui móveis). */
    private function syncNacionais(int $year): int
    {
        try {
            $resp = Http::timeout(15)->get("{$this->brasilApiUrl}/{$year}");

            if (! $resp->successful()) {
                Log::warning("HolidayService: BrasilAPI retornou {$resp->status()} para {$year}");
                return 0;
            }

            $count = 0;
            foreach ($resp->json() as $f) {
                $date = $this->parseDate($f['date'] ?? $f['data'] ?? null);
                if (! $date) continue;

                Holiday::updateOrCreate(
                    ['date' => $date, 'scope' => 'nacional', 'company_id' => null, 'state' => null, 'city' => null],
                    ['name' => $f['name'] ?? $f['nome'] ?? 'Feriado Nacional', 'recurring' => false]
                );
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error("HolidayService: erro nacionais {$year}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Feriados estaduais via GitHub Raw joaopbini/feriados-brasil.
     * Filtra por UF. Dados associados à empresa (company_id) para não
     * poluir empresas de outros estados.
     */
    private function syncEstaduais(string $uf, int $year, int $companyId): int
    {
        $url = "{$this->ghBase}/estadual/json/{$year}.json";

        try {
            $resp = Http::timeout(20)->get($url);
            if (! $resp->successful()) {
                Log::warning("HolidayService: GitHub estadual {$year} retornou {$resp->status()}");
                return 0;
            }

            $count = 0;
            foreach ($resp->json() as $f) {
                if (strtoupper($f['uf'] ?? '') !== $uf) continue;

                $date = $this->parseDate($f['data'] ?? null);
                if (! $date) continue;

                Holiday::updateOrCreate(
                    ['date' => $date, 'scope' => 'estadual', 'company_id' => $companyId, 'state' => $uf, 'city' => null],
                    ['name' => $f['nome'] ?? 'Feriado Estadual', 'recurring' => false]
                );
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error("HolidayService: erro estadual {$uf}/{$year}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Feriados municipais via GitHub Raw joaopbini/feriados-brasil.
     * Filtra por código IBGE do município.
     * O JSON municipal pesa ~1,6 MB — stream directo sem carregar tudo na RAM.
     */
    private function syncMunicipais(string $ibge, int $year, int $companyId): int
    {
        $url = "{$this->ghBase}/municipal/json/{$year}.json";

        try {
            $resp = Http::timeout(60)->get($url);
            if (! $resp->successful()) {
                Log::warning("HolidayService: GitHub municipal {$year} retornou {$resp->status()}");
                return 0;
            }

            $ibgeInt = (int) $ibge;
            $count   = 0;

            foreach ($resp->json() as $f) {
                if ((int) ($f['codigo_ibge'] ?? 0) !== $ibgeInt) continue;

                $date = $this->parseDate($f['data'] ?? null);
                if (! $date) continue;

                Holiday::updateOrCreate(
                    ['date' => $date, 'scope' => 'municipal', 'company_id' => $companyId, 'state' => null, 'city' => $ibge],
                    ['name' => $f['nome'] ?? 'Feriado Municipal', 'recurring' => false]
                );
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error("HolidayService: erro municipal IBGE={$ibge}/{$year}: " . $e->getMessage());
            return 0;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                             */
    /* ------------------------------------------------------------------ */

    /** Aceita "dd/mm/yyyy" ou "yyyy-mm-dd". */
    private function parseDate(?string $raw): ?string
    {
        if (! $raw) return null;

        try {
            if (str_contains($raw, '/')) {
                return Carbon::createFromFormat('d/m/Y', $raw)->toDateString();
            }
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Pesquisa municípios via API IBGE de Localidades (gratuita, sem key).
     * Retorna array de ['ibge' => '...', 'nome' => '...', 'uf' => '...']
     */
    public function searchMunicipios(string $uf, string $query = ''): array
    {
        try {
            $resp = Http::timeout(10)
                ->get("https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios");

            if (! $resp->successful()) return [];

            $municipios = collect($resp->json())
                ->map(fn($m) => [
                    'ibge' => (string) $m['id'],
                    'nome' => $m['nome'],
                    'uf'   => strtoupper($m['microrregiao']['mesorregiao']['UF']['sigla'] ?? $uf),
                ]);

            if ($query) {
                $municipios = $municipios->filter(
                    fn($m) => str_contains(strtolower($m['nome']), strtolower($query))
                );
            }

            return $municipios->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
