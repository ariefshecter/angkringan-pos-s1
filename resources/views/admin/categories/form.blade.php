<div class="space-y-5">
    <div class="space-y-2"><x-input-label for="name" value="Nama kategori" /><x-text-input id="name" name="name" :value="old('name', $category?->name)" required autofocus /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
    <div class="space-y-2"><x-input-label for="sort_order" value="Urutan tampil" /><x-text-input id="sort_order" name="sort_order" type="number" min="0" :value="old('sort_order', $category?->sort_order ?? 0)" required /><x-input-error :messages="$errors->get('sort_order')" class="mt-2" /></div>
    <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" class="rounded border-stone-300 text-amber-600 focus:ring-amber-500" @checked(old('is_active', $category?->is_active ?? true))><span class="text-sm text-stone-700">Kategori aktif dan tampil untuk pelanggan</span></label>
    <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end"><a href="{{ route('admin.categories.index') }}" class="ui-button-secondary">Batal</a><button class="ui-button-accent">Simpan kategori</button></div>
</div>
