<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-5 py-2.5 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-700 rounded-full font-semibold text-sm text-gray-700 dark:text-zinc-300 tracking-wide hover:bg-gray-50 dark:hover:bg-zinc-700 hover:text-gray-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:ring-offset-2 dark:focus:ring-offset-zinc-900 disabled:opacity-50 transition-all duration-200']) }}>
    {{ $slot }}
</button>
