@extends('layouts.app')

@section('title', 'Oqentra — Dashboards a partir de planilhas')

@section('content')
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="Oqentra — página inicial">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-white" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19V10" />
                        <path d="M10 19V5" />
                        <path d="M16 19v-6" />
                        <path d="M22 19H2" />
                    </svg>
                </span>
                <span class="text-lg font-semibold tracking-tight">Oqentra</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-muted md:flex" aria-label="Navegação principal">
                <a href="#como-funciona" class="transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                    Como funciona
                </a>
                <a href="#recursos" class="transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                    Recursos
                </a>
            </nav>

            <a
                href="#enviar"
                class="inline-flex items-center justify-center rounded-md bg-brand px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            >
                Enviar planilha
            </a>
        </div>
    </header>

    <main id="conteudo" class="flex-1">
        <section class="mx-auto w-full max-w-6xl px-4 pb-16 pt-12 sm:px-6 sm:pt-16 lg:px-8 lg:pb-24 lg:pt-20">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <p class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-muted">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand" aria-hidden="true"></span>
                        Dashboards gerados a partir de planilhas
                    </p>

                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.25rem] lg:leading-[1.08]">
                        Transforme planilhas em <span class="text-brand">dashboards claros</span>.
                    </h1>

                    <p class="mt-5 max-w-xl text-lg leading-8 text-muted">
                        O Oqentra organiza arquivos tabulares e gera gráficos automaticamente para você explorar seus dados. Sem cadastro, sem login e sem configuração inicial.
                    </p>

                    <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-muted">
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.296a1 1 0 0 1 0 1.408l-7.5 7.5a1 1 0 0 1-1.408 0l-3.5-3.5a1 1 0 1 1 1.408-1.408L8.5 12.091l6.796-6.795a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                            </svg>
                            Sem cadastro
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.296a1 1 0 0 1 0 1.408l-7.5 7.5a1 1 0 0 1-1.408 0l-3.5-3.5a1 1 0 1 1 1.408-1.408L8.5 12.091l6.796-6.795a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                            </svg>
                            CSV, TSV, XLSX, XLS e JSON
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.296a1 1 0 0 1 0 1.408l-7.5 7.5a1 1 0 0 1-1.408 0l-3.5-3.5a1 1 0 1 1 1.408-1.408L8.5 12.091l6.796-6.795a1 1 0 0 1 1.408 0Z" clip-rule="evenodd" />
                            </svg>
                            Gráficos automáticos
                        </li>
                    </ul>
                </div>

                <div id="enviar" class="scroll-mt-24">
                    <livewire:import-uploader />
                </div>
            </div>
        </section>

        <section id="como-funciona" class="scroll-mt-16 border-y border-line bg-surface py-16 sm:py-20">
            <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-brand">Como funciona</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                        Da planilha ao painel em três passos
                    </h2>
                    <p class="mt-3 text-muted">
                        Um fluxo direto para quem precisa analisar dados sem depender de ferramentas complexas.
                    </p>
                </div>

                <ol class="mt-10 grid gap-6 md:grid-cols-3">
                    <li class="rounded-xl border border-line bg-canvas p-6">
                        <span class="text-sm font-semibold text-brand">01</span>
                        <h3 class="mt-3 text-base font-semibold">Envie a planilha</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Selecione ou arraste um arquivo CSV, TSV, XLSX, XLS ou JSON tabular para ver os dados.
                        </p>
                    </li>
                    <li class="rounded-xl border border-line bg-canvas p-6">
                        <span class="text-sm font-semibold text-brand">02</span>
                        <h3 class="mt-3 text-base font-semibold">Revise os dados</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            O Oqentra identifica colunas, tipos e agrupamentos relevantes.
                        </p>
                    </li>
                    <li class="rounded-xl border border-line bg-canvas p-6">
                        <span class="text-sm font-semibold text-brand">03</span>
                        <h3 class="mt-3 text-base font-semibold">Explore o dashboard</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Gráficos são gerados automaticamente para comparar e analisar os resultados.
                        </p>
                    </li>
                </ol>

                <p class="mt-8 text-sm text-muted">
                    O envio, a validação e a prévia dos dados já estão disponíveis. A geração de gráficos será habilitada na próxima etapa do projeto.
                </p>
            </div>
        </section>

        <section id="recursos" class="scroll-mt-16 py-16 sm:py-20">
            <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-brand">Recursos</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                        Análise acessível, do primeiro clique ao gráfico
                    </h2>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <article class="rounded-xl border border-line bg-surface p-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v12" />
                                <path d="m7 8 5-5 5 5" />
                                <path d="M5 21h14" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold">Sem barreiras de acesso</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Acesse pelo navegador e comece a analisar sem criar conta ou informar dados pessoais.
                        </p>
                    </article>

                    <article class="rounded-xl border border-line bg-surface p-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                                <path d="M14 3v5h5" />
                                <path d="M9 13h6" />
                                <path d="M9 17h6" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold">Leitura de planilhas</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Estrutura pensada para arquivos CSV e planilhas do Excel, com identificação das colunas.
                        </p>
                    </article>

                    <article class="rounded-xl border border-line bg-surface p-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 20V10" />
                                <path d="M10 20V4" />
                                <path d="M16 20v-7" />
                                <path d="M22 20H2" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold">Gráficos interativos</h3>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Visualizações geradas com Apache ECharts para explorar os dados de forma clara.
                        </p>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>Oqentra — análise de planilhas sem cadastro.</p>
            <p>Projeto em desenvolvimento · {{ date('Y') }}</p>
        </div>
    </footer>
@endsection
