<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::when(
            $request->search,
            fn($q) => $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('cnpj', 'like', "%{$request->search}%")
        )
        ->when($request->has('active'), fn($q) => $q->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN)))
        ->orderBy('name')
        ->paginate(20);

        return response()->json([
            'data' => CompanyResource::collection($companies),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cnpj' => 'required|string|max:18|unique:companies,cnpj',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zipcode' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:50|max:5000',
            'require_photo' => 'nullable|boolean',
            'require_geolocation' => 'nullable|boolean',
            'work_start' => 'nullable|date_format:H:i',
            'work_end' => 'nullable|date_format:H:i',
            'lunch_duration' => 'nullable|integer|min:0|max:120',
        ]);

        $company = Company::create($request->all());

        return response()->json([
            'message' => 'Empresa criada com sucesso.',
            'data' => new CompanyResource($company),
        ], 201);
    }

    public function show(Company $company): JsonResponse
    {
        $company->loadCount('activeEmployees');

        return response()->json(['data' => new CompanyResource($company)]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:50|max:5000',
            'require_photo' => 'nullable|boolean',
            'require_geolocation' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $company->update($request->all());

        return response()->json([
            'message' => 'Empresa atualizada com sucesso.',
            'data' => new CompanyResource($company->fresh()),
        ]);
    }
}
