<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $query = Communication::query()
            ->with('author')
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if (auth()->user()->isGestor()) {
            $query->where('company_id', auth()->user()->company_id);
        } elseif ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $communications = $query->paginate(20)->withQueryString();

        return view('web.communications.index', compact('communications'));
    }

    public function create(): View
    {
        $this->authorize('manage-employees');

        return view('web.communications.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $validated = $request->validate([
            'title'        => 'required|string|max:160',
            'body'         => 'required|string|max:5000',
            'type'         => 'required|in:info,aviso,urgente',
            'pinned'       => 'nullable|boolean',
            'publish_now'  => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'expires_at'   => 'nullable|date|after:today',
        ]);

        $companyId = auth()->user()->isAdmin()
            ? $request->integer('company_id', auth()->user()->company_id)
            : auth()->user()->company_id;

        Communication::create([
            'company_id'   => $companyId,
            'created_by'   => auth()->id(),
            'title'        => $validated['title'],
            'body'         => $validated['body'],
            'type'         => $validated['type'],
            'pinned'       => $request->boolean('pinned'),
            'published_at' => $request->boolean('publish_now')
                ? now()
                : ($validated['published_at'] ?? null),
            'expires_at'   => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('painel.communications.index')
            ->with('success', 'Comunicado criado com sucesso.');
    }

    public function edit(Communication $communication): View
    {
        $this->authorize('manage-employees');
        $this->checkCompanyAccess($communication);

        return view('web.communications.edit', compact('communication'));
    }

    public function update(Request $request, Communication $communication): RedirectResponse
    {
        $this->authorize('manage-employees');
        $this->checkCompanyAccess($communication);

        $validated = $request->validate([
            'title'        => 'required|string|max:160',
            'body'         => 'required|string|max:5000',
            'type'         => 'required|in:info,aviso,urgente',
            'pinned'       => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'expires_at'   => 'nullable|date',
        ]);

        $communication->update([
            'title'        => $validated['title'],
            'body'         => $validated['body'],
            'type'         => $validated['type'],
            'pinned'       => $request->boolean('pinned'),
            'published_at' => $request->boolean('publish_now') ? now() : ($validated['published_at'] ?? $communication->published_at),
            'expires_at'   => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('painel.communications.index')
            ->with('success', 'Comunicado atualizado.');
    }

    public function destroy(Communication $communication): RedirectResponse
    {
        $this->authorize('manage-employees');
        $this->checkCompanyAccess($communication);

        $communication->delete();

        return back()->with('success', 'Comunicado removido.');
    }

    private function checkCompanyAccess(Communication $communication): void
    {
        if (auth()->user()->isGestor() && $communication->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
