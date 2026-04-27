<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HolidayWebController extends Controller
{
    public function __construct(private readonly HolidayService $holidayService) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $year      = (int) $request->get('year', date('Y'));
        $companyId = $request->get('company_id');
        $scope     = $request->get('scope');

        $holidays = Holiday::query()
            ->when($year, fn($q) => $q->whereYear('date', $year))
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when(!$companyId, fn($q) => $q->whereNull('company_id'))
            ->when($scope, fn($q) => $q->where('scope', $scope))
            ->orderBy('date')
            ->paginate(50)
            ->withQueryString();

        $companies = Company::orderBy('name')->get();

        return view('web.holidays.index', compact('holidays', 'year', 'companyId', 'scope', 'companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'date'       => 'required|date_format:Y-m-d',
            'scope'      => 'required|in:national,state,municipal,custom',
            'state'      => 'nullable|string|max:2',
            'city'       => 'nullable|string|max:120',
            'company_id' => 'nullable|integer|exists:companies,id',
            'recurring'  => 'boolean',
        ]);

        $data['recurring'] = $request->boolean('recurring');
        $data['company_id'] = $data['company_id'] ?? null;

        Holiday::create($data);
        Cache::flush();

        return back()->with('success', 'Feriado cadastrado com sucesso.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorize('manage-employees');

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'date'       => 'required|date_format:Y-m-d',
            'scope'      => 'required|in:national,state,municipal,custom',
            'state'      => 'nullable|string|max:2',
            'city'       => 'nullable|string|max:120',
            'company_id' => 'nullable|integer|exists:companies,id',
            'recurring'  => 'boolean',
        ]);

        $data['recurring']  = $request->boolean('recurring');
        $data['company_id'] = $data['company_id'] ?? null;

        $holiday->update($data);
        Cache::flush();

        return back()->with('success', 'Feriado atualizado.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->authorize('manage-employees');

        $holiday->delete();
        Cache::flush();

        return back()->with('success', 'Feriado removido.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $year      = (int) $request->get('year', date('Y'));
        $companyId = $request->get('company_id');

        try {
            if ($companyId) {
                $company = Company::findOrFail($companyId);
                $count   = $this->holidayService->syncForCompany($company, $year);
                $msg     = "Sincronizados {$count} feriados para {$company->name} ({$year}).";
            } else {
                $total = 0;
                foreach (Company::all() as $c) {
                    $total += $this->holidayService->syncForCompany($c, $year);
                }
                $msg = "Sincronizados {$total} feriados para todas as empresas ({$year}).";
            }
            Cache::flush();
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao sincronizar: ' . $e->getMessage());
        }

        return back()->with('success', $msg);
    }
}
