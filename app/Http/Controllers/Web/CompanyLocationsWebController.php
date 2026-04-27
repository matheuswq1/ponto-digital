<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyLocation;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyLocationsWebController extends Controller
{
    public function __construct(private readonly GeocodingService $geocoding) {}

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('manage-companies');

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'address'       => 'nullable|string|max:500',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:50|max:50000',
        ]);

        // Se o utilizador enviou um endereço mas não as coordenadas, geocodificar
        if (! empty($data['address']) && (empty($data['latitude']) || empty($data['longitude']))) {
            $geo = $this->geocoding->geocode($data['address']);
            if ($geo) {
                $data['latitude']  = $geo['lat'];
                $data['longitude'] = $geo['lng'];
                $data['address']   = $geo['formatted_address'];
            }
        }

        if (empty($data['latitude']) || empty($data['longitude'])) {
            return back()
                ->with('error', 'Não foi possível determinar as coordenadas. Verifique o endereço ou preencha latitude/longitude manualmente.')
                ->with('locations_tab', true);
        }

        $company->locations()->create([
            'name'          => $data['name'],
            'address'       => $data['address'] ?? null,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'radius_meters' => $data['radius_meters'],
            'active'        => true,
        ]);

        return redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'localizacoes'])
            ->with('success', 'Localização adicionada.');
    }

    public function update(Request $request, Company $company, CompanyLocation $location): RedirectResponse
    {
        $this->authorize('manage-companies');
        abort_if($location->company_id !== $company->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'address'       => 'nullable|string|max:500',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:50|max:50000',
            'active'        => 'nullable|boolean',
        ]);

        // Re-geocodificar se endereço mudou e não foram fornecidas coordenadas
        if (! empty($data['address']) && (empty($data['latitude']) || empty($data['longitude']))) {
            $geo = $this->geocoding->geocode($data['address']);
            if ($geo) {
                $data['latitude']  = $geo['lat'];
                $data['longitude'] = $geo['lng'];
                $data['address']   = $geo['formatted_address'];
            }
        }

        if (empty($data['latitude']) || empty($data['longitude'])) {
            return back()
                ->with('error', 'Não foi possível determinar as coordenadas.')
                ->with('locations_tab', true);
        }

        $location->update([
            'name'          => $data['name'],
            'address'       => $data['address'] ?? $location->address,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'radius_meters' => $data['radius_meters'],
            'active'        => $request->boolean('active', true),
        ]);

        return redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'localizacoes'])
            ->with('success', 'Localização actualizada.');
    }

    public function destroy(Company $company, CompanyLocation $location): RedirectResponse
    {
        $this->authorize('manage-companies');
        abort_if($location->company_id !== $company->id, 403);

        $location->delete();

        return redirect()
            ->route('painel.companies.show', ['company' => $company->id, 'tab' => 'localizacoes'])
            ->with('success', 'Localização removida.');
    }

    /**
     * Endpoint AJAX para geocodificar um endereço (usado pelo painel no autocomplete).
     */
    public function geocode(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage-companies');

        $request->validate(['address' => 'required|string|max:500']);

        $result = $this->geocoding->geocode($request->address);

        if (! $result) {
            return response()->json(['error' => 'Endereço não encontrado.'], 422);
        }

        return response()->json($result);
    }
}
