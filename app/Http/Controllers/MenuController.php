<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $menus = Menu::query()
            ->with('category')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.menus.index', [
            'menus' => $menus,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menus.create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = Str::slug($data['name']);
        $data['is_available'] = $request->boolean('is_available');
        $data['image_path'] = $request->file('image')?->store('menus', 'public');
        unset($data['image']);
        Menu::query()->create($data);

        return redirect()->route('admin.menus.index')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit(Menu $menu): View
    {
        return view('admin.menus.edit', [
            'menu' => $menu,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $data = $this->validateData($request, $menu);

        if ($data['stock'] < $menu->reserved_stock) {
            throw ValidationException::withMessages([
                'stock' => "Stok tidak boleh kurang dari {$menu->reserved_stock} karena sedang direservasi pelanggan.",
            ]);
        }

        $data['slug'] = Str::slug($data['name']);
        $data['is_available'] = $request->boolean('is_available');

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($menu->image_path ?? '');
            $data['image_path'] = $request->file('image')->store('menus', 'public');
        }

        unset($data['image']);
        $menu->update($data);

        return redirect()->route('admin.menus.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        Storage::disk('public')->delete($menu->image_path ?? '');
        $menu->delete();

        return back()->with('success', 'Menu berhasil dihapus.');
    }

    private function validateData(Request $request, ?Menu $menu = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:kategori,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('menu', 'name')->ignore($menu)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_available' => ['nullable', 'boolean'],
        ]);
    }
}
