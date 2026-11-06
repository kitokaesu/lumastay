# Relatório do Projeto — LumaStay

**Aplicação web de reservas de alojamento turístico**
Desenvolvida em PHP 8, HTML5, CSS3 e MySQL/MariaDB com PDO.

**Data de conclusão:** 6 de novembro de 2026
**Ambiente de validação:** PHP 8.3.16 · MySQL 8.4.3 · Windows (Laragon)

---

## Índice

1. [Introdução](#1-introdução)
2. [Âmbito e requisitos](#2-âmbito-e-requisitos)
3. [Arquitetura da aplicação](#3-arquitetura-da-aplicação)
4. [Modelo de dados](#4-modelo-de-dados)
5. [Fluxo de reserva e controlo de concorrência](#5-fluxo-de-reserva-e-controlo-de-concorrência)
6. [Segurança](#6-segurança)
7. [Validação e regras de negócio](#7-validação-e-regras-de-negócio)
8. [Área administrativa](#8-área-administrativa)
9. [Design, identidade visual e experiência](#9-design-identidade-visual-e-experiência)
10. [Acessibilidade e responsividade](#10-acessibilidade-e-responsividade)
11. [Testes e resultados](#11-testes-e-resultados)
12. [Decisões técnicas e dificuldades](#12-decisões-técnicas-e-dificuldades)
13. [Limitações](#13-limitações)
14. [Trabalho futuro](#14-trabalho-futuro)
15. [Conclusão](#15-conclusão)
16. [Anexos](#16-anexos)

---

## 1. Introdução

A LumaStay é uma aplicação web de reservas de alojamento turístico em Portugal. O projeto foi concebido como uma demonstração completa de um sistema real: catálogo público, pesquisa com verificação de disponibilidade, pedido de reserva, consulta posterior e uma área administrativa para gestão operacional.

O objetivo não foi construir apenas um conjunto de páginas estáticas, mas sim um fluxo funcional de ponta a ponta, com regras de negócio aplicadas no servidor, persistência em base de dados relacional, proteção contra os erros mais comuns de aplicações web e uma interface cuidada em desktop e telemóvel.

A aplicação foi desenvolvida em **PHP tradicional**, sem frameworks de front-end nem arquitetura SPA. Toda a lógica relevante — validação, disponibilidade, cálculo de preço, autenticação e transições de estado — é executada no servidor.

> **Nota de âmbito:** projeto académico de demonstração. Não processa pagamentos reais e não envia emails. Todos os alojamentos, hóspedes e reservas são fictícios.

---

## 2. Âmbito e requisitos

### 2.1 Requisitos funcionais

| Área | Requisito | Estado |
| --- | --- | --- |
| Público | Página inicial com destaque e pesquisa | Implementado |
| Público | Listagem com filtros por destino, datas, hóspedes, tipo e preço | Implementado |
| Público | Detalhe do alojamento com galeria e comodidades | Implementado |
| Público | Pedido de reserva com validação no servidor | Implementado |
| Público | Verificação de disponibilidade e cálculo de preço | Implementado |
| Público | Código único de reserva e página de confirmação | Implementado |
| Público | Consulta de reserva por código + email | Implementado |
| Admin | Login protegido, com bloqueio por tentativas | Implementado |
| Admin | Dashboard com indicadores e gráfico | Implementado |
| Admin | CRUD de alojamentos | Implementado |
| Admin | Gestão de reservas e transições de estado | Implementado |
| Admin | Listagem e ficha de hóspedes | Implementado |
| Admin | Calendário de disponibilidade | Implementado |
| Dados | Mínimo de 8 alojamentos, 10 hóspedes e 12 reservas | Implementado (8/10/16) |
| Qualidade | Testes automatizados executáveis | Implementado (29 testes) |
| Documentação | README, manual, relatório e apresentação | Implementado |

### 2.2 Requisitos técnicos

- PHP 8.0 ou superior, com `declare(strict_types=1)` nos ficheiros de aplicação.
- PDO com prepared statements e exceções ativas.
- Sessões para autenticação administrativa.
- Validação exclusivamente no servidor.
- Proteção CSRF em todos os formulários que alteram estado.
- Saída HTML escapada.
- `password_hash()` e `password_verify()` para credenciais.
- Sem React, Next.js, Vue, Angular, Node.js, Express, Vite ou SPA.
- Sem APIs externas pagas.
- Imagens locais e originais, com fallback.
- Interface responsiva e com identidade visual própria.

### 2.3 Identidade visual

A paleta definida para o projeto foi aplicada de forma consistente através de variáveis CSS:

| Token | Cor | Uso |
| --- | --- | --- |
| `--teal` | `#183A3A` | Cor principal, cabeçalhos, rodapé |
| `--green` | `#234B45` | Superfícies secundárias e estados |
| `--cream` | `#FAF9F6` | Fundo claro principal |
| `--sand` | `#EAE4D9` | Cartões, painéis e separadores |
| `--ink` | `#292D2C` | Texto principal |
| `--gold` | `#C49A6C` | Destaques e detalhes premium |

Foram introduzidas variantes acessíveis (`--gold-deep`, `--ink-soft`, `--ink-faint`) para garantir contraste mínimo WCAG AA sem perder a identidade cromática.

---

## 3. Arquitetura da aplicação

### 3.1 Visão geral

A aplicação segue uma arquitetura **clássica de renderização no servidor**, organizada em camadas simples e explícitas:

```text
Pedido HTTP
    │
    ▼
Página PHP (raiz ou admin/)
    │  require includes/auth.php
    │  valida entrada, aplica regras, consulta dados
    ▼
Funções de domínio (includes/functions.php)
    │  validação, disponibilidade, transições, formatação
    ▼
PDO (config/database.php)
    │  prepared statements + transações
    ▼
MySQL / MariaDB
    │
    ▼
HTML renderizado (includes/header.php, footer.php, ...)
```

Não existe um router central: cada página é um ponto de entrada autónomo que inclui as dependências comuns. Esta abordagem é adequada ao âmbito do projeto, mantém o código legível e facilita a inspeção por parte de quem avalia.

### 3.2 Estrutura de ficheiros

```text
lumastay/
├── index.php                     Página inicial
├── alojamentos.php               Listagem e pesquisa
├── alojamento.php                Detalhe
├── reservar.php                  Formulário + processamento transacional
├── reserva-confirmada.php        Confirmação
├── consultar-reserva.php         Consulta por código + email
├── login.php / logout.php        Autenticação
├── admin/                        Nove páginas administrativas
├── includes/                     Domínio, autenticação e parciais de UI
├── config/                       Configuração e ligação PDO
├── database/                     Esquema e dados de demonstração
├── assets/                       CSS, JavaScript e ilustrações SVG
├── tests/                        Suite de testes
└── docs/                         Documentação
```

### 3.3 Responsabilidades

| Camada | Ficheiros | Responsabilidade |
| --- | --- | --- |
| Apresentação | `*.php`, `includes/*header*`, `includes/*footer*` | Estrutura HTML, sem lógica de negócio complexa |
| Domínio | `includes/functions.php` | Validação, disponibilidade, estados, formatação, ícones |
| Autenticação | `includes/auth.php` | Sessão, CSRF, login, bloqueio, expiração |
| Persistência | `config/database.php` | Configuração e ligação PDO singleton |
| Dados | `database/*.sql` | Esquema, restrições e seed |
| Estilo | `assets/css/*.css` | Design tokens, componentes e breakpoints |
| Comportamento | `assets/js/main.js` | Menu móvel, validação visual, interações |

### 3.4 Configuração

A configuração privilegia variáveis de ambiente e permite um ficheiro local não versionado:

1. Variáveis de ambiente (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`, `APP_ENV`, `APP_URL`).
2. `config/config.local.php` (excluído do controlo de versões).
3. Valores por omissão adequados ao Laragon (`127.0.0.1:3306`, base `lumastay`, utilizador `root`, palavra-passe vazia).

A ligação PDO é criada uma única vez por pedido, com:

```php
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES   => false,
```

`ATTR_EMULATE_PREPARES => false` garante prepared statements nativos no MySQL, reduzindo a superfície de injeção SQL.

---

## 4. Modelo de dados

### 4.1 Diagrama relacional

```text
┌──────────────────┐          ┌──────────────────┐
│   admin_users    │          │  accommodations  │
├──────────────────┤          ├──────────────────┤
│ id (PK)          │          │ id (PK)          │
│ name             │          │ name             │
│ email (UQ)       │          │ slug (UQ)        │
│ password_hash    │          │ description      │
│ created_at       │          │ location         │
└──────────────────┘          │ address          │
                              │ type             │
                              │ price_per_night  │
                              │ capacity         │
                              │ bedrooms         │
                              │ bathrooms        │
                              │ amenities (JSON) │
                              │ image_url        │
                              │ rating           │
                              │ review_count     │
                              │ active           │
                              │ featured         │
                              │ created_at       │
                              │ updated_at       │
                              └────────┬─────────┘
                                       │ 1
                                       │
                                       │ N
                              ┌────────▼─────────┐
                              │   reservations   │
                              ├──────────────────┤
                              │ id (PK)          │
                              │ reservation_code │
                              │ accommodation_id │
                              │ guest_id         │
                              │ check_in         │
                              │ check_out        │
                              │ guests_count     │
                              │ price_per_night  │
                              │ total_price      │
                              │ status           │
                              │ created_at       │
                              │ updated_at       │
                              └────────┬─────────┘
                                       │ N
                                       │
                                       │ 1
                              ┌────────▼─────────┐
                              │      guests      │
                              ├──────────────────┤
                              │ id (PK)          │
                              │ full_name        │
                              │ email (UQ)       │
                              │ phone            │
                              │ created_at       │
                              └──────────────────┘
```

### 4.2 Tabelas

#### `accommodations`

Representa cada unidade de alojamento.

- `slug` único, usado para identificação legível.
- `amenities` em JSON para acomodar listas variáveis sem tabelas auxiliares.
- `price_per_night` em `DECIMAL(10,2)`, evitando erros de vírgula flutuante.
- Índices em `location`, `active`, `price_per_night` e `type` para os filtros mais frequentes.
- Restrições `CHECK` para preço positivo, capacidade entre 1 e 30, avaliação entre 0 e 5 e flags binárias.

#### `guests`

Representa o contacto do hóspede.

- `email` único: um hóspede é identificado pelo email.
- Quando um pedido de reserva usa um email já existente, o nome e o telefone são atualizados em vez de criar duplicado.

#### `reservations`

Representa o pedido de reserva.

- `reservation_code` único, em ASCII, com formato `LS-XXXXXX`.
- Chaves estrangeiras com `ON DELETE RESTRICT`, protegendo o histórico.
- `price_per_night` e `total_price` guardados no momento da criação, para que alterações futuras de preço não reescrevam o passado.
- `status` limitado por `CHECK` a `pendente`, `confirmada`, `cancelada` e `concluida`.
- Índice composto `(accommodation_id, check_in, check_out)` para as consultas de sobreposição.

#### `admin_users`

Tabela adicional, necessária para a autenticação administrativa.

- `password_hash` com bcrypt.
- Não faz parte do modelo de negócio público e não é exposta em nenhuma página.

### 4.3 Integridade

A integridade é garantida em três níveis:

1. **Base de dados:** chaves estrangeiras, unicidade e restrições `CHECK`.
2. **Aplicação:** validação antes de qualquer escrita.
3. **Concorrência:** transações e bloqueios de linha no fluxo de reserva.

---

## 5. Fluxo de reserva e controlo de concorrência

### 5.1 Sequência

```text
Visitante escolhe alojamento e datas
        │
        ▼
GET reservar.php
        │  valida parâmetros de URL
        │  calcula noites e total estimado
        │  verifica disponibilidade (pré-visualização)
        ▼
Preenche formulário e submete (POST + CSRF)
        │
        ▼
Validação completa no servidor
        │  datas, capacidade, nome, email, telefone, condições
        ▼
BEGIN TRANSACTION
        │
        ├─ SELECT alojamento ... FOR UPDATE
        │     (serializa pedidos para o mesmo alojamento)
        │
        ├─ SELECT reservas sobrepostas ... FOR UPDATE
        │     status IN ('pendente','confirmada')
        │     check_in < novo_check_out
        │     check_out > novo_check_in
        │
        ├─ Se existir sobreposição → ROLLBACK + erro
        │
        ├─ SELECT / INSERT / UPDATE hóspede por email
        │
        ├─ INSERT reserva (estado 'pendente', preço congelado)
        │
        └─ COMMIT
        │
        ▼
Redirect para reserva-confirmada.php?codigo=...&email=...
```

### 5.2 Porque é necessária uma transação

Sem controlo de concorrência, dois visitantes poderiam submeter o mesmo intervalo em simultâneo: ambos passariam na verificação prévia e ambos inseririam uma reserva. O resultado seria uma dupla reserva.

A solução implementada combina:

- **`SELECT ... FOR UPDATE` sobre o alojamento**, que funciona como ponto de serialização por unidade;
- **segunda verificação de sobreposição dentro da transação**;
- **`COMMIT` apenas se não existir conflito**;
- **`ROLLBACK` e mensagem genérica** em caso de conflito.

O teste de integração confirmou o comportamento: uma segunda submissão para as mesmas datas foi rejeitada e a base de dados manteve exatamente uma reserva.

### 5.3 Regra de sobreposição

Duas reservas colidem quando:

```text
nova.check_in  < existente.check_out
E
nova.check_out > existente.check_in
```

Esta formulação trata corretamente estadias encadeadas: uma reserva que termina a 4 de junho não colide com outra que começa a 4 de junho.

Apenas os estados `pendente` e `confirmada` bloqueiam datas. Reservas `cancelada` e `concluida` não bloqueiam, o que permite reutilizar o calendário e manter o histórico.

### 5.4 Preço

O preço é sempre calculado no servidor:

```text
total = noites × price_per_night (do alojamento bloqueado na transação)
```

O valor é arredondado a duas casas decimais e guardado na reserva. O navegador apenas apresenta uma estimativa; nunca define o valor final.

### 5.5 Código de reserva

O código é gerado no servidor com caracteres seguros, no formato `LS-XXXXXX`, e validado contra a base de dados. A coluna é única, pelo que uma colisão é impossível ao nível do esquema. Em caso de colisão excecional, a transação falha e pode ser repetida.

---

## 6. Segurança

### 6.1 Injeção SQL

Todas as consultas que recebem dados de utilizador usam prepared statements com parâmetros nomeados. Não há interpolação de valores em SQL. As únicas partes dinâmicas são cláusulas construídas internamente a partir de listas fechadas (por exemplo, ordenação), nunca a partir de texto livre.

### 6.2 XSS

Toda a saída é escapada com:

```php
htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
```

A função `e()` é usada de forma sistemática nos templates. Conteúdo rico não é renderizado como HTML; descrições e nomes passam sempre por escape.

### 6.3 CSRF

- Token de 32 bytes gerado com `random_bytes()` e guardado na sessão.
- Campo oculto em todos os formulários POST.
- Validação com `hash_equals()` para evitar ataques de temporização.
- Em caso de falha, a operação é interrompida com mensagem genérica e redirecionamento; **nenhuma alteração é aplicada**.

Foi testado um POST administrativo sem token: a resposta foi um redirecionamento e o registo permaneceu inalterado.

### 6.4 Autenticação

- Palavras-passe com `password_hash()` (bcrypt) e verificação com `password_verify()`.
- Rehash transparente quando o algoritmo ou custo mudam.
- Regeneração do ID de sessão após login e logout.
- Cookie de sessão com `HttpOnly`, `SameSite=Lax` e `Secure` quando o pedido é HTTPS.
- Expiração administrativa após **2 horas** de inatividade.
- Bloqueio após **5 tentativas falhadas em 10 minutos**, contadas por sessão.
- Mensagem de erro genérica no login, sem revelar se o email existe.

### 6.5 Controlo de acesso

Todas as páginas administrativas começam por:

```php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
```

`require_admin()` verifica a sessão, a expiração e atualiza o timestamp de atividade. Visitantes sem sessão são redirecionados para o login com uma mensagem e com o URL pretendido guardado.

### 6.6 Sessão e cabeçalhos

- Nome de sessão próprio (`LumaStaySess`), evitando colisões com outras aplicações no mesmo servidor.
- `session_regenerate_id(true)` nos momentos críticos.
- Nenhuma credencial é escrita em HTML ou JavaScript.
- Erros técnicos são registados com `error_log()` e não mostrados ao visitante.

### 6.7 Proteção de dados e histórico

- A eliminação de alojamentos é bloqueada quando existem reservas associadas, sugerindo desativação.
- Reservas canceladas permanecem na base de dados.
- Chaves estrangeiras com `ON DELETE RESTRICT`.
- A consulta pública exige código **e** email, sem revelar a existência do código quando o email não corresponde.

---

## 7. Validação e regras de negócio

### 7.1 Reserva

| Regra | Comportamento |
| --- | --- |
| Data de entrada válida | Formato `AAAA-MM-DD` e data real |
| Entrada no passado | Rejeitada |
| Saída anterior ou igual à entrada | Rejeitada |
| Estadia superior a 60 noites | Rejeitada |
| Hóspedes abaixo de 1 | Rejeitado |
| Hóspedes acima da capacidade | Rejeitado |
| Nome inválido | Rejeitado (3–150 caracteres, letras e pontuação limitada) |
| Email inválido | Rejeitado |
| Telefone inválido | Rejeitado (9–15 dígitos, indicativo opcional) |
| Condições não aceites | Rejeitado |
| Datas indisponíveis | Rejeitado, sem duplicar reserva |

### 7.2 Alojamento

| Regra | Comportamento |
| --- | --- |
| Nome | 3–150 caracteres |
| Descrição | 30–5000 caracteres |
| Slug | Único; gerado automaticamente se vazio |
| Preço | Positivo, até duas casas decimais |
| Capacidade | 1–30 |
| Quartos | 0–20 |
| Casas de banho | 1–20 |
| Avaliação | 0–5 |
| Comodidades | Lista normalizada e guardada em JSON |
| Imagem | Caminho local `assets/` ou URL `http(s)` |
| Inativo em destaque | Rejeitado |

### 7.3 Transições de estado

```text
pendente ──confirmar──► confirmada ──concluir──► concluida
    │                        │
    └──────cancelar──────────┴──► cancelada
```

Regras aplicadas:

- Confirmar: apenas a partir de `pendente`, com nova verificação de sobreposição.
- Cancelar: apenas a partir de `pendente` ou `confirmada`.
- Concluir: apenas a partir de `confirmada` e só depois da data de saída.
- Qualquer outra transição é rejeitada sem alterar dados.
- O `UPDATE` inclui o estado atual na cláusula `WHERE`, impedindo alterações concorrentes inesperadas.

### 7.4 Disponibilidade

A função central é:

```php
accommodation_is_available($id, $checkIn, $checkOut, $excludeReservationId = null)
```

O parâmetro de exclusão permite validar a confirmação de uma reserva sem que ela colida consigo própria.

---

## 8. Área administrativa

### 8.1 Dashboard

Indicadores calculados em SQL:

- Receita prevista: soma de reservas `pendente` e `confirmada` com saída futura.
- Total de reservas e reservas pendentes.
- Total de hóspedes.
- Ocupação a 30 dias: noites ocupadas / (alojamentos ativos × 30), limitada a 100%.
- Reservas por mês nos últimos 6 meses.
- Distribuição por estado.
- Próximas entradas e saídas num horizonte de 30 dias.

O gráfico é construído em **CSS puro**, com alturas calculadas em PHP. Não há bibliotecas de gráficos, dependências externas ou chamadas de rede.

### 8.2 Alojamentos

- Listagem com pesquisa, filtro por estado e tipo, e ordenação.
- Criação e edição com pré-visualização de imagem e validação partilhada.
- Ativação/desativação com efeito imediato no site público.
- Eliminação condicionada à ausência de reservas.

### 8.3 Reservas

- Filtros por estado, alojamento e texto.
- Cartões de resumo por estado.
- Detalhe com linha temporal, dados do hóspede e valores.
- Ações contextuais: confirmar, cancelar, concluir.

### 8.4 Hóspedes

- Pesquisa por nome, email ou telefone.
- Indicadores de reservas, noites e valor acumulado.
- Ficha individual com histórico completo e ligação às reservas.

### 8.5 Disponibilidade

- Visão agregada por mês e por alojamento.
- Taxa de ocupação, noites ocupadas, número de reservas e valor.
- Calendário diário com legenda: disponível, pendente, confirmada, check-out e dia atual.
- Navegação entre meses.
- Respeita a mesma regra de estados do site público.

---

## 9. Design, identidade visual e experiência

### 9.1 Princípios

- **Premium sem excesso:** muito espaço branco, tipografia editorial e detalhes dourados discretos.
- **Hierarquia clara:** títulos grandes, texto de apoio contido, ações primárias distintas.
- **Consistência:** os mesmos tokens, componentes e padrões no site público e na administração.
- **Originalidade:** ilustrações SVG criadas para o projeto, sem banco de imagens.

### 9.2 Componentes

- Cabeçalho fixo com navegação e menu móvel em gaveta.
- Cartões de alojamento com imagem, tipo, avaliação, localização, comodidades, preço e ação.
- Painel de pesquisa reutilizável na página inicial e na listagem.
- Formulários com mensagens de erro junto ao contexto e resumo no topo.
- Tabelas administrativas com estados, ações e resposta em ecrãs pequenos.
- Sistema de flashes para feedback de sucesso, aviso e erro.

### 9.3 Ilustrações

Foram criadas oito ilustrações SVG originais, uma por alojamento, além de `hero.svg`, `logo-mark.svg` e `fallback.svg`. Todas usam formas, gradientes e paleta coerentes com a identidade. A aplicação recorre a `fallback.svg` quando um caminho de imagem é inválido.

### 9.4 JavaScript

O JavaScript é opcional e progressivo:

- abre e fecha o menu móvel;
- melhora a validação visual dos formulários;
- atualiza resumos de preço;
- gere diálogos de confirmação.

Nenhuma regra de negócio depende de JavaScript. Com JavaScript desativado, o fluxo principal continua funcional.

---

## 10. Acessibilidade e responsividade

### 10.1 Acessibilidade

- Estrutura semântica com `header`, `nav`, `main`, `section`, `article` e `footer`.
- Ligação "Saltar para o conteúdo".
- Textos alternativos em todas as imagens.
- Estados de foco visíveis.
- Menus com `aria-expanded` e fecho por `Escape`.
- Contraste ajustado para WCAG AA.
- Formulários com `label` associado e mensagens de erro legíveis.
- Nenhuma informação transmitida apenas por cor.

### 10.2 Resultados Lighthouse

Executado no Chrome, em desktop, sobre o servidor local:

| Página | Acessibilidade | Boas práticas | SEO |
| --- | ---: | ---: | ---: |
| Início | 100 | 100 | 100 |
| Alojamentos | 100 | 100 | 100 |
| Detalhe | 100 | 100 | 100 |
| Reservar | 100 | 100 | 100 |
| Consultar reserva | 100 | 100 | 100 |
| Login | 100 | 100 | 100 |
| Dashboard | 100 | 100 | 100 |
| Alojamentos (admin) | 100 | 100 | 100 |
| Reservas (admin) | 100 | 100 | 100 |
| Hóspedes (admin) | 100 | 100 | 100 |
| Disponibilidade (admin) | 100 | 100 | 100 |

Correções efetuadas durante a auditoria:

- escurecimento de `--gold-deep` de `#A97D4F` para `#8A6234`;
- escurecimento de `--ink-faint` de `#858C87` para `#6B736F`;
- aumento do contraste do rodapé;
- remoção de `aria-label` redundante nos cartões, que causava desalinhamento entre nome acessível e texto visível;
- ajuste do wordmark no rodapé para `--gold-soft`.

### 10.3 Responsividade

Verificação com emulação real de dispositivo via Chrome DevTools Protocol:

| Viewport | Resultado |
| --- | --- |
| 390 × 844 (telemóvel) | Sem overflow horizontal em todas as páginas públicas e administrativas |
| 1440 × 900 (desktop) | Sem overflow horizontal |
| Menu público móvel | Abre e fecha corretamente |
| Menu administrativo | Gaveta funcional abaixo de 900 px, com overlay |

O menu administrativo passa a gaveta em ecrãs estreitos, mantendo o acesso a todas as secções.

---

## 11. Testes e resultados

### 11.1 Suite automatizada

O ficheiro `tests/run-tests.php` contém 29 testes organizados em quatro grupos:

1. **Funções utilitárias** (13 testes)
   - escape HTML, formatação monetária e de datas;
   - validação de datas, emails e telefones;
   - cálculo de noites, slugs, excertos e comodidades;
   - resolução de imagens, tipos, estados e ícones.

2. **Validação do formulário de alojamento** (3 testes)
   - formulário válido sem erros;
   - acumulação de erros em formulário inválido;
   - rejeição de slug duplicado.

3. **Integridade da base de dados** (11 testes)
   - ligação PDO e driver MySQL;
   - mínimos de dados de demonstração;
   - presença dos quatro estados;
   - coerência entre noites, preço/noite e total;
   - disponibilidade com reservas canceladas, concluídas, pendentes e confirmadas;
   - exclusão da própria reserva;
   - códigos únicos no formato correto;
   - rejeição de transições inválidas;
   - credenciais de demonstração com hash válido.

4. **Verificações HTTP** (2 testes)
   - páginas públicas respondem `200`;
   - páginas administrativas redirecionam visitantes para o login (`302`).

### 11.2 Resultado real

Execução de 6 de novembro de 2026:

```text
==============================================================
Resultado: 29 passaram, 0 falharam, 0 ignorados (total 29)
Todos os testes executados passaram.
==============================================================
```

### 11.3 Verificações manuais e de integração

Além da suite, foram executados fluxos reais com sessão e CSRF:

| Verificação | Resultado |
| --- | --- |
| Criação de reserva (3 noites × 185,00 €) | Reserva `LS-A3MH3H`, total `555,00 €`, estado `pendente` |
| Reenvio das mesmas datas | Rejeitado; sem duplicado |
| Consulta por código + email | Apresenta reserva e estado |
| Consulta com email errado | Mensagem genérica, sem revelar dados |
| Confirmação de reserva no admin | Estado alterado para `confirmada` |
| Cancelamento de reserva no admin | Estado alterado para `cancelada` |
| Criação de alojamento | Registado e visível na listagem |
| Edição de alojamento | Preço e destaque atualizados |
| Ativação/desativação | Estado público alterado |
| Eliminação de alojamento sem reservas | Permitida |
| Eliminação de alojamento com reservas | Bloqueada com mensagem |
| Acesso anónimo às páginas admin | Redirecionamento para login |
| POST admin sem CSRF | Rejeitado, sem alteração |
| 5 tentativas de login falhadas | Bloqueio ativo; login correto recusado enquanto bloqueado |
| Sintaxe PHP | 28 ficheiros, 0 erros |

### 11.4 Estado final da base de dados

Após os testes, a base foi reposta no estado de demonstração:

```text
Alojamentos: 8
Hóspedes:    10
Reservas:    16
  Pendentes:  4
  Confirmadas: 7
  Concluídas:  3
  Canceladas:  2
```

### 11.5 Reprodução dos testes

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_NAME = 'lumastay'
$env:DB_USER = 'root'
$env:DB_PASSWORD = ''
$env:APP_TEST_URL = 'http://localhost/lumastay'

php tests/run-tests.php
```

---

## 12. Decisões técnicas e dificuldades

### 12.1 PHP tradicional em vez de framework

**Decisão:** usar PHP puro, sem Laravel, Symfony ou equivalente.

**Justificação:** o objetivo incluía demonstrar domínio dos fundamentos — PDO, sessões, validação, CSRF e organização de código — sem esconder esses mecanismos atrás de abstrações. O resultado é um projeto inspecionável, com poucas dependências e arranque imediato.

**Custo:** alguma repetição de código de inicialização e ausência de funcionalidades oferecidas por frameworks (migrações, ORM, routing avançado). A repetição foi mitigada com includes partilhados.

### 12.2 Sem ORM

**Decisão:** SQL explícito com PDO.

**Justificação:** as consultas são poucas e bem definidas; SQL explícito torna a lógica de disponibilidade e os indicadores do dashboard transparentes. Além disso, permite usar `FOR UPDATE` de forma clara.

### 12.3 Concorrência

**Problema:** duas reservas simultâneas para as mesmas datas.

**Solução:** transação com bloqueio do alojamento e segunda verificação de sobreposição.

**Alternativa considerada:** restrição de exclusão ao nível da base de dados. O MySQL não oferece `EXCLUDE` como o PostgreSQL, e uma restrição por intervalo exigiria lógica adicional. A solução transacional é suficiente para o volume e o âmbito do projeto.

### 12.4 Preço congelado

**Decisão:** guardar `price_per_night` e `total_price` na reserva.

**Justificação:** o valor acordado no momento da reserva não deve mudar quando o catálogo é atualizado. Isto preserva a integridade histórica e financeira.

### 12.5 Disponibilidade e estados

**Decisão:** apenas `pendente` e `confirmada` bloqueiam datas.

**Justificação:** uma reserva cancelada liberta o calendário; uma reserva concluída pertence ao passado. Manter esta regra igual no site, no admin e nos testes evita inconsistências.

### 12.6 Imagens SVG originais

**Decisão:** criar ilustrações vetoriais em vez de usar fotografias de terceiros.

**Justificação:** evita dependências externas, questões de licenciamento e falhas de rede. Os SVG são leves, nítidos em qualquer resolução e coerentes com a paleta.

**Dificuldade encontrada:** uma camada de textura com um retângulo preto em tamanho real escurecia todas as ilustrações. O problema foi identificado por inspeção visual, confirmado no SVG isolado e corrigido nos oito ficheiros.

### 12.7 Acessibilidade

**Dificuldade:** o dourado original não atingia contraste suficiente em texto pequeno.

**Solução:** criar variantes mais escuras para texto e manter o dourado claro apenas para fundos e detalhes. O processo foi guiado por auditorias Lighthouse sucessivas, até 100 em todas as páginas testadas.

### 12.8 Codificação de caracteres

**Dificuldade:** a importação de SQL através de piping no PowerShell corrompeu acentos.

**Solução:** importar com o cliente MySQL e `source`, a partir de cópias byte-a-byte, com `--default-character-set=utf8mb4`. O procedimento ficou documentado no README e no manual.

---

## 13. Limitações

- **Sem pagamentos:** não há integração com gateway de pagamento; todas as reservas são pedidos.
- **Sem emails transacionais:** a confirmação é mostrada no ecrã e guardada na base de dados.
- **Sem upload de imagens:** a imagem é indicada por caminho local ou URL.
- **Gestão de administradores apenas por base de dados:** não existe interface para criar ou editar utilizadores administrativos.
- **Sem reservas manuais no admin:** o calendário mostra ocupação, mas a criação de reservas pela equipa não está incluída.
- **Bloqueio de login por sessão:** o contador de tentativas está na sessão, não por conta ou por IP. É adequado a demonstração, mas insuficiente para produção distribuída.
- **Sem migrações:** o esquema é recriado por `schema.sql`, adequado ao âmbito académico.
- **Sem cache nem otimização de produção:** não há CDN, compressão configurada, cabeçalhos de cache ou monitorização.
- **Acessibilidade validada por Lighthouse:** não substitui testes com utilizadores reais e leitores de ecrã.

---

## 14. Trabalho futuro

1. **Pagamentos:** integração com gateway (Stripe, easypay ou equivalente) e estados de pagamento.
2. **Emails:** confirmação, lembrete de check-in e cancelamento.
3. **Upload de imagens:** gestão de galeria por alojamento.
4. **Reservas manuais:** criação e edição pela equipa administrativa.
5. **Gestão de utilizadores:** perfis, permissões e auditoria de ações.
6. **Rate limiting por conta/IP:** complementar o bloqueio por sessão.
7. **Testes end-to-end:** automação do navegador para os fluxos completos.
8. **Internacionalização:** português e inglês.
9. **Produção:** HTTPS obrigatório, cabeçalhos de segurança, backups, logs estruturados e monitorização.
10. **Acessibilidade:** testes com NVDA/VoiceOver e utilizadores reais.

---

## 15. Conclusão

A LumaStay cumpre os objetivos definidos: é uma aplicação funcional, com fluxo de reserva completo, área administrativa operacional, base de dados relacional consistente e documentação em português.

Os aspetos mais relevantes do trabalho foram:

- **Correção do domínio:** disponibilidade, preços e estados são calculados e validados no servidor.
- **Concorrência tratada:** a transação com bloqueio impede duplicação de reservas.
- **Segurança transversal:** PDO, escape, CSRF, sessões e hashing aplicados de forma sistemática.
- **Qualidade verificada:** 29 testes automatizados, fluxos manuais, sintaxe validada, Lighthouse 100 e responsividade comprovada em viewports reais.
- **Identidade própria:** paleta, tipografia e ilustrações originais, sem dependências externas.

O projeto demonstra que é possível construir uma aplicação web completa e segura com tecnologia simples, desde que as regras de negócio e os limites de confiança estejam claramente definidos.

---

## 16. Anexos

### 16.1 Credenciais de demonstração

```text
Email:        admin@lumastay.pt
Palavra-passe: LumaStay2026!
```

### 16.2 Comandos úteis

```bash
# Importar base de dados
mysql --default-character-set=utf8mb4 -u root -p -e "SOURCE database/schema.sql;"
mysql --default-character-set=utf8mb4 -u root -p -e "SOURCE database/seed.sql;"

# Servidor embutido do PHP
php -S 127.0.0.1:8088 -t .

# Testes
php tests/run-tests.php

# Verificar sintaxe
find . -name "*.php" -exec php -l {} \;
```

### 16.3 Documentos relacionados

- [`../README.md`](../README.md) — instalação, configuração e visão geral.
- [`manual-utilizacao.md`](manual-utilizacao.md) — manual do visitante e do administrador.
- [`apresentacao-projeto.md`](apresentacao-projeto.md) — guião de apresentação e demonstração.
