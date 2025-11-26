<div class="space-y-6">
    @if ($statusMessage)
        <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-400">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Template Library</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-300">
                Manage AI prompt templates used across product generations. Default templates are provided for every team;
                create custom ones tailored to your workflow.
            </p>
        </div>
        <div class="flex gap-3">
            <x-button type="button" wire:click="startCreate">
                Create template
            </x-button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @forelse ($templates as $template)
            @php
                $context = collect($template->context ?? []);
                $placeholders = $context
                    ->pluck('key')
                    ->filter()
                    ->map(fn ($key) => '{{ '.$key.' }}')
                    ->join(', ');
                $contentType = \Illuminate\Support\Str::headline($template->settings['content_type'] ?? 'text');
                $isDefault = $template->team_id === null;
            @endphp
                <div class="rounded-xl border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none">
                    <div class="px-5 py-4 sm:flex sm:items-start sm:justify-between">
                        <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $template->name }}</h2>
                                    @if ($isDefault)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-zinc-700 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:text-zinc-300">
                                            Default
                                        </span>
                                    @endif
                                </div>
                                @if ($template->description)
                                    <p class="text-sm text-gray-600 dark:text-zinc-300">{{ $template->description }}</p>
                                @endif
                            <dl class="grid gap-1 text-sm text-gray-600 dark:text-zinc-300 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-400">Slug</dt>
                                    <dd>{{ $template->slug }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-400">Content type</dt>
                                    <dd>{{ $contentType }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-400">Context variables</dt>
                                    <dd>{{ $placeholders ?: 'None' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 sm:mt-0 sm:flex-col sm:items-end sm:gap-3">
                            <x-button type="button" wire:click="duplicate({{ $template->id }})" class="px-3 py-1.5">
                                Copy
                            </x-button>
                            @if ($template->team_id === optional($team)->id)
                                <x-button type="button" wire:click="startEdit({{ $template->id }})" class="px-3 py-1.5">
                                    Edit
                                </x-button>
                                <button type="button"
                                        x-data
                                        x-on:click.prevent="if (window.confirm('Delete the {{ addslashes($template->name) }} template? This action cannot be undone.')) { $wire.delete({{ $template->id }}) }"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-full text-xs font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                                    Delete
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="border-t border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/30 px-5 py-3 text-xs text-gray-500 dark:text-zinc-400">
                        Updated {{ optional($template->updated_at)->diffForHumans() ?? 'recently' }}
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/30 px-5 py-6 text-sm text-gray-600 dark:text-zinc-300">
                    No templates found yet. Start by creating one above.
                </div>
            @endforelse
        </div>

        <div class="space-y-4">
            @if ($showForm)
                <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none">
                    <div class="border-b border-amber-100 dark:border-amber-500/20 px-5 py-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $editingTemplateId ? 'Edit template' : 'Create template' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-300">
                            Fill out the details below to {{ $editingTemplateId ? 'update your' : 'add a new' }} template.
                        </p>
                    </div>
                    <form wire:submit.prevent="save" class="px-5 py-5 space-y-5"
                          x-data="{
                              insertPlaceholder(value) {
                                  const field = this.$refs.promptField;
                                  if (!field) {
                                      return;
                                  }

                                  const start = field.selectionStart ?? field.value.length;
                                  const end = field.selectionEnd ?? field.value.length;
                                  const before = field.value.slice(0, start);
                                  const after = field.value.slice(end);
                                  const needsWhitespace = before !== '' && !/\s$/u.test(before);
                                  const insertion = (needsWhitespace ? ' ' : '') + value;

                                  field.value = before + insertion + after;
                                  const cursor = before.length + insertion.length;

                                  field.setSelectionRange(cursor, cursor);
                                  field.focus();
                                  field.dispatchEvent(new Event('input'));
                              }
                          }">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Name</label>
                            <input type="text" wire:model.defer="form.name" class="mt-1 w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20">
                            @error('form.name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Description <span class="text-xs text-gray-500 dark:text-zinc-400">(optional)</span></label>
                            <textarea wire:model.defer="form.description" rows="2" class="mt-1 w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"></textarea>
                            @error('form.description') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Content type</label>
                            <select wire:model.defer="form.content_type" class="mt-1 w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20">
                                <option value="text">Text</option>
                                <option value="usps">List</option>
                                <option value="faq">FAQ entries</option>
                            </select>
                            @error('form.content_type') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Prompt</label>
                                <div class="flex flex-wrap gap-2">
                                    @php
                                        $chipKeys = ['title', 'description', 'sku', 'gtin', 'brand'];
                                    @endphp
                                    @foreach ($chipKeys as $chipKey)
                                        @php
                                            $variable = $contextVariables[$chipKey] ?? [];
                                            $label = $variable['label'] ?? \Illuminate\Support\Str::headline($chipKey);
                                            $placeholder = '{'.'{ '.$chipKey.' }'.'}';
                                        @endphp
                                        <button type="button"
                                                class="inline-flex items-center rounded-full border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-zinc-300 hover:border-amber-300 dark:hover:border-amber-500/50 hover:text-amber-600 dark:hover:text-amber-400"
                                                x-on:click.prevent="insertPlaceholder('{{ $placeholder }}')">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <textarea id="template-prompt" x-ref="promptField" wire:model.defer="form.prompt" rows="6" class="mt-1 w-full rounded-xl border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20"></textarea>
                            @error('form.prompt') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <x-button type="submit">
                                {{ $editingTemplateId ? 'Save changes' : 'Create template' }}
                            </x-button>
                            <x-secondary-button type="button" wire:click="cancelForm">
                                Cancel
                            </x-secondary-button>
                        </div>
                    </form>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/30 px-5 py-6 text-sm text-gray-600 dark:text-zinc-300">
                    Select a template to edit or create a new one. The form will appear here.
                </div>
            @endif
        </div>
    </div>
</div>
