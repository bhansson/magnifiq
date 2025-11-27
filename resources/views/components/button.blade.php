<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-amber-400 to-orange-500 border-0 rounded-full font-semibold text-sm text-black tracking-wide hover:from-amber-300 hover:to-orange-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:ring-offset-2 dark:focus:ring-offset-zinc-900 disabled:opacity-50 transition-all duration-200 shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40']) }}>
    {{ $slot }}
</button>
