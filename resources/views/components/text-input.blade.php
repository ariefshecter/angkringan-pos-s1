@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-xl border-stone-300 bg-stone-50/60 px-4 py-3 text-sm text-stone-900 shadow-sm transition placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:ring-amber-500 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:opacity-70']) }}>
