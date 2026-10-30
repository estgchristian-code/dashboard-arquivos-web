# DataCanvas

Site público que transforma planilhas e arquivos tabulares em dashboards com gráficos automáticos, sem cadastro ou login.

> **Estado atual:** página inicial e **importação real de arquivos tabulares** com validação no servidor, leitura dos formatos suportados e prévia dos dados. A geração automática de gráficos e as exportações ainda **não** estão implementadas.

## Stack

| Camada | Tecnologia |
| --- | --- |
| Backend | PHP 8.3 · Laravel 13 |
| Componentes | Livewire 4 |
| Estilos | Tailwind CSS 4 (plugin oficial do Vite) |
| Frontend build | Vite 7 · Node.js |
| Gráficos | Apache ECharts 6 |
| Banco | MySQL (sessão/cache da aplicação; importações não usam banco) |
| Leitura de planilhas | PhpSpreadsheet 5.10 |

## Requisitos

- PHP 8.3+ com as extensões `pdo_mysql`, `mbstring`, `zip`, `xml`, `gd` e `fileinfo`
- Composer 2
- Node.js 20+ e npm
- MySQL 8+

## Instalação local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Crie um banco MySQL chamado `datacanvas` (ou ajuste `DB_DATABASE` no `.env`), preencha `DB_USERNAME` e `DB_PASSWORD` e execute:

```bash
php artisan migrate
npm run build
php artisan serve
```

Para desenvolvimento com recarregamento automático:

```bash
composer dev
```

## Formatos suportados

| Formato | Extensões | Leitura |
| --- | --- | --- |
| CSV | `.csv` | Delimitador detectado no conteúdo (vírgula, ponto e vírgula, tabulação ou barra vertical) |
| TSV | `.tsv` | Delimitador detectado no conteúdo |
| XLSX | `.xlsx` | Primeira planilha, via PhpSpreadsheet |
| XLS | `.xls` | Primeira planilha, via PhpSpreadsheet |
| JSON tabular | `.json` | Lista de objetos ou lista de listas, sem valores aninhados |

O formato é decidido pelo **conteúdo real** (assinatura binária, estrutura e delimitador), não pelo MIME informado pelo navegador. A extensão é usada apenas para recusar nomes claramente incompatíveis. Codificação UTF-8, UTF-16 com BOM e Windows-1252 são reconhecidas; cabeçalhos, tipos prováveis, células vazias, linhas e colunas são detectados automaticamente.

## Limites e configuração

Os limites são aplicados no servidor e configurados por variáveis de ambiente (veja `.env.example`):

| Configuração | Padrão |
| --- | --- |
| Tamanho máximo do arquivo | 10 MB |
| Linhas de dados | 10.000 |
| Colunas | 100 |
| Células | 200.000 |
| Tempo de processamento | 20 s |
| Linhas na prévia | 25 |
| Retenção da importação | 24 h |
| Uploads por minuto / hora | 5 / 20 |

As mesmas opções ficam em `config/datacanvas.php`. O rate limiting pode ser desativado com `DATACANVAS_RATE_LIMIT_ENABLED=false`.

## Armazenamento e limpeza

- Arquivos e prévias ficam no disco privado `imports` (`storage/app/imports`), fora de `public/`, sem URL pública.
- Cada importação recebe um identificador aleatório de 32 caracteres hexadecimais. O arquivo original é gravado com nome aleatório; o nome enviado é usado apenas como rótulo de exibição.
- O proprietário é identificado pelo hash SHA-256 de um token aleatório mantido na sessão do visitante. Outro visitante não consegue localizar nem remover a importação.
- Metadados, linhas normalizadas e prévia são gravados como JSON no próprio diretório da importação. **Não há tabela nem migração** para esse fluxo.
- Importações expiradas são removidas no acesso e pela rotina agendada:

```bash
php artisan datacanvas:cleanup-imports
php artisan datacanvas:cleanup-imports --dry-run
```

O agendamento roda a cada hora (`routes/console.php`). Uploads temporários antigos do Livewire também são limpos.

## Segurança

- Validação de extensão, tamanho e conteúdo no servidor; dados do navegador não são confiáveis.
- XLSX é inspecionado antes da leitura: entradas com path traversal, criptografia, proporção de compressão abusiva (ZIP bomb), `DOCTYPE`/entidades externas, macros e executáveis são recusados.
- PhpSpreadsheet é usado com `setReadDataOnly(true)`; fórmulas, macros e conteúdo incorporado não são executados.
- Nenhum conteúdo de planilha é gravado em log.
- A prévia é renderizada com escape do Blade; payloads de script permanecem apenas como texto.
- Formulários Livewire usam a proteção CSRF padrão do Laravel.
- O fluxo não usa `eval` nem executa qualquer conteúdo do arquivo.

## Verificações

```bash
php artisan test
npm run build
vendor/bin/pint --test
composer validate
```

Os testes usam SQLite em memória e drivers `array`/`sync` definidos em `phpunit.xml`, portanto não dependem do MySQL. A aplicação em execução usa MySQL para sessão, cache e fila; sem um MySQL configurado, a página inicial não será servida corretamente.

Os testes de importação usam arquivos reais em `tests/Fixtures/imports` (CSV, TSV, XLSX, XLS, JSON, arquivos corrompidos, ZIP malicioso, macro e payload XSS), cobrindo formatos válidos, limites, isolamento entre visitantes e limpeza.

## Estrutura relevante

```text
app/Livewire/ImportUploader.php                     # Componente fino de upload/prévia
app/Services/Imports/ImportService.php              # Orquestração do upload
app/Services/Imports/Support/FormatDetector.php     # Detecção real de formato
app/Services/Imports/Support/TableNormalizer.php    # Cabeçalhos, tipos e limites
app/Services/Imports/Support/ZipGuard.php           # Proteções para XLSX/ZIP
app/Services/Imports/Readers/                       # CSV/TSV, JSON e planilhas
app/Services/Imports/ImportRepository.php           # Persistência privada e limpeza
app/Services/Imports/VisitorIdentity.php            # Token anônimo da sessão
app/Services/Imports/ImportRateLimiter.php          # Rate limiting por visitante
app/Console/Commands/CleanupImports.php             # Limpeza agendada
resources/views/livewire/import-uploader.blade.php  # Interface de envio e prévia
resources/views/home.blade.php                      # Landing page em português
```

## Identidade visual

- Fundo: `#F9FAFB`
- Superfícies: `#FFFFFF`
- Azul de destaque: `#2F4B7C`
- Texto: `#111827`
- Sem gradientes

## Segurança e escopo

- Não há autenticação: o produto é público por definição.
- `.env`, arquivos enviados, dados temporários e artefatos de build não são versionados.
- Nenhuma credencial real é mantida em arquivos versionados.
- Esta base **não** deve ser considerada pronta para produção. Ainda faltam a geração de gráficos, revisão de infraestrutura, políticas de retenção definitivas e testes de carga.
