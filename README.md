# LumaStay

Aplicação web de reservas de alojamento turístico em Portugal, desenvolvida em PHP tradicional, HTML5, CSS3 e MySQL/MariaDB com PDO.

A LumaStay apresenta um catálogo curado de casas, villas, quintas, apartamentos e suites, permite pesquisar por destino, datas e número de hóspedes, concluir pedidos de reserva com validação no servidor e consultar o estado de uma reserva através de um código único. Inclui ainda uma área administrativa completa para gestão do catálogo, reservas, hóspedes e disponibilidade.

> **Projeto académico de demonstração.** Não são efetuados pagamentos reais. Todas as reservas, hóspedes e alojamentos são dados fictícios.

---

## Índice

- [Funcionalidades](#funcionalidades)
- [Stack técnica](#stack-técnica)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Dados de demonstração](#dados-de-demonstração)
- [Credenciais de administração](#credenciais-de-administração)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Testes](#testes)
- [Segurança implementada](#segurança-implementada)
- [Acessibilidade e responsividade](#acessibilidade-e-responsividade)
- [Documentação](#documentação)
- [Limitações conhecidas](#limitações-conhecidas)

---

## Funcionalidades

### Site público

- Página inicial com destaque de alojamentos, destinos e proposta de valor.
- Listagem de alojamentos com pesquisa por destino, intervalo de datas, número de hóspedes, tipo, preço mínimo/máximo e ordenação.
- Página de detalhe com galeria local, descrição, comodidades, capacidade, quartos, casas de banho e formulário de reserva.
- Pedido de reserva com validação de datas, capacidade, nome, email, telefone e aceitação de condições.
- Verificação de disponibilidade no servidor e nova verificação dentro de uma transação com bloqueio de linha (`SELECT ... FOR UPDATE`).
- Geração de código único de reserva (`LS-XXXXXX`) e cálculo do preço no momento da reserva.
- Página de confirmação com resumo, código e dados do hóspede.
- Consulta de reserva por código + email, com apresentação do estado atual.
- Login de administração, com proteção contra tentativas repetidas.

### Área administrativa (protegida por sessão)

- **Dashboard:** indicadores de receita prevista, reservas, hóspedes, ocupação a 30 dias, gráfico de reservas por mês, distribuição por estado e próximas entradas/saídas.
- **Alojamentos:** listagem com pesquisa, filtros e ordenação; criação, edição, ativação/desativação e eliminação condicionada (bloqueada quando existem reservas associadas).
- **Reservas:** listagem com filtros por estado, alojamento e pesquisa; detalhe com linha temporal de estado; confirmação, cancelamento e conclusão com regras de transição.
- **Hóspedes:** listagem e ficha individual com histórico de reservas, total de noites e valor acumulado.
- **Disponibilidade:** calendário mensal por alojamento, indicadores de ocupação, reservas ativas e legenda de estados; reservas canceladas e concluídas não bloqueiam datas.

### Qualidade

- Validação de dados exclusivamente no servidor (a validação JavaScript é apenas uma ajuda visual).
- Saída HTML sempre escapada.
- CSRF em todos os formulários que alteram estado.
- Imagens locais originais em SVG, com fallback automático.
- Layout responsivo (telemóvel, tablet, portátil e ecrãs grandes).
- Gráfico do dashboard em CSS puro, sem bibliotecas externas.
- Lighthouse: **100** em acessibilidade, boas práticas e SEO nas páginas públicas e administrativas testadas.

---

## Stack técnica

| Camada | Tecnologia |
| --- | --- |
| Servidor | PHP 8.0+ (testado em PHP 8.3.16) |
| Base de dados | MySQL 8.0+ / MariaDB 10.4+ (testado em MySQL 8.4.3) |
| Acesso a dados | PDO com prepared statements e exceções |
| Front-end | HTML5, CSS3 e JavaScript sem dependências |
| Imagens | SVG originais criados para o projeto |
| Servidor web | Apache (Laragon) ou servidor embutido do PHP para testes |

**Não são utilizados** React, Next.js, Vue, Angular, Node.js, Express, Vite ou arquitetura SPA. O projeto não depende de APIs externas pagas nem de bibliotecas de front-end.

---

## Requisitos

- PHP 8.0 ou superior com as extensões:
  - `pdo_mysql`
  - `mbstring` (recomendado)
  - `fileinfo`
  - `openssl` (recomendado)
- MySQL 8.0+ ou MariaDB 10.4+
- Apache com `mod_rewrite` (opcional; o projeto usa URLs com ficheiros `.php`, sem reescrita obrigatória) ou o servidor embutido do PHP.
- Laragon, XAMPP, WAMP ou instalação equivalente.

O projeto foi validado com:

- PHP 8.3.16 (Laragon, Windows)
- MySQL 8.4.3
- Apache 2.4 / servidor embutido do PHP

---

## Instalação

### 1. Copiar o projeto

Copie a pasta `lumastay` para a raiz web do seu servidor. Em Laragon, o destino habitual é:

```text
C:\laragon\www\lumastay
```

Em XAMPP:

```text
C:\xampp\htdocs\lumastay
```

### 2. Criar e importar a base de dados

A base de dados chama-se `lumastay` e é criada pelo próprio `schema.sql`.

#### Opção A — phpMyAdmin

1. Abra o phpMyAdmin.
2. Importe `database/schema.sql`.
3. Importe `database/seed.sql` (por esta ordem).

#### Opção B — linha de comandos (MySQL/MariaDB)

```bash
mysql -u root -p -e "SOURCE C:/laragon/www/lumastay/database/schema.sql;"
mysql -u root -p -e "SOURCE C:/laragon/www/lumastay/database/seed.sql;"
```

No Laragon com `root` sem palavra-passe, pode omitir `-p`.

> **Importante:** garanta que os ficheiros `.sql` são importados como UTF-8. No phpMyAdmin, confirme a codificação `utf8mb4`. Na linha de comandos, use `--default-character-set=utf8mb4` se necessário.

### 3. Configurar a ligação

O projeto funciona com os valores por omissão do Laragon (`127.0.0.1:3306`, base `lumastay`, utilizador `root`, palavra-passe vazia). Se a sua instalação for diferente:

```bash
copy config\config.local.php.example config\config.local.php
```

Edite `config/config.local.php`:

```php
<?php
return [
    'app_env'    => 'local',
    'app_url'    => '', // vazio = deteção automática
    'timezone'   => 'Europe/Lisbon',

    'db_host'     => '127.0.0.1',
    'db_port'     => '3306',
    'db_name'     => 'lumastay',
    'db_user'     => 'root',
    'db_password' => '',
    'db_charset'  => 'utf8mb4',
];
```

Também pode usar variáveis de ambiente, que têm prioridade sobre o ficheiro:

```text
DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET, APP_ENV, APP_URL
```

O ficheiro `config/config.local.php` está no `.gitignore` e não deve ser versionado.

### 4. Abrir a aplicação

Com Laragon/Apache:

```text
http://localhost/lumastay/
```

O `app_url` pode ficar vazio (deteção automática) ou ser definido explicitamente, por exemplo:

```php
'app_url' => 'http://localhost/lumastay',
```

### 5. Servidor embutido do PHP (alternativa para testes)

Na pasta do projeto:

```bash
php -S 127.0.0.1:8088 -t .
```

Depois abra `http://127.0.0.1:8088/`.

### 6. Instalação numa VM Linux

Para publicar numa máquina virtual com Apache ou Nginx, MySQL/MariaDB e Cloudflare, segue o guia dedicado:

- [`docs/instalacao-vm.md`](docs/instalacao-vm.md)

---

## Dados de demonstração

O `seed.sql` cria:

- **8 alojamentos** em Lisboa, Douro, Algarve, Sintra, Porto, Évora, Madeira e Comporta;
- **10 hóspedes** fictícios;
- **16 reservas** distribuídas por quatro estados:
  - 4 pendentes;
  - 7 confirmadas;
  - 3 concluídas;
  - 2 canceladas.

Os dados incluem datas passadas, presentes e futuras para que o dashboard, o calendário e os filtros tenham conteúdo realista.

### Alojamentos incluídos

| Alojamento | Localização | Tipo | Preço/noite | Capacidade |
| --- | --- | --- | ---: | ---: |
| Casa da Luz | Lisboa | Apartamento | 185,00 € | 2 |
| Refúgio do Douro | Douro | Quinta | 320,00 € | 6 |
| Villa Atlântica | Algarve | Villa | 410,00 € | 8 |
| Casa do Bosque | Sintra | Casa | 245,00 € | 4 |
| Estúdio Ribeira | Porto | Apartamento | 145,00 € | 2 |
| Quinta da Cal | Évora | Quinta | 275,00 € | 6 |
| Suite Jardim do Mar | Madeira | Suite | 195,00 € | 2 |
| Loft Comporta | Comporta | Loft | 230,00 € | 4 |

---

## Credenciais de administração

```text
Email:      admin@lumastay.pt
Palavra-passe: LumaStay2026!
```

A palavra-passe é guardada com `password_hash()` (bcrypt) e verificada com `password_verify()`. O hash incluído no `seed.sql` corresponde a esta palavra-passe de demonstração.

Após 5 tentativas falhadas no período de 10 minutos, o login é bloqueado temporariamente nessa sessão.

---

## Estrutura do projeto

```text
lumastay/
├── index.php                     Página inicial
├── alojamentos.php               Listagem e pesquisa
├── alojamento.php                Detalhe do alojamento
├── reservar.php                  Formulário e processamento da reserva
├── reserva-confirmada.php        Confirmação da reserva
├── consultar-reserva.php         Consulta por código + email
├── login.php                     Login administrativo
├── logout.php                    Terminar sessão
├── admin/
│   ├── index.php                 Dashboard
│   ├── alojamentos.php           Listagem e ações do catálogo
│   ├── criar-alojamento.php      Criação
│   ├── editar-alojamento.php     Edição
│   ├── reservas.php              Listagem de reservas
│   ├── detalhes-reserva.php      Detalhe e transições de estado
│   ├── hospedes.php              Listagem de hóspedes
│   ├── hospede.php               Ficha do hóspede
│   └── disponibilidade.php       Calendário de disponibilidade
├── includes/
│   ├── functions.php             Domínio, validação, ícones e disponibilidade
│   ├── auth.php                  Sessão, CSRF e autenticação
│   ├── header.php                Cabeçalho público
│   ├── footer.php                Rodapé público
│   ├── navigation.php            Navegação pública
│   ├── admin-header.php          Cabeçalho administrativo
│   ├── admin-footer.php          Rodapé administrativo
│   ├── accommodation-card.php    Card reutilizável
│   └── admin-accommodation-form.php Formulário partilhado
├── config/
│   ├── database.php              Configuração e ligação PDO
│   └── config.local.php.example  Modelo de configuração local
├── database/
│   ├── schema.sql                Esquema e restrições
│   └── seed.sql                  Dados de demonstração
├── assets/
│   ├── css/
│   │   ├── style.css             Estilos públicos e design tokens
│   │   ├── admin.css             Estilos da área administrativa
│   │   └── responsive.css        Pontos de rutura e impressão
│   ├── js/
│   │   └── main.js               Interações sem dependências
│   └── images/
│       ├── logo-mark.svg
│       ├── fallback.svg
│       ├── hero.svg
│       └── alojamentos/*.svg     Oito ilustrações originais
├── tests/
│   └── run-tests.php             Suite de testes
├── docs/
│   ├── manual-utilizacao.md
│   ├── relatorio-projeto.md
│   └── apresentacao-projeto.md
└── README.md
```

---

## Testes

A suite `tests/run-tests.php` cobre funções utilitárias, validação, integridade da base de dados, disponibilidade, códigos de reserva, transições de estado, credenciais e verificações HTTP.

### Executar

No PowerShell, a partir da raiz do projeto:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_NAME = 'lumastay'
$env:DB_USER = 'root'
$env:DB_PASSWORD = ''

# Opcional: ativa os testes HTTP
$env:APP_TEST_URL = 'http://localhost/lumastay'

php tests/run-tests.php
```

Se `APP_TEST_URL` não for definido, os testes HTTP são ignorados e os restantes continuam a ser executados.

### Resultado real obtido

Execução efetuada em 6 de novembro de 2026, com PHP 8.3.16, MySQL 8.4.3 e o servidor embutido do PHP:

```text
Resultado: 29 passaram, 0 falharam, 0 ignorados (total 29)
Todos os testes executados passaram.
```

Também foram executadas verificações manuais e automatizadas de:

- criação de reserva com CSRF e sessão, incluindo preço e estado `pendente`;
- rejeição de sobreposição de datas sem inserir duplicados;
- consulta de reserva e página de confirmação;
- login, dashboard e todas as páginas administrativas;
- criação, edição, ativação/desativação e eliminação de alojamento;
- confirmação e cancelamento de reserva;
- proteção de rotas administrativas sem sessão;
- rejeição de POST sem token CSRF.

---

## Segurança implementada

- **PDO com prepared statements** em todas as consultas com dados de utilizador.
- **`PDO::ATTR_EMULATE_PREPARES => false`** para prepared statements nativos.
- **`password_hash()` / `password_verify()`** para credenciais de administração, com rehash transparente.
- **CSRF** em todos os formulários POST, com token por sessão e comparação com `hash_equals()`.
- **Sessões** com cookie `HttpOnly`, `SameSite=Lax` e `Secure` quando em HTTPS.
- **Regeneração do ID de sessão** após login e logout.
- **Expiração por inatividade** da sessão administrativa após 2 horas.
- **Bloqueio temporário** após 5 tentativas de login falhadas em 10 minutos.
- **Escaping de saída** com `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **Validação no servidor** de datas, capacidade, preços, email, telefone, slugs e comodidades.
- **Controlo de acesso** centralizado em `require_admin()`.
- **Transações e bloqueios** para evitar reservas sobrepostas em pedidos concorrentes.
- **Mensagens de erro genéricas** para o visitante; detalhes técnicos apenas no log do servidor.
- **Eliminação de alojamentos bloqueada** quando existem reservas associadas (preservação de histórico).

---

## Acessibilidade e responsividade

Verificações efetuadas:

- **Lighthouse** (Chrome, desktop):
  - Página inicial: acessibilidade **100**, boas práticas **100**, SEO **100**;
  - Listagem, detalhe, reserva, consulta e login: **100 / 100 / 100**;
  - Dashboard, alojamentos, reservas, hóspedes e disponibilidade: **100 / 100 / 100**.
- **Viewports reais** testados com emulação de dispositivo:
  - **390 × 844** (telemóvel): sem overflow horizontal em todas as páginas públicas e administrativas;
  - **1440 × 900** (desktop): sem overflow horizontal.
- Navegação móvel em gaveta, com `aria-expanded` e fecho por `Escape`.
- Menu administrativo em gaveta abaixo de 900 px, com overlay e foco visual.
- Estados de foco visíveis, ligações de salto para o conteúdo e textos alternativos nas imagens.
- Suporte a `prefers-reduced-motion` não é obrigatório neste âmbito, mas as animações são curtas e não essenciais.

---

## Documentação

- [`docs/manual-utilizacao.md`](docs/manual-utilizacao.md) — manual passo a passo para visitantes e administradores.
- [`docs/relatorio-projeto.md`](docs/relatorio-projeto.md) — relatório técnico, arquitetura, decisões e resultados.
- [`docs/apresentacao-projeto.md`](docs/apresentacao-projeto.md) — guião de apresentação e demonstração.
- [`docs/instalacao-vm.md`](docs/instalacao-vm.md) — instalação numa VM Linux com Apache/Nginx, MySQL e Cloudflare.

---

## Limitações conhecidas

- Não existe envio real de emails; a confirmação é apresentada no ecrã e guardada na base de dados.
- Não existe pagamento online; o estado inicial é sempre `pendente`.
- A gestão de utilizadores administrativos é feita por seed/base de dados, não por interface.
- Não há upload de imagens; os alojamentos usam caminhos locais ou URLs `http(s)`.
- O calendário administrativo apresenta a ocupação, mas a criação de reservas manuais pela administração não está incluída.
- O projeto foi pensado para um ambiente local/académico, não para produção sem revisão adicional de infraestrutura, backups e observabilidade.

---

## Licença e âmbito

Projeto académico de demonstração. Conceito, design, textos e ilustrações SVG originais. As reservas são simuladas e não envolvem pagamentos reais.
