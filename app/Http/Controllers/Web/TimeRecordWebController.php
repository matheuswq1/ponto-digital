<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TimeRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimeRecordWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $date   = $request->get('date', today()->toDateString());
        $search = $request->get('q');

        $records = TimeRecord::with('employee.user')
            ->whereDate('datetime', $date)
            ->when($search, function ($q) use ($search) {
                $q->whereHas('employee.user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            })
            ->orderBy('datetime')
            ->paginate(30)
            ->withQueryString();

        $total   = $records->total();
        $dateObj = Carbon::parse($date);

        return view('web.pontos.index', compact('records', 'date', 'dateObj', 'total', 'search'));
    }
}
