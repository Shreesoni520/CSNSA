# CSNSA

Sistema de gestão de **Recursos Humanos e Assiduidade** para equipas e organizações. Painel administrativo web para funcionários, registo de ponto, turnos, escalas, ausências, banco de horas e relatórios.

---

## Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Dashboard** | Visão geral de presenças, estatísticas e notificações |
| **Funcionários** | Cadastro, edição, arquivo e exportação (PDF / CSV) |
| **Equipas** | Gestão de departamentos e equipas |
| **Registo de Ponto** | Consulta e gestão de registos de entrada/saída |
| **Relógios de Ponto** | Integração com dispositivos biométricos (protocolo iClock) |
| **Turnos** | Definição de horários e associação a funcionários |
| **Escala Mensal** | Planeamento de escalas com vista em lista ou grelha; importação CSV |
| **Ausências** | Pedidos, justificações e relatórios de ausências |
| **Banco de Horas** | Controlo de horas trabalhadas, extras e saldos |
| **Relatórios de Horas** | Relatórios detalhados por período |
| **Utilizadores** | Gestão de contas e permissões por papel |

### Outras capacidades

- Tema claro / escuro
- Permissões granulares por módulo
- Upload de fotos de funcionários e documentos de justificação
- Migrações automáticas de base de dados ao iniciar
- API REST para relógios de ponto e picagens manuais

---

## Requisitos

- **PHP** 7.4+ (recomendado 8.x)
- **MySQL** ou **MariaDB**
- **Apache** (ex.: XAMPP, WAMP, LAMP)
- Extensões PHP: `mysqli`, `json`, `mbstring`, `gd` (upload de imagens)

---

## Instalação

### 1. Clonar o repositório

```bash
git clone https://github.com/Shreesoni520/CSNSA.git
cd CSNSA
```

Coloque a pasta no diretório do servidor web (ex.: `C:\xampp\htdocs\CSNSA`).

### 2. Configurar a aplicação

```bash
copy config.example.php config.php
```

Edite `config.php` com as credenciais da sua base de dados:

```php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "csnsa";
```

### 3. Criar a base de dados

1. Abra o **phpMyAdmin** ou o cliente MySQL
2. Crie uma base de dados chamada `csnsa`
3. Importe o ficheiro `csnsa.sql` (disponível localmente após instalação; não está no repositório por segurança)

> Se a base de dados ainda não existir, algumas páginas pedem a importação de `csnsa.sql` antes de funcionar.

### 4. Permissões de pastas

Garanta que o servidor web pode escrever nestas pastas:

```
uploads/
uploads/funcionarios/
uploads/users/
admin/uploads/
admin/uploads/ausencias/
```

### 5. Aceder ao sistema

Abra no browser:

```
http://localhost/CSNSA/
```

Será redirecionado para o painel de administração.

### 6. Primeiro utilizador

Na **primeira instalação** (sem utilizadores na base de dados), é possível criar a conta de administrador em:

```
http://localhost/CSNSA/admin/index.php?csnsa=auth-register
```

Depois do primeiro registo, o registo público fica automaticamente desativado.

---

## Configuração avançada

### Relógios de ponto / API biométrica

Em `config.php`:

```php
$ponto_api_secret = '';                    // Token secreto para a API (opcional)
$ponto_auto_registar_dispositivos = true;  // Registar dispositivos automaticamente
```

### Endpoints da API

| Endpoint | Descrição |
|----------|-----------|
| `api/punch.php` | Picagem manual (JSON) |
| `api/iclock/cdata.php` | Protocolo iClock — envio de dados |
| `api/iclock/getrequest.php` | Protocolo iClock — pedidos do dispositivo |

### Fuso horário

O sistema usa `Europe/Lisbon` por defeito (definido em `config.php`).

---

## Estrutura do projeto

```
CSNSA/
├── index.php              # Redireciona para o admin
├── config.php             # Configuração local (não versionado)
├── config.example.php     # Modelo de configuração
├── admin/                 # Painel administrativo
│   ├── index.php          # Router (?csnsa=pagina)
│   ├── includes/          # Autenticação, helpers, layouts
│   ├── funcoes/           # Lógica de negócio
│   ├── css/ & js/         # Interface
│   └── uploads/           # Documentos de ausências (local)
├── api/                   # API para relógios de ponto
├── fpdf/                  # Geração de PDFs
└── uploads/               # Fotos de funcionários e utilizadores (local)
```

---

## Segurança

Os seguintes ficheiros/pastas **não devem** ser expostos publicamente no repositório:

- `config.php` — credenciais da base de dados
- `csnsa.sql` — dump da base de dados
- `uploads/` e `admin/uploads/` — dados pessoais e documentos

Recomenda-se usar um repositório **privado** para este tipo de aplicação.

---

## Tecnologias

- **Backend:** PHP, MySQL/MariaDB
- **Frontend:** Bootstrap 4, jQuery, DataTables, Feather Icons
- **PDF:** FPDF
- **Dispositivos:** Protocolo ZKTeco iClock

---

## Licença

Projeto interno / proprietário. A biblioteca FPDF incluída está sob a [licença FPDF](fpdf/license.txt).

---

## Autor

**Shreesoni520** — [GitHub](https://github.com/Shreesoni520)
