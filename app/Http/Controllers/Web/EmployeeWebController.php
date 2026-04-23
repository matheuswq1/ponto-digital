<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $search = $request->get('q');

        $employees = Employee::with('user', 'company')
            ->where('active', true)
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('cargo', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('web.employees.index', compact('employees', 'search'));
    }
}
