<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-amber-500 hover:bg-amber-600 border-0 rounded-xl font-medium text-sm text-black tracking-wide focus:outline-none focus:ring-2 focus:ring-amber-500/50 disabled:opacity-50 transition-colors duration-200']) }}>
    {{ $slot }}
</button>
