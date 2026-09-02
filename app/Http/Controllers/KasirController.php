<?php

namespace App\Http\Controllers;

use App\Http\Requests\KasirPasswordResetRequest;
use App\Http\Requests\KasirStoreRequest;
use App\Http\Requests\KasirUpdateRequest;
use App\Models\Kasir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class KasirController extends Controller
{
    public function index(Request $request): View
    {
        $kasirs = Kasir::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->trim();
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.kasir.index', [
            'kasirs' => $kasirs,
            'summary' => [
                'total' => Kasir::query()->count(),
                'active' => Kasir::query()->where('is_active', true)->count(),
                'inactive' => Kasir::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.kasir.create', ['kasir' => new Kasir(['is_active' => true])]);
    }

    public function store(KasirStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        Kasir::query()->create($data);

        return redirect()->route('admin.kasir.index')->with('success', 'Akun Kasir berhasil ditambahkan.');
    }

    public function edit(Kasir $kasir): View
    {
        return view('admin.kasir.edit', compact('kasir'));
    }

    public function update(KasirUpdateRequest $request, Kasir $kasir): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $kasir->update($data);

        return redirect()->route('admin.kasir.index')->with('success', 'Akun Kasir berhasil diperbarui.');
    }

    public function toggle(Kasir $kasir): RedirectResponse
    {
        $kasir->update(['is_active' => ! $kasir->is_active]);

        return back()->with('success', 'Akun '.$kasir->name.' berhasil '.($kasir->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function resetPassword(KasirPasswordResetRequest $request, Kasir $kasir): RedirectResponse
    {
        $kasir->update(['password' => Hash::make($request->validated('password'))]);

        return back()->with('success', 'Password akun '.$kasir->name.' berhasil direset.');
    }
}
