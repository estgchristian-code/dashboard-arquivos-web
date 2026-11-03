<div class="rounded-xl border border-line bg-surface p-5 shadow-sm sm:p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold">Comece com sua planilha</h2>
            <p class="mt-1 text-sm text-muted">
                Selecione ou arraste um arquivo para visualizar os dados antes de criar o dashboard.
            </p>
        </div>
        <span class="shrink-0 rounded-full bg-brand-soft px-2.5 py-1 text-xs font-medium text-brand">
            Até {{ $limits['max_file_label'] }}
        </span>
    </div>

    @if ($errorMessage !== '')
        <div class="mt-5 flex items-start gap-3 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]" role="alert">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm0-7a1 1 0 0 0-1 1v3a1 1 0 1 0 2 0V7a1 1 0 0 0-1-1Z" clip-rule="evenodd" />
            </svg>
            <p class="flex-1 leading-6" wire:key="upload-error">{{ $errorMessage }}</p>
            <button
                type="button"
                wire:click="dismissError"
                class="shrink-0 rounded-md p-1 text-[#991b1b] transition-colors hover:bg-[#fee2e2] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#991b1b]"
                aria-label="Fechar mensagem de erro"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-5 flex items-start gap-3 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]" role="alert">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm0-7a1 1 0 0 0-1 1v3a1 1 0 1 0 2 0V7a1 1 0 0 0-1-1Z" clip-rule="evenodd" />
            </svg>
            <p class="leading-6">
                Não foi possível enviar o arquivo. Verifique se ele tem no máximo
                {{ $limits['max_file_label'] }} e se está em CSV, TSV, XLSX, XLS ou JSON.
            </p>
        </div>
    @endif

    @if ($import !== null)
        <div class="mt-5 rounded-lg border border-line">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line bg-canvas px-4 py-3.5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded bg-brand-soft px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-brand">
                            {{ $import['format_label'] }}
                        </span>
                        <span class="truncate text-sm font-medium" title="{{ $import['original_name'] }}">
                            {{ $import['original_name'] }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        {{ $import['size_label'] }} · {{ $import['created_at'] }} · expira em {{ $import['expires_at'] }}
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="remove"
                    class="inline-flex items-center gap-1.5 rounded-md border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:border-[#fca5a5] hover:text-[#991b1b] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.75 1a1 1 0 0 0-.97.757L7.5 3H4a1 1 0 0 0 0 2h12a1 1 0 1 0 0-2h-3.5l-.28-1.243A1 1 0 0 0 11.25 1h-2.5ZM5 6h10l-.7 10.1a2 2 0 0 1-2 1.9H7.7a2 2 0 0 1-2-1.9L5 6Z" clip-rule="evenodd" />
                    </svg>
                    Remover e enviar outro
                </button>
            </div>

            <dl class="grid grid-cols-2 gap-px border-b border-line bg-line sm:grid-cols-4">
                <div class="bg-surface px-4 py-3">
                    <dt class="text-xs text-muted">Linhas</dt>
                    <dd class="mt-0.5 text-base font-semibold">{{ number_format($import['row_count'], 0, ',', '.') }}</dd>
                </div>
                <div class="bg-surface px-4 py-3">
                    <dt class="text-xs text-muted">Colunas</dt>
                    <dd class="mt-0.5 text-base font-semibold">{{ number_format($import['column_count'], 0, ',', '.') }}</dd>
                </div>
                <div class="bg-surface px-4 py-3">
                    <dt class="text-xs text-muted">Células vazias</dt>
                    <dd class="mt-0.5 text-base font-semibold">{{ number_format($import['empty_cells'], 0, ',', '.') }}</dd>
                </div>
                <div class="bg-surface px-4 py-3">
                    <dt class="text-xs text-muted">Codificação</dt>
                    <dd class="mt-0.5 text-base font-semibold">{{ $import['encoding'] }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 border-b border-line px-4 py-2.5 text-xs text-muted">
                @if ($import['delimiter_label'] !== null)
                    <span>Separador: <strong class="font-medium text-ink">{{ $import['delimiter_label'] }}</strong></span>
                @endif
                @if ($import['sheet_name'] !== null)
                    <span>Planilha: <strong class="font-medium text-ink">{{ $import['sheet_name'] }}</strong></span>
                @endif
                <span>
                    Prévia: <strong class="font-medium text-ink">{{ $import['preview_row_count'] }}</strong>
                    de {{ number_format($import['row_count'], 0, ',', '.') }} linhas
                </span>
            </div>

            <div class="max-h-[22rem] overflow-auto">
                <table class="min-w-full border-collapse text-left text-xs">
                    <caption class="sr-only">
                        Prévia das primeiras linhas e colunas identificadas no arquivo {{ $import['original_name'] }}
                    </caption>
                    <thead class="sticky top-0 z-10 bg-canvas text-ink">
                        <tr>
                            <th scope="col" class="w-10 border-b border-line px-3 py-2 text-right font-medium text-muted">#</th>
                            @foreach ($import['columns'] as $column)
                                <th scope="col" class="whitespace-nowrap border-b border-line px-3 py-2 font-semibold">
                                    {{ $column['name'] }}
                                    <span class="mt-0.5 block text-[10px] font-normal text-muted">{{ $column['type_label'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @foreach ($import['rows'] as $rowIndex => $row)
                            <tr class="hover:bg-canvas">
                                <td class="px-3 py-2 text-right text-muted">{{ $rowIndex + 1 }}</td>
                                @foreach ($import['columns'] as $columnIndex => $column)
                                    @php($value = $row[$columnIndex] ?? '')
                                    <td class="max-w-[18rem] truncate whitespace-nowrap px-3 py-2 {{ $value === '' ? 'text-muted' : '' }}">
                                        {{ $value === '' ? '—' : $value }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t border-line px-4 py-2.5 text-xs text-muted">
                A prévia é temporária, fica em armazenamento privado e é removida automaticamente em até
                {{ $limits['retention_hours'] }} horas. Nenhum arquivo é público.
            </p>
            <div class="flex flex-wrap items-center gap-2 border-t border-line px-4 py-3">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-brand-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Gerar dashboard</a>
                <button type="button" wire:click="remove" class="inline-flex items-center justify-center rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-medium text-muted transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Remover e enviar outro</button>
            </div>
        </div>
    @else
        <div
            x-data="{ dragging: false }"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="dragging = false; const files = $event.dataTransfer.files; if (files.length) { $refs.fileInput.files = files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true })); } }"
            x-bind:class="dragging ? 'border-brand bg-brand-soft' : 'border-[#cbd5e1] bg-canvas'"
            class="mt-5 rounded-lg border-2 border-dashed px-5 py-9 text-center transition-colors"
        >
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-line bg-surface text-brand" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 16V4" />
                    <path d="m7 9 5-5 5 5" />
                    <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                </svg>
            </span>

            <p class="mt-4 text-sm font-medium">Arraste e solte sua planilha aqui</p>
            <p class="mt-1 text-xs text-muted">ou</p>

            <input
                x-ref="fileInput"
                id="datacanvas-file"
                type="file"
                wire:model="file"
                wire:key="file-input-{{ $uploadKey }}"
                accept=".csv,.tsv,.xlsx,.xls,.json,text/csv,text/tab-separated-values,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/json"
                class="sr-only"
                aria-describedby="upload-status upload-limits"
            />

            <button
                type="button"
                x-on:click="$refs.fileInput.click()"
                wire:loading.attr="disabled"
                wire:target="file"
                class="mt-3 inline-flex items-center justify-center gap-2 rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium transition-colors hover:border-brand hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:text-muted"
            >
                <svg wire:loading.remove wire:target="file" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                    <path d="M14 3v5h5" />
                </svg>
                <svg wire:loading wire:target="file" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-75" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2Z" />
                </svg>
                <span wire:loading.remove wire:target="file">Selecionar arquivo</span>
                <span wire:loading wire:target="file">Lendo arquivo…</span>
            </button>

            <p id="upload-status" class="mt-3 text-xs text-muted" aria-live="polite">
                CSV, TSV, XLSX, XLS ou JSON tabular
            </p>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
            <span>CSV · TSV · XLSX · XLS · JSON</span>
            <span id="upload-limits">
                Até {{ number_format($limits['max_rows'], 0, ',', '.') }} linhas e
                {{ number_format($limits['max_columns'], 0, ',', '.') }} colunas
            </span>
        </div>

        <p class="mt-4 border-t border-line pt-4 text-xs leading-5 text-muted">
            O arquivo é validado no servidor pelo conteúdo real. Fórmulas, macros e conteúdo incorporado
            não são executados. O acesso à prévia é restrito a esta sessão do navegador.
        </p>
    @endif
</div>


