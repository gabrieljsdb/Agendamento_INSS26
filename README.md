# Sistema de Agendamento INSS - OAB/SC (Versão Melhorada)

Sistema web para gerenciamento de agendamentos de atendimento do INSS pela OAB/SC, com autenticação via SOAP, notificações por email e painel administrativo.

## 🚀 Principais Melhorias Implementadas

Esta versão representa uma refatoração completa do projeto original, com foco em **segurança**, **arquitetura** e **boas práticas**:

### Segurança
- ✅ Credenciais removidas do código (uso de `.env`)
- ✅ Proteção CSRF em todos os formulários
- ✅ Sessões configuradas de forma segura
- ✅ Validação e sanitização robusta de inputs
- ✅ Prepared statements (PDO) em todas as queries
- ✅ Debug desabilitado em produção
- ✅ Logs estruturados sem exposição de dados sensíveis

### Arquitetura
- ✅ Padrão MVC implementado
- ✅ Separação de responsabilidades (Models, Services, Controllers)
- ✅ Autoloading PSR-4 via Composer
- ✅ Dependency Injection
- ✅ Middleware para autenticação e CSRF
- ✅ Validadores dedicados
- ✅ Configuração centralizada

### Código e Manutenibilidade
- ✅ Código organizado em namespaces
- ✅ Documentação inline (PHPDoc)
- ✅ Funções auxiliares reutilizáveis
- ✅ Tratamento de erros consistente
- ✅ Logs estruturados com Monolog (PSR-3)
- ✅ Migrations para controle de versão do banco

### Funcionalidades
- ✅ Sistema de filas para emails
- ✅ Templates de email
- ✅ Validações robustas de agendamento
- ✅ Logs de auditoria
- ✅ Configurações parametrizadas

---

## 📋 Requisitos

- **PHP** >= 7.4
- **MySQL** >= 5.7 ou **MariaDB** >= 10.2
- **Composer** (gerenciador de dependências)
- **Extensões PHP**: PDO, PDO_MySQL, SOAP, JSON, mbstring

---

## 🔧 Instalação

### 1. Clone o repositório

```bash
git clone <url-do-repositorio>
cd Agendamento_INSS_Melhorado
```

### 2. Instale as dependências

```bash
composer install
```

### 3. Configure o ambiente

Copie o arquivo de exemplo e configure suas credenciais:

```bash
cp .env.example .env
```

Edite o arquivo `.env` e configure:

```env
# Banco de dados
DB_HOST=localhost
DB_DATABASE=sistema_agendamento
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Email
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=seu_email@gmail.com
MAIL_PASSWORD=sua_senha_app

# Administradores
ADMIN_EMAILS=admin1@exemplo.com,admin2@exemplo.com
```

### 4. Crie o banco de dados

```bash
mysql -u root -p
```

```sql
CREATE DATABASE sistema_agendamento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Execute as migrations

```bash
mysql -u seu_usuario -p sistema_agendamento < database/migrations/001_criar_tabelas_iniciais.sql
```

### 6. Configure o servidor web

#### Apache

Crie um VirtualHost apontando para a pasta `public/`:

```apache
<VirtualHost *:80>
    ServerName agendamento.local
    DocumentRoot /caminho/para/Agendamento_INSS_Melhorado/public
    
    <Directory /caminho/para/Agendamento_INSS_Melhorado/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name agendamento.local;
    root /caminho/para/Agendamento_INSS_Melhorado/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 7. Configure permissões

```bash
chmod -R 755 storage logs
chown -R www-data:www-data storage logs
```

---

## 🚀 Como Funciona a Nova Estrutura

O projeto agora utiliza um padrão de **Front Controller**, onde as requisições são processadas por controladores específicos:

- **`public/index.php`**: Dashboard principal (requer login).
- **`public/login.php`**: Gerencia a exibição do formulário e o processamento da autenticação SOAP.
- **`public/agendar.php`**: Processa a criação de novos agendamentos.
- **`public/api_eventos.php`**: Fornece os dados JSON para o FullCalendar.
- **`public/logout.php`**: Finaliza a sessão de forma segura.

## 📁 Estrutura do Projeto

```
Agendamento_INSS_Melhorado/
├── config/                  # Arquivos de configuração
│   └── config.php          # Configuração central
├── database/               # Migrations e seeds
│   ├── migrations/         # Scripts SQL de migração
│   └── seeds/             # Dados iniciais
├── logs/                   # Arquivos de log
├── public/                 # Pasta pública (DocumentRoot)
│   ├── css/
│   ├── js/
│   ├── images/
│   └── index.php          # Ponto de entrada
├── src/                    # Código-fonte da aplicação
│   ├── Controllers/       # Controladores
│   ├── Models/            # Modelos de dados
│   ├── Services/          # Serviços (Email, Auth, etc)
│   ├── Middleware/        # Middlewares
│   ├── Validators/        # Validadores
│   ├── bootstrap.php      # Inicialização da aplicação
│   └── helpers.php        # Funções auxiliares
├── storage/               # Armazenamento de arquivos
│   ├── emails/           # Templates de email
│   └── uploads/          # Uploads de usuários
├── templates/             # Templates de visualização
├── vendor/                # Dependências do Composer
├── .env.example          # Exemplo de configuração
├── .gitignore
├── composer.json         # Dependências e autoload
└── README.md            # Este arquivo
```

---

## 🔐 Segurança

### Credenciais

**NUNCA** commite o arquivo `.env` no Git. Todas as credenciais devem estar neste arquivo.

### CSRF Protection

Todos os formulários POST devem incluir o token CSRF:

```php
<?php echo \App\Middleware\CsrfMiddleware::field(); ?>
```

### Validação de Inputs

Sempre valide e sanitize os inputs do usuário:

```php
use App\Validators\AgendamentoValidator;

$validator = new AgendamentoValidator($config);
if (!$validator->validar($data)) {
    $errors = $validator->getErrors();
    // Tratar erros
}
```

### Sessões Seguras

As sessões já estão configuradas de forma segura no `bootstrap.php`:
- HttpOnly cookies
- Secure cookies (em produção com HTTPS)
- SameSite protection
- Session regeneration após login

---

## 📧 Configuração de Email

### Gmail

1. Ative a verificação em duas etapas
2. Gere uma senha de aplicativo
3. Use a senha de aplicativo no `.env`:

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu_email@gmail.com
MAIL_PASSWORD=sua_senha_app
MAIL_ENCRYPTION=tls
```

### Outros provedores

Consulte a documentação do seu provedor de email para obter as configurações SMTP.

---

## 🔄 Rotina Diária (Cron)

Configure um cron job para enviar a lista de agendamentos diariamente:

```bash
crontab -e
```

Adicione:

```cron
# Envia lista de agendamentos às 18h todos os dias
0 18 * * * /usr/bin/php /caminho/para/rotina_diaria.php >> /caminho/para/logs/cron.log 2>&1
```

---

## 🧪 Testes

Execute os testes (quando implementados):

```bash
composer test
```

---

## 📊 Logs

Os logs são armazenados em `logs/app.log` e rotacionados automaticamente.

Níveis de log disponíveis:
- `debug`: Informações detalhadas para debug
- `info`: Eventos informativos
- `warning`: Avisos que não impedem o funcionamento
- `error`: Erros que precisam de atenção
- `critical`: Erros críticos que podem parar o sistema

---

## 🛠️ Desenvolvimento

### Ambiente de Desenvolvimento

No `.env`, configure:

```env
APP_ENV=development
APP_DEBUG=true
```

### Ambiente de Produção

No `.env`, configure:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

### Padrões de Código

O projeto segue as PSRs (PHP Standards Recommendations):
- PSR-1: Basic Coding Standard
- PSR-4: Autoloading Standard
- PSR-3: Logger Interface
- PSR-12: Extended Coding Style

---

## 📝 Licença

Este projeto é de propriedade da OAB/SC.

---

## 👥 Contribuindo

1. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
2. Commit suas mudanças (`git commit -am 'Adiciona nova funcionalidade'`)
3. Push para a branch (`git push origin feature/nova-funcionalidade`)
4. Abra um Pull Request

---

## 📞 Suporte

Para suporte técnico, entre em contato com a equipe de TI da OAB/SC.

---

## 🔄 Changelog

### Versão 2.0.0 (2026-01-27)

#### Adicionado
- Arquitetura MVC completa
- Sistema de configuração com `.env`
- Proteção CSRF
- Validadores dedicados
- Middleware de autenticação
- Logs estruturados com Monolog
- Migrations de banco de dados
- Sistema de filas para emails
- Funções auxiliares globais
- Documentação completa

#### Modificado
- Refatoração completa da estrutura de código
- Separação de responsabilidades
- Melhoria na segurança de sessões
- Otimização de queries do banco

#### Removido
- Credenciais hardcoded
- Debug em produção
- Código duplicado
- Lógica misturada com apresentação

---

## 📚 Documentação Adicional

- [Guia de Instalação Detalhado](docs/instalacao.md)
- [Documentação da API](docs/api.md)
- [Guia de Contribuição](docs/contribuindo.md)
- [Arquitetura do Sistema](docs/arquitetura.md)

---

**Desenvolvido com ❤️ para OAB/SC**
