@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'w-full px-4 py-3 bg-white dark:bg-zinc-800/50 border border-gray-300 dark:border-zinc-700 rounded-xl text-gray-900 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-zinc-500 focus:border-amber-500 dark:focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:focus:ring-amber-500/20 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed']) !!}>
