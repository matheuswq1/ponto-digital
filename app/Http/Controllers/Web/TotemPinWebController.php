<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FaceEnrollPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TotemPinWebController extends Controller
{
    /**
     * POST /painel/totem-pins/gerar
     *
     * Gera um PIN de uso único de 6 dígitos para cadastro facial no totem.
     * Válido por 15 minutos. Substitui qualquer PIN anterior pendente do mesmo colaborador.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $user = $request->user();

        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
        ]);

        $employee = Employee::with('user', 'company')->findOrFail($request->integer('employee_id'));

        // Gestor só pode gerar PINs para colaboradores da sua empresa
        if ($user->isGestor() && (int) $employee->company_id !== (int) $user->company_id) {
            return back()->with('error', 'Sem permissão para este colaborador.');
        }

        // Invalidar PINs anteriores não usados do mesmo colaborador
        FaceEnrollPin::where('employee_id', $employee->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->update(['used' => true]);

        // Gerar PIN único de 6 dígitos
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (
            FaceEnrollPin::where('pin', $pin)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->exists()
        );

        FaceEnrollPin::create([
            'pin'         => $pin,
            'employee_id' => $employee->id,
            'company_id'  => $employee->company_id,
            'created_by'  => $user->id,
            'used'        => false,
            'expires_at'  => now()->addMinutes(15),
        ]);

        return back()
            ->with('pin_gerado', $pin)
            ->with('pin_employee_name', $employee->user?->name ?? 'Colaborador')
            ->with('pin_employee_id', $employee->id);
    }
}
