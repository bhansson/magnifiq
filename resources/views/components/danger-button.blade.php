<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-red-600 border border-transparent rounded-full font-semibold text-sm text-white tracking-wide hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:ring-offset-2 dark:focus:ring-offset-zinc-900 disabled:opacity-50 transition-all duration-200']) }}>
    {{ $slot }}
</button>
