# Instalação na VM `lab.csmiguel.pt`

Guia para colocar a LumaStay a correr numa máquina virtual Linux (Ubuntu/Debian), com Apache ou Nginx, MySQL/MariaDB e PHP 8.

> **Importante:** o domínio `lab.csmiguel.pt` já está atrás da Cloudflare e responde com outra aplicação. **Não substituas o site existente.** Usa um subdomínio, uma subpasta ou uma porta alternativa para a LumaStay.

---

## Índice

1. [Antes de começar](#1-antes-de-começar)
2. [Escolher o endereço da aplicação](#2-escolher-o-endereço-da-aplicação)
3. [Ligar à VM](#3-ligar-à-vm)
4. [Instalar dependências](#4-instalar-dependências)
5. [Obter o código](#5-obter-o-código)
6. [Criar e importar a base de dados](#6-criar-e-importar-a-base-de-dados)
7. [Configurar a aplicação](#7-configurar-a-aplicação)
8. [Configurar o Apache](#8-configurar-o-apache)
9. [Configurar o Nginx (alternativa)](#9-configurar-o-nginx-alternativa)
10. [Cloudflare, DNS e HTTPS](#10-cloudflare-dns-e-https)
11. [Permissões e segurança](#11-permissões-e-segurança)
12. [Verificação final](#12-verificação-final)
13. [Atualizar a aplicação](#13-atualizar-a-aplicação)
14. [Resolução de problemas](#14-resolução-de-problemas)
15. [Se a VM for Windows](#15-se-a-vm-for-windows)

---

## 1. Antes de começar

### 1.1 Pré-requisitos

- Acesso SSH à VM com um utilizador que tenha `sudo`.
- PHP 8.0 ou superior.
- MySQL 8.0+ ou MariaDB 10.4+.
- Apache 2.4+ ou Nginx.
- Git (para clonar o repositório) ou acesso ao código por outro meio.
- Porta 80 (e 443, se usares HTTPS) acessível a partir da Cloudflare.

### 1.2 Confirmar o sistema

Dentro da VM:

```bash
cat /etc/os-release
php -v
mysql --version
apache2 -v 2>/dev/null || nginx -v
```

Se algum comando não existir, instala no passo 4.

### 1.3 Aviso de segurança

O repositório é público e inclui credenciais de demonstração:

```text
admin@lumastay.pt / LumaStay2026!
```

**Altera a palavra-passe imediatamente após a instalação.** Ver o passo 11.2.

### 1.4 Acesso SSH

`lab.csmiguel.pt` está atrás da Cloudflare, pelo que **não serves para SSH**. Usa o IP direto da VM ou o hostname interno fornecido pela escola:

```bash
ssh utilizador@IP_DA_VM
```

---

## 2. Escolher o endereço da aplicação

| Opção | Endereço | Quando usar | HTTPS |
| --- | --- | --- | --- |
| A | `http://IP_DA_VM/lumastay/` | Teste rápido, sem DNS | Não |
| B | `https://lumastay.csmiguel.pt/` | Controlas o DNS de `csmiguel.pt` | Sim (Universal SSL) |
| C | `https://lab.csmiguel.pt/lumastay/` | Só controlas `lab.csmiguel.pt` | Sim |
| D | `https://lumastay.lab.csmiguel.pt/` | Só com certificado para 3.º nível | Requer ACM/certificado |

### Recomendação

- **Para validar rapidamente:** opção A.
- **Para uma apresentação:** opção B, se conseguires criar um registo DNS em `csmiguel.pt`.
- **Se não conseguires mexer no DNS:** opção C (subpasta), com cuidado para não conflituar com a aplicação existente.
- **Evita a opção D** por omissão: o certificado Universal SSL da Cloudflare cobre `csmiguel.pt` e `*.csmiguel.pt`, mas **não** cobre `*.*.csmiguel.pt`.

Ao longo do guia, substitui:

```text
APP_URL_PUBLICO   -> o endereço escolhido (sem barra final)
CAMINHO_APLICACAO -> /var/www/lumastay
```

---

## 3. Ligar à VM

```bash
ssh utilizador@IP_DA_VM
```

Confirma que estás na máquina certa:

```bash
hostname
ip addr show
```

> Todos os comandos seguintes assumem Ubuntu/Debian com `apt`. Noutra distribuição, adapta os nomes dos pacotes.

---

## 4. Instalar dependências

### 4.1 Atualizar o sistema

```bash
sudo apt update
sudo apt upgrade -y
```

### 4.2 Apache + PHP + MySQL

```bash
sudo apt install -y \
  apache2 \
  mysql-server \
  php \
  php-cli \
  php-mysql \
  php-mbstring \
  php-xml \
  php-curl \
  git \
  unzip
```

Ativa os módulos necessários:

```bash
sudo a2enmod rewrite headers
sudo systemctl enable --now apache2
sudo systemctl enable --now mysql
```

### 4.3 Alternativa com Nginx

```bash
sudo apt install -y \
  nginx \
  mysql-server \
  php-fpm \
  php-cli \
  php-mysql \
  php-mbstring \
  php-xml \
  php-curl \
  git \
  unzip

sudo systemctl enable --now nginx
sudo systemctl enable --now php8.3-fpm   # ajusta a versão
```

Confirma a versão do PHP e o socket FPM:

```bash
php -v
ls /run/php/
```

### 4.4 Confirmar extensões

```bash
php -m | grep -E 'pdo|pdo_mysql|mbstring'
```

Deve aparecer `PDO`, `pdo_mysql` e `mbstring`.

---

## 5. Obter o código

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/kitokaesu/lumastay.git
```

O conteúdo fica em `/var/www/lumastay`.

### Se o repositório passar a privado

Cria uma deploy key na VM:

```bash
ssh-keygen -t ed25519 -C "lumastay-vm" -f ~/.ssh/lumastay_deploy
cat ~/.ssh/lumastay_deploy.pub
```

Adiciona a chave pública em **GitHub → Settings → Deploy keys** do repositório e usa o URL SSH:

```bash
git clone git@github.com:kitokaesu/lumastay.git
```

---

## 6. Criar e importar a base de dados

### 6.1 Criar utilizador dedicado

Entra no MySQL como root:

```bash
sudo mysql
```

Executa (troca a palavra-passe):

```sql
CREATE DATABASE IF NOT EXISTS lumastay
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'lumastay'@'localhost'
  IDENTIFIED BY 'TROCA_ESTA_PALAVRA_FORTE';

GRANT SELECT, INSERT, UPDATE, DELETE
  ON lumastay.* TO 'lumastay'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

> O utilizador da aplicação não precisa de `CREATE`, `DROP` nem `ALTER`. O esquema é importado como `root`.

### 6.2 Importar esquema e dados

```bash
cd /var/www/lumastay

sudo mysql --default-character-set=utf8mb4 < database/schema.sql
sudo mysql --default-character-set=utf8mb4 < database/seed.sql
```

### 6.3 Confirmar

```bash
sudo mysql -D lumastay -e "
  SELECT COUNT(*) AS alojamentos FROM accommodations;
  SELECT COUNT(*) AS hospedes FROM guests;
  SELECT COUNT(*) AS reservas FROM reservations;
  SELECT status, COUNT(*) FROM reservations GROUP BY status;
"
```

Resultado esperado:

```text
alojamentos: 8
hospedes:    10
reservas:    16
pendente:    4
confirmada:  7
concluida:   3
cancelada:   2
```

---

## 7. Configurar a aplicação

### 7.1 Criar o ficheiro local

```bash
cd /var/www/lumastay
sudo cp config/config.local.php.example config/config.local.php
sudo nano config/config.local.php
```

Conteúdo ajustado:

```php
<?php
return [
    'app_env'    => 'production',
    'app_url'    => 'https://lumastay.csmiguel.pt',
    'timezone'   => 'Europe/Lisbon',

    'db_host'     => '127.0.0.1',
    'db_port'     => '3306',
    'db_name'     => 'lumastay',
    'db_user'     => 'lumastay',
    'db_password' => 'TROCA_ESTA_PALAVRA_FORTE',
    'db_charset'  => 'utf8mb4',
];
```

Regras:

- `app_url` deve ser o endereço público **exato**, sem barra final.
- Se usares subpasta: `https://lab.csmiguel.pt/lumastay`.
- Se usares IP: `http://IP_DA_VM/lumastay`.
- `app_env` deve ser `production`.

### 7.2 Variáveis de ambiente (alternativa)

Se preferires não guardar credenciais no ficheiro, define variáveis no Apache/PHP-FPM:

```apache
SetEnv DB_HOST 127.0.0.1
SetEnv DB_NAME lumastay
SetEnv DB_USER lumastay
SetEnv DB_PASSWORD TROCA_ESTA_PALAVRA_FORTE
SetEnv APP_ENV production
SetEnv APP_URL https://lumastay.csmiguel.pt
```

As variáveis de ambiente têm prioridade sobre `config.local.php`.

### 7.3 Proteger o ficheiro

```bash
sudo chown root:www-data config/config.local.php
sudo chmod 640 config/config.local.php
```

### 7.4 Desativar erros em produção

Edita o `php.ini` do Apache ou do FPM:

```bash
php --ini
sudo nano /etc/php/8.3/apache2/php.ini    # Apache
# ou
sudo nano /etc/php/8.3/fpm/php.ini        # Nginx
```

Garante:

```ini
display_errors = Off
log_errors = On
expose_php = Off
```

Depois:

```bash
sudo systemctl restart apache2
# ou
sudo systemctl restart php8.3-fpm
```

> **Nunca** deixes `APP_DEBUG=1` numa máquina pública. Essa variável mostra detalhes da ligação à base de dados.

---

## 8. Configurar o Apache

### 8.1 Subdomínio (recomendado)

Cria `/etc/apache2/sites-available/lumastay.conf`:

```bash
sudo nano /etc/apache2/sites-available/lumastay.conf
```

Conteúdo:

```apache
<VirtualHost *:80>
    ServerName lumastay.csmiguel.pt
    DocumentRoot /var/www/lumastay

    <Directory /var/www/lumastay>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/lumastay-error.log
    CustomLog ${APACHE_LOG_DIR}/lumastay-access.log combined
</VirtualHost>
```

Ativa:

```bash
sudo a2ensite lumastay.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 8.2 Subpasta (se não puderes criar subdomínio)

No vhost existente de `lab.csmiguel.pt`, adiciona **antes** de outras regras de rewrite:

```apache
Alias /lumastay /var/www/lumastay

<Directory /var/www/lumastay>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Depois:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

> Se a aplicação existente tiver um `RewriteRule` universal, pode intercetar `/lumastay`. Nesse caso, usa mesmo um subdomínio.

### 8.3 Confirmar que o Apache vê o site

```bash
sudo apache2ctl -S
```

Deve aparecer o `ServerName` e o `DocumentRoot` corretos.

---

## 9. Configurar o Nginx (alternativa)

Cria `/etc/nginx/sites-available/lumastay`:

```nginx
server {
    listen 80;
    server_name lumastay.csmiguel.pt;

    root /var/www/lumastay;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param APP_ENV production;
        fastcgi_param APP_URL https://lumastay.csmiguel.pt;
    }

    location ~ /\. {
        deny all;
    }

    access_log /var/log/nginx/lumastay-access.log;
    error_log  /var/log/nginx/lumastay-error.log;
}
```

Ativa:

```bash
sudo ln -s /etc/nginx/sites-available/lumastay /etc/nginx/sites-enabled/lumastay
sudo nginx -t
sudo systemctl reload nginx
```

Ajusta `php8.3-fpm.sock` à versão instalada (`ls /run/php/`).

---

## 10. Cloudflare, DNS e HTTPS

### 10.1 Criar o registo DNS

Se usares `lumastay.csmiguel.pt`, na Cloudflare:

1. **DNS → Records → Add record**.
2. Tipo: `A`.
3. Nome: `lumastay`.
4. IPv4: IP público da VM.
5. Proxy: **Proxied** (nuvem laranja).
6. Guardar.

Se a VM não tiver IP público, usa um **Cloudflare Tunnel** (`cloudflared`) ou pede ao administrador da rede um encaminhamento.

> Não apontes `lumastay.csmiguel.pt` para `lab.csmiguel.pt` sem confirmar que o vhost de origem aceita o novo hostname.

### 10.2 Modo SSL/TLS

Em **SSL/TLS → Overview**:

- **Full (strict)** — recomendado.
- **Full** — aceitável se o certificado de origem for válido para o hostname.
- **Flexible** — evita. A ligação Cloudflare → origem fica em HTTP e o cookie de sessão deixa de ser marcado como `Secure`.
- **Off** — nunca.

Para **Full (strict)**, instala na VM um **Cloudflare Origin Certificate**:

1. Cloudflare → **SSL/TLS → Origin Server → Create Certificate**.
2. Hostnames: `lumastay.csmiguel.pt`.
3. Copia o certificado e a chave para a VM.
4. Configura o vhost Apache/Nginx com esses ficheiros.

Exemplo Apache:

```apache
<VirtualHost *:443>
    ServerName lumastay.csmiguel.pt
    DocumentRoot /var/www/lumastay

    SSLEngine on
    SSLCertificateFile    /etc/ssl/lumastay/origin.pem
    SSLCertificateKeyFile /etc/ssl/lumastay/origin.key

    <Directory /var/www/lumastay>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Ativa `ssl`:

```bash
sudo a2enmod ssl
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 10.3 Se usares subpasta

Não é necessário criar DNS novo. Mantém o certificado existente de `lab.csmiguel.pt` e configura `app_url` com a subpasta.

### 10.4 Certificado Let's Encrypt (só com DNS direto)

Se o hostname não estiver proxied na Cloudflare:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d lumastay.csmiguel.pt
```

---

## 11. Permissões e segurança

### 11.1 Permissões dos ficheiros

```bash
sudo chown -R www-data:www-data /var/www/lumastay
sudo find /var/www/lumastay -type d -exec chmod 755 {} \;
sudo find /var/www/lumastay -type f -exec chmod 644 {} \;
sudo chown root:www-data /var/www/lumastay/config/config.local.php
sudo chmod 640 /var/www/lumastay/config/config.local.php
```

A aplicação não precisa de diretórios com escrita, porque não há upload de imagens.

### 11.2 Alterar a palavra-passe de administração

Gera um hash novo:

```bash
php -r "echo password_hash('NOVA_PALAVRA_PASSE_FORTE', PASSWORD_DEFAULT), PHP_EOL;"
```

Atualiza na base de dados:

```bash
sudo mysql -D lumastay -e "
  UPDATE admin_users
  SET password_hash = 'COLAR_O_HASH_GERADO'
  WHERE email = 'admin@lumastay.pt';
"
```

Ou cria um administrador novo e remove o de demonstração:

```sql
INSERT INTO admin_users (name, email, password_hash)
VALUES ('Administrador', 'o-teu-email@csmiguel.pt', 'HASH_GERADO');
```

### 11.3 Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
# ou: sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

Se a origem só deve aceitar tráfego da Cloudflare, restringe as portas 80/443 aos intervalos publicados em <https://www.cloudflare.com/ips/>. Isto é opcional e exige manutenção.

### 11.4 Cabeçalhos e boas práticas

- `display_errors = Off`.
- `expose_php = Off`.
- Não versionar `config/config.local.php`.
- Fazer backups regulares da base de dados:

```bash
sudo mysqldump --default-character-set=utf8mb4 lumastay \
  | gzip > /var/backups/lumastay-$(date +%F).sql.gz
```

- Manter o sistema atualizado:

```bash
sudo apt update && sudo apt upgrade -y
```

### 11.5 Acesso à administração

O login está em:

```text
https://APP_URL_PUBLICO/login.php
```

Se quiseres, restringe o acesso por IP no Apache:

```apache
<Location /login.php>
    Require ip 203.0.113.0/24
</Location>
```

> Isto pode bloquear o login através da Cloudflare. Nesse caso, a restrição deve ser feita na Cloudflare (WAF/Access), não no Apache.

---

## 12. Verificação final

### 12.1 Testes HTTP

```bash
curl -I https://APP_URL_PUBLICO/
curl -I https://APP_URL_PUBLICO/alojamentos.php
curl -I https://APP_URL_PUBLICO/login.php
```

Esperado: `200 OK` (ou `302` para o login, nas páginas administrativas).

### 12.2 Teste no navegador

1. Abrir a página inicial.
2. Pesquisar por destino e datas.
3. Abrir um alojamento.
4. Fazer uma reserva de teste.
5. Consultar a reserva com código + email.
6. Entrar na administração.
7. Confirmar e cancelar a reserva de teste.
8. Verificar o calendário de disponibilidade.

### 12.3 Verificar logs

```bash
sudo tail -f /var/log/apache2/lumastay-error.log
# ou
sudo tail -f /var/log/nginx/lumastay-error.log
```

### 12.4 Verificar a base de dados

```bash
sudo mysql -D lumastay -e "SELECT COUNT(*) FROM reservations;"
```

### 12.5 Testes automatizados na VM

```bash
cd /var/www/lumastay

DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_NAME=lumastay \
DB_USER=lumastay \
DB_PASSWORD='TROCA_ESTA_PALAVRA_FORTE' \
APP_TEST_URL='https://APP_URL_PUBLICO' \
php tests/run-tests.php
```

Resultado esperado:

```text
Resultado: 29 passaram, 0 falharam, 0 ignorados (total 29)
```

> Os testesHTTP fazem pedidos ao endereço público. Se a Cloudflare bloquear pedidos automatizados, remove `APP_TEST_URL` para executar apenas os testes de domínio e base de dados.

---

## 13. Atualizar a aplicação

```bash
cd /var/www/lumastay
sudo git pull
sudo chown -R www-data:www-data .
sudo chmod 640 config/config.local.php
sudo systemctl reload apache2
# ou: sudo systemctl reload nginx
```

Se houver alterações ao esquema, importa o `schema.sql` com cuidado: **o ficheiro apaga e recria as tabelas**. Faz sempre backup antes.

---

## 14. Resolução de problemas

| Sintoma | Causa provável | Solução |
| --- | --- | --- |
| `500` com "Serviço temporariamente indisponível" | Credenciais ou MySQL em baixo | Verifica `systemctl status mysql`, `config.local.php` e o log |
| `404` em todas as páginas | `DocumentRoot` ou vhost errado | `apache2ctl -S` / `nginx -T`; confirma o `ServerName` |
| `403 Forbidden` | Permissões ou índice desativado | `chown`/`chmod`; confirma `Options -Indexes` |
| CSS/JS não carregam | `app_url` errado ou subpasta mal configurada | Ajusta `app_url` e limpa a cache do navegador |
| Acentos como `?` | Importação sem `utf8mb4` | Reimporta com `--default-character-set=utf8mb4` |
| Redireciona sempre para login | Cookie de sessão bloqueado | Usa HTTPS e `Full (strict)`; confirma `APP_URL` |
| `521`/`522` na Cloudflare | Origem em baixo ou firewall | Verifica Apache/Nginx, `ufw` e IP da VM |
| `too many redirects` | SSL mode `Flexible` + redirecionamento para HTTPS | Muda para `Full (strict)` ou desativa o redirecionamento no origem |
| `git pull` com erro de permissões | Repositório pertence a `www-data` | `sudo git -C /var/www/lumastay pull` como root |
| Erro `SQLSTATE[HY000] [2002]` | MySQL não está a correr ou host/porta errados | `systemctl status mysql`; confirma `db_host`/`db_port` |
| Erro `SQLSTATE[HY000] [1045]` | Utilizador/palavra-passe errados | Recria o utilizador e atualiza `config.local.php` |

### Ver o erro real

```bash
sudo tail -n 100 /var/log/apache2/error.log
sudo tail -n 100 /var/log/apache2/lumastay-error.log
sudo tail -n 100 /var/log/nginx/error.log
sudo journalctl -u apache2 -n 100 --no-pager
```

---

## 15. Se a VM for Windows

Se `lab.csmiguel.pt` correr em Windows Server:

1. Instala **Laragon** ou **XAMPP**.
2. Copia o projeto para `C:\laragon\www\lumastay` (ou `C:\xampp\htdocs\lumastay`).
3. Importa `database/schema.sql` e `database/seed.sql` pelo phpMyAdmin ou linha de comandos, com `utf8mb4`.
4. Cria `config/config.local.php` com as credenciais locais.
5. Cria um VirtualHost no Apache para `lumastay.csmiguel.pt`, com `DocumentRoot "C:/laragon/www/lumastay"`.
6. Adiciona a entrada DNS na Cloudflare e configura o SSL.

O resto dos passos (base de dados, configuração, segurança) é equivalente.

---

## Resumo rápido (Ubuntu + Apache + subdomínio)

```bash
# 1. Dependências
sudo apt update
sudo apt install -y apache2 mysql-server php php-cli php-mysql php-mbstring php-xml php-curl git unzip
sudo a2enmod rewrite headers

# 2. Código
sudo mkdir -p /var/www
sudo git clone https://github.com/kitokaesu/lumastay.git /var/www/lumastay

# 3. Base de dados (cria o utilizador antes, no mysql)
sudo mysql --default-character-set=utf8mb4 < /var/www/lumastay/database/schema.sql
sudo mysql --default-character-set=utf8mb4 < /var/www/lumastay/database/seed.sql

# 4. Configuração
sudo cp /var/www/lumastay/config/config.local.php.example /var/www/lumastay/config/config.local.php
sudo nano /var/www/lumastay/config/config.local.php

# 5. Permissões
sudo chown -R www-data:www-data /var/www/lumastay
sudo find /var/www/lumastay -type d -exec chmod 755 {} \;
sudo find /var/www/lumastay -type f -exec chmod 644 {} \;
sudo chown root:www-data /var/www/lumastay/config/config.local.php
sudo chmod 640 /var/www/lumastay/config/config.local.php

# 6. Apache (cria o vhost antes)
sudo a2ensite lumastay.conf
sudo apache2ctl configtest
sudo systemctl reload apache2

# 7. Testar
curl -I https://lumastay.csmiguel.pt/
```

---

## Notas finais

- O domínio `lab.csmiguel.pt` já tem uma aplicação. **Não apagues nem substituas ficheiros existentes.**
- Usa um subdomínio ou subpasta e confirma sempre o `DocumentRoot`.
- Altera a palavra-passe de administração antes de expor a aplicação.
- Mantém `config/config.local.php` fora do Git.
- Se a Cloudflare estiver em modo `Flexible`, o cookie de sessão não será `Secure`; usa `Full (strict)`.
- Para uma demonstração, a opção mais rápida é `http://IP_DA_VM/lumastay/` com o servidor embutido do PHP ou Apache.
