<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="O DataCanvas transforma planilhas em dashboards com gráficos automáticos. Sem cadastro e sem login.">
    <title>@yield('title', 'DataCanvas — Dashboards a partir de planilhas')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col">
    <a
        href="#conteudo"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
    >
        Ir para o conteúdo
    </a>

    @yield('content')
</body>
</html>
