@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-slate-300 bg-white/95 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-tubigon focus:ring-tubigon disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500']) }}>
