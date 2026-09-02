<div class="grid gap-5 sm:grid-cols-2">
    <div><x-input-label for="name" value="Nama lengkap" /><x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $kasir->name)" required autofocus /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
    <div><x-input-label for="username" value="Username" /><x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $kasir->username)" required /><x-input-error :messages="$errors->get('username')" class="mt-2" /></div>
    <div><x-input-label for="email" value="Email" /><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $kasir->email)" required /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
    @if($kasir->exists)
        <div class="flex items-center gap-3 pt-7"><input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-stone-300 text-amber-500 focus:ring-amber-500" @checked(old('is_active', $kasir->is_active))><x-input-label for="is_active" value="Akun aktif" /></div>
    @else
        <input type="hidden" name="is_active" value="1">
    @endif
    @if(! $kasir->exists)
        <div><x-input-label for="password" value="Password" /><x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
        <div><x-input-label for="password_confirmation" value="Konfirmasi password" /><x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required /></div>
    @endif
</div>
<div class="mt-6 flex justify-end gap-3 border-t border-stone-100 pt-5"><a href="{{ route('admin.kasir.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium">Batal</a><button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Simpan</button></div>
