# Manual de Utilização — LumaStay

Este manual descreve como usar a aplicação, tanto do ponto de vista do visitante como do administrador.

**Endereço local habitual:** `http://localhost/lumastay/`
**Credenciais de administração:** `admin@lumastay.pt` / `LumaStay2026!`

---

## Índice

1. [Visitante: pesquisar alojamentos](#1-visitante-pesquisar-alojamentos)
2. [Visitante: ver detalhes de um alojamento](#2-visitante-ver-detalhes-de-um-alojamento)
3. [Visitante: fazer uma reserva](#3-visitante-fazer-uma-reserva)
4. [Visitante: consultar uma reserva](#4-visitante-consultar-uma-reserva)
5. [Administrador: iniciar sessão](#5-administrador-iniciar-sessão)
6. [Administrador: dashboard](#6-administrador-dashboard)
7. [Administrador: gerir alojamentos](#7-administrador-gerir-alojamentos)
8. [Administrador: gerir reservas](#8-administrador-gerir-reservas)
9. [Administrador: consultar hóspedes](#9-administrador-consultar-hóspedes)
10. [Administrador: disponibilidade](#10-administrador-disponibilidade)
11. [Perguntas frequentes e resolução de problemas](#11-perguntas-frequentes-e-resolução-de-problemas)

---

## 1. Visitante: pesquisar alojamentos

### Página inicial

Ao abrir a aplicação, encontra:

- uma proposta de valor da LumaStay;
- alojamentos em destaque;
- destinos para explorar;
- um painel de pesquisa rápida.

No painel de pesquisa pode indicar:

- **Destino** (Lisboa, Douro, Algarve, Sintra, Porto, Évora, Madeira ou Comporta);
- **Entrada** e **Saída**;
- **Hóspedes**.

Prima **Pesquisar** para abrir a listagem com os filtros aplicados.

### Página de alojamentos

A listagem permite combinar vários filtros:

- **Destino**;
- **Entrada** e **Saída** — só aparecem alojamentos livres nesse intervalo;
- **Hóspedes** — só aparecem alojamentos com capacidade suficiente;
- **Tipo** — apartamento, casa, villa, quinta, suite, loft ou refúgio;
- **Preço mínimo** e **preço máximo** por noite;
- **Ordenação** — relevância, preço crescente/decrescente, avaliação ou mais recentes.

Os filtros ativos aparecem resumidos no topo dos resultados. Use **Limpar tudo** para os remover.

> Se introduzir um preço inválido (por exemplo, letras), a aplicação mostra uma mensagem de validação e não aplica o filtro.

### Sem resultados

Se nenhum alojamento corresponder aos critérios, é apresentada uma mensagem com uma sugestão para limpar os filtros ou alterar as datas.

---

## 2. Visitante: ver detalhes de um alojamento

Na listagem, clique num cartão ou em **Ver detalhes**. A página de detalhe apresenta:

- ilustração principal e galeria;
- localização, tipo e avaliação;
- descrição completa;
- capacidade, quartos e casas de banho;
- lista de comodidades com ícones;
- morada (a morada exata é indicada após a confirmação da reserva);
- painel lateral com preço por noite e botão **Reservar**.

O botão **Reservar** transporta o alojamento e, se existirem, as datas e o número de hóspedes escolhidos na pesquisa.

---

## 3. Visitante: fazer uma reserva

### Passo 1 — Escolher alojamento

Se ainda não escolheu uma casa, a página **Reservar** apresenta uma lista de alojamentos ativos. Escolha o alojamento e, opcionalmente, as datas e os hóspedes. Prima **Continuar**.

### Passo 2 — Preencher os dados

Confirme ou altere:

- **Entrada** e **Saída**;
- **Número de hóspedes** (limitado à capacidade do alojamento);
- **Nome completo**;
- **Email**;
- **Telefone**;
- aceitação das condições.

O resumo lateral atualiza o número de noites e o total estimado à medida que altera as datas.

Ao submeter, o servidor valida:

- formato e coerência das datas;
- data de entrada não anterior a hoje;
- saída posterior à entrada;
- máximo de 60 noites;
- capacidade do alojamento;
- nome, email e telefone;
- aceitação das condições;
- disponibilidade real face a reservas pendentes e confirmadas.

Se as datas já não estiverem disponíveis, é apresentada uma mensagem e não é criada qualquer reserva duplicada.

### Passo 3 — Confirmação

Depois de guardar, é redirecionado para a página de confirmação, que mostra:

- código único da reserva (formato `LS-XXXXXX`);
- alojamento, datas, noites, hóspedes e total;
- estado **Pendente**;
- dados de contacto;
- instruções para consultar mais tarde.

**Guarde o código e o email.** São necessários para consultar a reserva.

> O preço por noite fica registado na reserva no momento da criação. Alterações posteriores ao preço do alojamento não alteram reservas já existentes.

### Regras de estado

| Estado | Significado |
| --- | --- |
| Pendente | Pedido recebido, aguarda confirmação da equipa. |
| Confirmada | Reserva validada pela equipa. |
| Concluída | Estadia terminada; só é possível após a data de saída. |
| Cancelada | Reserva anulada; as datas ficam novamente disponíveis. |

Apenas reservas **pendentes** e **confirmadas** bloqueiam datas. Reservas canceladas e concluídas não impedem novas reservas.

---

## 4. Visitante: consultar uma reserva

No menu, clique em **Consultar reserva** (ou aceda a `consultar-reserva.php`).

Preencha:

- **Código da reserva** — por exemplo, `LS-A3MH3H`;
- **Email** usado na reserva.

Prima **Consultar**. Se os dados coincidirem, é apresentado:

- estado atual;
- alojamento e localização;
- datas e número de noites;
- número de hóspedes;
- total;
- dados do hóspede.

Por segurança, a aplicação não revela se o código existe quando o email não corresponde. A mensagem é genérica.

---

## 5. Administrador: iniciar sessão

1. No rodapé do site, clique em **Administração** (ou aceda a `login.php`).
2. Introduza:
   - **Email:** `admin@lumastay.pt`
   - **Palavra-passe:** `LumaStay2026!`
3. Prima **Entrar**.

Após o login é redirecionado para o dashboard.

### Segurança do login

- A palavra-passe é verificada com `password_verify()`.
- Após 5 tentativas falhadas em 10 minutos, o login é bloqueado temporariamente.
- A sessão expira após 2 horas de inatividade.
- O botão **Terminar sessão** encerra a sessão e regenera o identificador.

### Recuperar acesso

Se perder a palavra-passe de demonstração, pode gerar um novo hash com PHP:

```bash
php -r "echo password_hash('NovaPalavraPasse!', PASSWORD_DEFAULT), PHP_EOL;"
```

Depois atualize `admin_users.password_hash` na base de dados.

---

## 6. Administrador: dashboard

O dashboard apresenta:

- **Receita prevista** — soma das reservas pendentes e confirmadas com saída futura;
- **Reservas** — total e número de pendentes;
- **Hóspedes** — total de contactos;
- **Ocupação a 30 dias** — noites ocupadas / noites disponíveis;
- **Reservas por mês** — gráfico de barras em CSS;
- **Distribuição por estado** — pendentes, confirmadas, concluídas e canceladas;
- **Próximas entradas** e **próximas saídas**, com ligação ao detalhe.

Os indicadores são calculados em SQL a partir do estado real da base de dados.

---

## 7. Administrador: gerir alojamentos

Aceda a **Alojamentos** no menu lateral.

### Pesquisar e filtrar

Pode pesquisar por nome, localização ou morada, filtrar por:

- estado (ativos/inativos);
- tipo;
- ordenação (nome, mais recentes, preço).

### Criar alojamento

1. Clique em **Novo alojamento**.
2. Preencha:
   - nome, slug (opcional), descrição, localização, tipo e morada;
   - preço por noite, capacidade, quartos, casas de banho, avaliação e número de avaliações;
   - comodidades separadas por vírgulas;
   - imagem (caminho local começado por `assets/` ou URL `http(s)`);
   - estado **ativo** e, se aplicável, **destaque**.
3. Confirme a pré-visualização da imagem.
4. Prima **Criar alojamento**.

Regras importantes:

- o slug é gerado automaticamente a partir do nome se ficar vazio;
- o slug tem de ser único;
- um alojamento inativo não pode estar em destaque;
- a descrição deve ter entre 30 e 5000 caracteres;
- o preço tem de ser positivo;
- a capacidade vai de 1 a 30 hóspedes.

### Editar alojamento

Clique no ícone de lápis na linha do alojamento. Altere os campos e prima **Guardar alterações**. As alterações ficam visíveis imediatamente no site.

### Ativar/desativar

Clique no ícone de energia. Um alojamento inativo:

- deixa de aparecer na pesquisa pública;
- deixa de aceitar novas reservas;
- mantém o histórico de reservas.

### Eliminar

O ícone de lixo só está disponível quando o alojamento **não tem reservas associadas**. Se tiver reservas, a aplicação bloqueia a eliminação e sugere a desativação, preservando o histórico.

---

## 8. Administrador: gerir reservas

Aceda a **Reservas**.

### Filtrar

Pode filtrar por:

- estado;
- alojamento;
- pesquisa por código, hóspede ou email.

No topo são apresentados cartões com o total por estado. Clique num cartão para filtrar por esse estado.

### Ver detalhe

Clique no código da reserva ou no ícone de visualização. O detalhe mostra:

- código e estado;
- linha temporal (pedido → confirmada → concluída);
- datas, noites e alojamento;
- dados do hóspede;
- valores (preço/noite, noites, total);
- ações disponíveis.

### Confirmar reserva

Disponível apenas para reservas **pendentes**. Ao confirmar, a aplicação volta a verificar se não existe outra reserva ativa sobreposta. Se existir, a confirmação é bloqueada com uma mensagem explicativa.

### Cancelar reserva

Disponível para reservas **pendentes** ou **confirmadas**. Ao cancelar:

- o estado passa a **cancelada**;
- as datas ficam novamente disponíveis;
- o histórico é preservado.

### Concluir reserva

Disponível apenas para reservas **confirmadas** cuja data de saída já passou. Uma reserva futura não pode ser concluída.

### Transições inválidas

A aplicação rejeita ações não permitidas, por exemplo:

- confirmar uma reserva já concluída;
- concluir uma reserva pendente;
- cancelar uma reserva já cancelada.

---

## 9. Administrador: consultar hóspedes

Aceda a **Hóspedes**.

A listagem permite pesquisar por nome, email ou telefone e mostra:

- nome e contacto;
- número de reservas;
- total de noites;
- valor acumulado.

Clique num hóspede para abrir a ficha individual, com:

- dados de contacto;
- resumo (reservas, noites, valor);
- histórico completo de reservas, com ligação ao detalhe de cada uma.

Os hóspedes são criados automaticamente quando é submetido um pedido de reserva com um email novo. Se o email já existir, os dados de nome e telefone são atualizados.

---

## 10. Administrador: disponibilidade

Aceda a **Disponibilidade**.

### Visão agregada

A tabela mostra, para o mês selecionado:

- alojamento;
- noites ocupadas;
- taxa de ocupação;
- número de reservas;
- valor das reservas;
- ligação para o calendário diário.

### Filtros

- **Mês** — navegue para o mês anterior ou seguinte;
- **Alojamento** — veja todos ou apenas uma casa.

### Calendário diário

Ao escolher um alojamento, é apresentado um calendário mensal com:

- dias disponíveis;
- dias ocupados por reservas confirmadas;
- dias ocupados por reservas pendentes;
- dias de check-out;
- dia atual destacado;
- legenda.

Passe o cursor sobre um evento para ver o código da reserva e o hóspede.

> Reservas canceladas e concluídas **não** aparecem como bloqueio. Esta regra é a mesma usada no site público e nos testes automatizados.

---

## 11. Perguntas frequentes e resolução de problemas

### A página mostra "Serviço temporariamente indisponível"

A aplicação não conseguiu ligar-se à base de dados. Verifique:

1. O MySQL/MariaDB está a correr.
2. A base `lumastay` existe.
3. As credenciais em `config/config.local.php` estão corretas.
4. A porta está correta (`3306` por omissão).

### Os acentos aparecem como "?" ou caracteres estranhos

A base de dados foi importada com a codificação errada. Volte a importar `schema.sql` e `seed.sql` garantindo `utf8mb4`.

Em phpMyAdmin, selecione a codificação `utf8mb4` no momento da importação. Na linha de comandos:

```bash
mysql --default-character-set=utf8mb4 -u root -p -e "SOURCE C:/laragon/www/lumastay/database/seed.sql;"
```

### As imagens não aparecem

Confirme que a pasta `assets/images/` foi copiada e que o servidor tem permissão de leitura. Se uma imagem falhar, o site usa automaticamente `assets/images/fallback.svg`.

### O login diz que a sessão expirou

O token CSRF é por sessão. Se a página de login estiver aberta há muito tempo, recarregue-a e tente novamente. A sessão administrativa também expira após 2 horas de inatividade.

### Esqueci-me do código da reserva

Por segurança, não existe pesquisa apenas por email. Contacte a equipa de demonstração ou consulte a reserva na área administrativa, em **Reservas**.

### Como repor os dados de demonstração

Execute novamente:

```bash
mysql -u root -p -e "SOURCE C:/laragon/www/lumastay/database/schema.sql;"
mysql -u root -p -e "SOURCE C:/laragon/www/lumastay/database/seed.sql;"
```

Isto apaga e recria as tabelas e volta a inserir os 8 alojamentos, 10 hóspedes e 16 reservas.

### Como executar os testes

```powershell
$env:DB_PORT = '3306'
$env:APP_TEST_URL = 'http://localhost/lumastay'
php tests/run-tests.php
```

O resultado esperado é `29 passaram, 0 falharam`.
