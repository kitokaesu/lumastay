# Guião de Apresentação — LumaStay

**Duração prevista:** 15 a 20 minutos (12 minutos de apresentação + 5 a 8 minutos de demonstração)
**Formato:** 13 diapositivos + demonstração ao vivo
**Público:** docentes e colegas de curso

---

## Antes de começar

### Checklist técnico (15 minutos antes)

- [ ] MySQL/MariaDB a correr.
- [ ] Base `lumastay` importada (`schema.sql` + `seed.sql`).
- [ ] Aplicação aberta em `http://localhost/lumastay/`.
- [ ] Login administrativo testado numa janela separada.
- [ ] Navegador com zoom a 100% e separadores pré-abertos:
  - página inicial;
  - listagem;
  - detalhe da Casa da Luz;
  - login;
  - dashboard.
- [ ] Notificações e som do sistema desativados.
- [ ] Dados no estado de demonstração (8 alojamentos, 10 hóspedes, 16 reservas).

### Plano B

Se o servidor local falhar:

1. Manter capturas de ecrã do fluxo principal numa pasta acessível.
2. Usar o README e o relatório para explicar a arquitetura.
3. Nunca afirmar que a demonstração correu se não correu.
4. Ter o comando de arranque do servidor embutido à mão:

```bash
php -S 127.0.0.1:8088 -t .
```

---

## Diapositivo 1 — Título

**Título:** LumaStay — Alojamento turístico premium em Portugal
**Subtítulo:** Aplicação web em PHP, MySQL e PDO
**Conteúdo visual:** logótipo, ilustração principal, nome do autor, curso e data.

**Notas de apresentação:**

> "Boa tarde. Vou apresentar a LumaStay, uma aplicação web de reservas de alojamento turístico em Portugal, desenvolvida em PHP tradicional, MySQL e PDO. Ao longo dos próximos minutos vou mostrar o problema, as decisões técnicas, o fluxo de reserva, a área administrativa e os resultados dos testes."

**Tempo:** 30 segundos.

---

## Diapositivo 2 — O problema

**Título:** Porquê a LumaStay?

**Tópicos:**

- Plataformas de reserva concentram oferta, mas perdem identidade e controlo.
- Pequenos alojamentos precisam de presença própria e gestão simples.
- Reservas duplicadas e overbooking são erros frequentes.
- Muitas soluções exigem subscrições e dependências externas.

**Notas de apresentação:**

> "O ponto de partida foi um problema concreto: um pequeno conjunto de alojamentos precisa de receber pedidos, verificar disponibilidade e gerir reservas sem depender de uma grande plataforma. Ao mesmo tempo, quer manter uma identidade própria. A LumaStay responde a isso com uma aplicação autónoma, sem APIs pagas e sem dependências de front-end."

**Tempo:** 45 segundos.

---

## Diapositivo 3 — Objetivos

**Título:** Objetivos do projeto

**Tópicos:**

- Catálogo público pesquisável por destino, datas, hóspedes, tipo e preço.
- Fluxo de reserva completo, com validação e cálculo no servidor.
- Código único e consulta posterior.
- Área administrativa protegida: alojamentos, reservas, hóspedes e disponibilidade.
- Segurança, acessibilidade e responsividade desde o início.
- Documentação e testes reais.

**Notas de apresentação:**

> "Os objetivos dividem-se em três blocos: o site público, a área administrativa e a qualidade. Não quis construir apenas páginas bonitas; o critério foi o fluxo funcionar de ponta a ponta, com regras aplicadas no servidor."

**Tempo:** 45 segundos.

---

## Diapositivo 4 — Stack e arquitetura

**Título:** Tecnologia simples, responsabilidades claras

**Conteúdo:**

```text
PHP 8 → páginas e regras de negócio
PDO  → prepared statements e transações
MySQL → persistência relacional
HTML/CSS/JS → interface sem dependências
SVG  → ilustrações originais
```

**Diagrama:**

```text
Página PHP
   ↓
Funções de domínio (validação, disponibilidade)
   ↓
PDO
   ↓
MySQL
   ↓
HTML renderizado
```

**Notas de apresentação:**

> "A arquitetura é deliberadamente clássica. Cada página PHP trata do pedido, chama funções de domínio partilhadas e usa PDO para aceder à base de dados. Não há React, Next.js, Vue nem SPA. Isto permite demonstrar os fundamentos: sessões, validação, CSRF, SQL e transações."

**Transição:** "Vejamos o modelo de dados."

**Tempo:** 60 segundos.

---

## Diapositivo 5 — Modelo de dados

**Título:** Quatro tabelas, integridade garantida

**Conteúdo:**

```text
accommodations 1 ──── N reservations N ──── 1 guests
admin_users (autenticação)
```

**Pontos a destacar:**

- `accommodations`: catálogo, preço, capacidade, comodidades em JSON.
- `guests`: contacto único por email.
- `reservations`: datas, hóspedes, preço congelado e estado.
- `admin_users`: credenciais com bcrypt.
- Chaves estrangeiras com `ON DELETE RESTRICT`.
- Restrições `CHECK` para preços, datas, capacidade e estados.
- Índice composto `(accommodation_id, check_in, check_out)`.

**Notas de apresentação:**

> "O modelo tem quatro tabelas. A decisão mais importante está nas reservas: o preço por noite e o total são guardados no momento da criação. Se o preço do alojamento mudar amanhã, a reserva antiga mantém o valor acordado. As chaves estrangeiras impedem apagar histórico por acidente."

**Tempo:** 60 segundos.

---

## Diapositivo 6 — Fluxo de reserva

**Título:** Do pedido à confirmação

**Conteúdo:**

```text
1. Escolher alojamento e datas
2. Validar no servidor
3. Verificar disponibilidade
4. Calcular preço
5. Registar hóspede
6. Criar reserva (pendente)
7. Mostrar código LS-XXXXXX
8. Confirmar no admin
```

**Notas de apresentação:**

> "O visitante escolhe o alojamento e as datas. O servidor valida tudo: datas reais, entrada no futuro, saída posterior, máximo de 60 noites, capacidade, nome, email, telefone e aceitação das condições. Só depois calcula o preço e cria a reserva com estado pendente. O código serve para consultar mais tarde."

**Tempo:** 60 segundos.

---

## Diapositivo 7 — Concorrência

**Título:** Como evitamos overbooking

**Conteúdo visual:** diagrama de dois pedidos simultâneos.

```text
Pedido A ──┐
           ├── SELECT alojamento FOR UPDATE  ← serializa
Pedido B ──┘
           │
           ├── verificar sobreposição
           ├── inserir reserva
           └── COMMIT
```

**Regra:**

```text
nova.check_in  < existente.check_out
E
nova.check_out > existente.check_in
```

**Notas de apresentação:**

> "Este é o ponto tecnicamente mais importante. Sem controlo, dois visitantes podiam reservar as mesmas datas ao mesmo tempo. A solução é uma transação com `SELECT ... FOR UPDATE` sobre o alojamento, seguida de uma segunda verificação de sobreposição. Se houver conflito, fazemos rollback e não inserimos nada. Testámos exatamente este cenário: a segunda submissão foi rejeitada e ficou apenas uma reserva."

**Tempo:** 75 segundos.

---

## Diapositivo 8 — Segurança

**Título:** Segurança aplicada em todas as camadas

**Tópicos:**

- PDO com prepared statements nativos.
- Escape de toda a saída HTML.
- CSRF com token de 32 bytes e `hash_equals()`.
- `password_hash()` / `password_verify()`.
- Sessões `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS.
- Regeneração de sessão no login e logout.
- Expiração após 2 horas de inatividade.
- Bloqueio após 5 tentativas falhadas em 10 minutos.
- Controlo de acesso centralizado com `require_admin()`.

**Notas de apresentação:**

> "A segurança não foi adicionada no fim. Todas as consultas usam prepared statements; toda a saída é escapada; todos os formulários POST têm CSRF. O login tem bloqueio por tentativas e a sessão expira por inatividade. Testámos um POST administrativo sem token: foi rejeitado e nada mudou na base de dados."

**Tempo:** 60 segundos.

---

## Diapositivo 9 — Área administrativa

**Título:** Gestão operacional completa

**Conteúdo:** grelha com as cinco áreas.

| Área | O que permite |
| --- | --- |
| Dashboard | Receita prevista, reservas, hóspedes, ocupação, gráfico mensal |
| Alojamentos | Criar, editar, ativar/desativar, eliminar com proteção |
| Reservas | Filtrar, ver detalhe, confirmar, cancelar, concluir |
| Hóspedes | Pesquisar, ver histórico e valor acumulado |
| Disponibilidade | Calendário mensal por alojamento |

**Notas de apresentação:**

> "A área administrativa cobre a operação diária. O dashboard calcula os indicadores em SQL, incluindo a ocupação a 30 dias e um gráfico dos últimos seis meses feito apenas em CSS. A gestão de alojamentos impede eliminar um imóvel com reservas, para preservar histórico. As reservas têm regras de transição: só pendentes podem ser confirmadas, só confirmadas podem ser concluídas, e só após a saída."

**Tempo:** 75 segundos.

---

## Diapositivo 10 — Design e identidade

**Título:** Uma identidade própria

**Conteúdo:**

- Paleta: `#183A3A`, `#234B45`, `#FAF9F6`, `#EAE4D9`, `#292D2C`, `#C49A6C`.
- Tipografia editorial e muito espaço branco.
- Oito ilustrações SVG originais.
- Componentes consistentes no site e no admin.
- Fallback automático para imagens em falta.

**Notas de apresentação:**

> "A identidade visual foi construída de raiz. Escolhi uma paleta de petróleo, creme e dourado, com ilustrações SVG originais para cada alojamento. Não há fotografias de banco de imagens nem dependências externas. O mesmo sistema de tokens e componentes é usado no site e na administração."

**Tempo:** 45 segundos.

---

## Diapositivo 11 — Qualidade

**Título:** Resultados verificados

**Conteúdo:**

```text
29 testes automatizados  →  29 passaram, 0 falharam
28 ficheiros PHP         →  0 erros de sintaxe
Lighthouse               →  100 / 100 / 100
Viewports 390 e 1440     →  sem overflow horizontal
```

**Detalhe Lighthouse:**

| Página | Acessibilidade | Boas práticas | SEO |
| --- | ---: | ---: | ---: |
| Públicas (6) | 100 | 100 | 100 |
| Admin (5) | 100 | 100 | 100 |

**Notas de apresentação:**

> "Os números são reais. A suite tem 29 testes: funções utilitárias, validação, integridade da base de dados, disponibilidade, códigos, transições, credenciais e verificações HTTP. Todos passaram. Verificámos também a sintaxe dos 28 ficheiros PHP, sem erros. No Lighthouse, todas as páginas públicas e administrativas testadas atingem 100 em acessibilidade, boas práticas e SEO. Em 390 e 1440 píxeis não há overflow horizontal."

**Tempo:** 60 segundos.

---

## Diapositivo 12 — Limitações e trabalho futuro

**Título:** O que fica para depois

**Limitações:**

- Sem pagamentos reais nem emails transacionais.
- Sem upload de imagens.
- Gestão de administradores apenas por base de dados.
- Bloqueio de login por sessão, não por conta/IP.
- Sem migrações nem otimizações de produção.

**Trabalho futuro:**

- Gateway de pagamento e estados de pagamento.
- Emails de confirmação e lembretes.
- Upload e galeria de imagens.
- Reservas manuais no admin.
- Perfis e permissões.
- Testes end-to-end e internacionalização.

**Notas de apresentação:**

> "O projeto tem limites conscientes. Não processa pagamentos nem envia emails, porque o âmbito era o fluxo de reserva e a gestão. Para produção, os próximos passos seriam pagamentos, emails, upload de imagens, permissões e otimização de infraestrutura. O bloqueio de login está na sessão; para produção faria sentido complementá-lo por conta e por IP."

**Tempo:** 45 segundos.

---

## Diapositivo 13 — Conclusão

**Título:** O que a LumaStay demonstra

**Mensagem final:**

- Uma aplicação completa pode ser construída com tecnologia simples.
- As regras de negócio pertencem ao servidor.
- A concorrência tem de ser tratada explicitamente.
- Segurança, acessibilidade e testes fazem parte do produto.

**Notas de apresentação:**

> "Em conclusão, a LumaStay mostra que é possível construir uma aplicação completa, segura e acessível com PHP, MySQL e PDO, desde que as regras estejam bem definidas e os limites de confiança sejam respeitados. O fluxo de reserva funciona, a administração funciona e os resultados foram verificados. Obrigado. Estou disponível para perguntas."

**Tempo:** 30 segundos.

---

# Demonstração ao vivo (5 a 8 minutos)

> **Regra de ouro:** demonstrar pouco e bem. Não navegar por todas as páginas. Seguir o guião abaixo.

## Passo 1 — Página inicial (30 s)

1. Abrir `http://localhost/lumastay/`.
2. Mostrar o hero, os destaques e o painel de pesquisa.
3. Referir que os dados vêm do MySQL, não estão escritos no HTML.

**Frase:** "Esta página é gerada a partir da base de dados. Os alojamentos em destaque são uma consulta SQL."

## Passo 2 — Pesquisa (45 s)

1. Escolher destino **Lisboa**.
2. Definir datas futuras livres (por exemplo, daqui a 3 meses, 3 noites).
3. Indicar 2 hóspedes.
4. Pesquisar.
5. Mostrar os filtros ativos e o resultado.
6. Referir que a disponibilidade é verificada no servidor.

## Passo 3 — Detalhe e reserva (2 min)

1. Abrir **Casa da Luz**.
2. Mostrar descrição, comodidades, capacidade e preço.
3. Clicar em **Reservar**.
4. Confirmar datas e hóspedes.
5. Preencher nome, email e telefone fictícios.
6. Aceitar condições.
7. Submeter.
8. Mostrar o código `LS-XXXXXX`, o total e o estado **Pendente**.

**Frase:** "O total foi calculado no servidor: noites × preço por noite. O código é único e permite consultar a reserva."

## Passo 4 — Consulta (45 s)

1. Abrir **Consultar reserva**.
2. Introduzir o código e o email usados.
3. Mostrar o estado e o resumo.
4. Opcional: introduzir um email errado e mostrar a mensagem genérica.

**Frase:** "Por segurança, não revelamos se o código existe quando o email não corresponde."

## Passo 5 — Área administrativa (2 a 3 min)

1. Abrir **Entrar**.
2. Iniciar sessão com `admin@lumastay.pt` / `LumaStay2026!`.
3. Mostrar o dashboard:
   - receita prevista;
   - ocupação;
   - gráfico.
4. Abrir **Reservas** e localizar a reserva criada.
5. Abrir o detalhe.
6. Confirmar a reserva.
7. Mostrar o estado atualizado.
8. Opcional: cancelar e mostrar que as datas ficam livres.
9. Abrir **Disponibilidade** e mostrar o calendário do alojamento.

**Frase:** "A confirmação volta a verificar sobreposições. As transições inválidas são rejeitadas."

## Passo 6 — Encerramento da demo (30 s)

1. Voltar ao dashboard.
2. Referir que os testes e o Lighthouse estão no relatório.
3. Agradecer.

---

# Preparação para perguntas

## "Porque não usou um framework?"

> "O objetivo era demonstrar os fundamentos — PDO, sessões, validação, CSRF e transações — sem os esconder atrás de abstrações. Um framework seria adequado num contexto de produção com prazos e equipas maiores, mas aqui tornaria menos visível o que estava a ser avaliado."

## "Como impede duas reservas iguais?"

> "Numa transação, bloqueio a linha do alojamento com `SELECT ... FOR UPDATE`, verifico sobreposições e só depois insiro. Se houver conflito, faço rollback. Testei o cenário e a segunda submissão foi rejeitada sem duplicar."

## "Porque é que reservas canceladas não bloqueiam?"

> "Porque o cancelamento liberta o calendário. A regra é consistente no site, no admin e nos testes: apenas pendentes e confirmadas bloqueiam. Concluídas pertencem ao passado."

## "O preço pode ser manipulado no navegador?"

> "Não. O navegador só mostra uma estimativa. O valor final é recalculado no servidor com o preço do alojamento bloqueado na transação."

## "Como protege a aplicação contra XSS?"

> "Toda a saída passa pela função `e()`, que usa `htmlspecialchars` com `ENT_QUOTES` e `ENT_SUBSTITUTE` em UTF-8. Não renderizamos HTML fornecido pelo utilizador."

## "O que acontece após 5 tentativas falhadas?"

> "O login fica bloqueado temporariamente nessa sessão. Mesmo a palavra-passe correta é recusada enquanto o bloqueio estiver ativo. Em produção, complementaria com limites por conta e por IP."

## "Os testes são reais?"

> "Sim. A suite foi executada e o resultado foi 29 passaram, 0 falharam. Os fluxos manuais também foram executados com sessão e CSRF, e a base foi reposta no estado de demonstração."

## "O que faria diferente com mais tempo?"

> "Pagamentos, emails transacionais, upload de imagens, reservas manuais no admin, permissões e testes end-to-end. Também reforçaria o rate limiting e a observabilidade para produção."

## "Como garantiu a acessibilidade?"

> "Estrutura semântica, labels associados, foco visível, textos alternativos, contraste WCAG AA e navegação por teclado. O Lighthouse foi usado de forma iterativa até 100 em todas as páginas testadas."

## "Porque SVG e não fotografias?"

> "Os SVG são originais, leves, nítidos em qualquer resolução e coerentes com a paleta. Evitam licenciamento e dependências externas. A aplicação tem fallback para imagens em falta."

---

# Resumo de tempos

| Bloco | Tempo |
| --- | ---: |
| Introdução e problema | 1 min 15 s |
| Objetivos | 45 s |
| Arquitetura e dados | 2 min |
| Fluxo e concorrência | 2 min 15 s |
| Segurança | 1 min |
| Administração | 1 min 15 s |
| Design | 45 s |
| Qualidade | 1 min |
| Limitações e conclusão | 1 min 15 s |
| **Subtotal apresentação** | **~11 min** |
| Demonstração | 5–8 min |
| Perguntas | 5 min |
| **Total** | **21–24 min** |

---

# Dicas de apresentação

1. **Não leia os diapositivos.** Use-os como apoio; fale a partir das notas.
2. **Comece pelo problema, não pela tecnologia.** O público liga-se mais ao "porquê".
3. **Mostre, não descreva.** Na demo, deixe o fluxo falar por si.
4. **Assuma as limitações.** Dizer o que não está feito demonstra maturidade.
5. **Tenha números concretos.** 29 testes, 0 falhas, 100 no Lighthouse, 390 px sem overflow.
6. **Prepare o fallback.** Se algo falhar, mantenha a calma e use capturas.
7. **Termine com uma frase clara.** Não deixe a apresentação "morrer" no último diapositivo.
