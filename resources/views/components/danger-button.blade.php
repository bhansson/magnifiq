<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-red-600 hover:bg-red-500 border-0 rounded-xl font-medium text-sm text-white tracking-wide focus:outline-none focus:ring-2 focus:ring-red-500/50 disabled:opacity-50 transition-colors duration-200']) }}>
    {{ $slot }}
</button>
