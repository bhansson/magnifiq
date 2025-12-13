<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-700 rounded-xl font-medium text-sm text-gray-700 dark:text-zinc-300 tracking-wide hover:bg-gray-50 dark:hover:bg-zinc-700 hover:text-gray-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 disabled:opacity-50 transition-colors duration-200']) }}>
    {{ $slot }}
</button>
