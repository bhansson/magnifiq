@props([
    'target' => null,
    'text' => null,
    'class' => '',
])

<button
    type="button"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center justify-center rounded p-1 text-gray-400 hover:text-gray-600 dark:text-zinc-500 dark:hover:text-zinc-300 transition ' . $class,
        'title' => 'Copy to clipboard',
    ]) }}
    x-data="{ copied: false }"
    x-on:click="
        let text = '';
        @if ($text)
            text = @js($text);
        @elseif ($target)
            const el = document.getElementById(@js($target));
            if (el) {
                text = el.tagName === 'TEXTAREA' || el.tagName === 'INPUT' ? el.value : el.innerText;
            }
        @endif
        if (text && text.trim()) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                });
            } else {
                const tmp = document.createElement('textarea');
                tmp.value = text;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
                copied = true;
                setTimeout(() => copied = false, 2000);
            }
        }
    "
>
    <template x-if="!copied">
        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
        </svg>
    </template>
    <template x-if="copied">
        <svg class="size-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
    </template>
</button>
