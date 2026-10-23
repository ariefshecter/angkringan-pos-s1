<x-app-layout>
    <x-slot name="header">
        <p class="ui-eyebrow">Account settings</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-950">Pengaturan profil</h1>
        <p class="mt-1 text-sm text-stone-500">Kelola identitas dan keamanan akun operasional Anda.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="ui-surface p-5 sm:p-8">
                <div class="max-w-2xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="ui-surface p-5 sm:p-8">
                <div class="max-w-2xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
