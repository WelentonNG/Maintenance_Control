<div align="center">

# 🔧 Sistema de Controle de Manutenção V1.0

### Plataforma completa para gerenciamento de manutenção industrial e predial

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Apache](https://img.shields.io/badge/Apache-2.4%2B-D22128?logo=apache&logoColor=white)](https://httpd.apache.org/)

**Desenvolvido por [WelentonNG](https://github.com/WelentonNG)** 

---

</div>

## 📋 Índice

- [Sobre o Projeto](#-sobre-o-projeto)
- [Funcionalidades](#-funcionalidades)
- [Tecnologias](#-tecnologias)
- [Pré-requisitos](#-pré-requisitos)
- [Instalação](#-instalação)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Credenciais de Acesso](#-credenciais-de-acesso)
- [Níveis de Acesso](#-níveis-de-acesso)
- [Segurança](#-segurança)
- [Licença](#-licença)
- [Contato](#-contato)

---

## 🎯 Sobre o Projeto

O **Sistema de Controle de Manutenção** é uma plataforma web completa desenvolvida para gerenciar todas as atividades relacionadas à manutenção de equipamentos industriais e prediais. Com uma interface moderna e intuitiva, o sistema oferece controle total sobre ordens de serviço, equipamentos, técnicos, peças e relatórios.

### ✨ Diferenciais

- 🎨 **Interface Moderna** - Design responsivo e intuitivo
- 🔐 **Sistema de Autenticação por Username** - Login seguro com nome de usuário
- 📊 **Dashboards Interativos** - Visualização de dados em tempo real
- 🔧 **Gestão Completa** - Controle total de manutenção preventiva e corretiva
- 📈 **Relatórios Detalhados** - Análises e estatísticas completas
- 🌐 **100% Web** - Acesse de qualquer dispositivo

---

## 🚀 Funcionalidades

### 📊 Dashboard Interativo
- Visão geral de estatísticas em tempo real
- Gráficos de evolução mensal de manutenções
- Indicadores de performance (KPIs)
- Últimas ordens de serviço
- Próximas manutenções preventivas agendadas

### 🔧 Gestão de Ordens de Serviço
- CRUD completo de ordens de manutenção
- Tipos: Preventiva, Corretiva, Preditiva, Emergência
- Prioridades: Baixa, Média, Alta, Urgente
- Status: Pendente, Em Andamento, Aguardando Peças, Concluída, Cancelada
- Atribuição de técnicos e equipamentos
- Histórico completo de manutenções

### 🏭 Gestão de Equipamentos
- Cadastro completo de equipamentos
- Código único para identificação
- Categorização por tipo
- Localização detalhada (prédio, andar, departamento)
- Controle de garantia
- Status operacional
- Níveis de criticidade

### 👨‍🔧 Gestão de Técnicos
- Cadastro vinculado a usuários do sistema
- Especialização e certificações
- Controle de disponibilidade
- Estatísticas de performance
- Valor/hora de trabalho

### 📦 Controle de Peças e Materiais
- Gestão de estoque
- Alertas de estoque mínimo
- Controle de preços e fornecedores
- Localização no almoxarifado
- Histórico de utilização

### 📅 Manutenções Preventivas
- Programação de manutenções recorrentes
- Frequências configuráveis (Diária até Anual)
- Geração automática de ordens de serviço
- Notificações de vencimento

### 📈 Relatórios e Análises
- Relatórios por período personalizado
- Top equipamentos com mais manutenções
- Performance dos técnicos
- Análise de custos por categoria
- Gráficos e estatísticas detalhadas
- Exportação de dados

### ⚙️ Administração
- Gestão completa de usuários
- Controle de permissões por nível
- Categorias e localizações
- Configurações do sistema
- Logs de atividades

---

## 🛠️ Tecnologias

- **Backend:** PHP 7.4+
- **Banco de Dados:** MySQL 5.7+
- **Servidor:** Apache 2.4+ (XAMPP)
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Bibliotecas:** 
  - Chart.js - Gráficos interativos
  - Font Awesome - Ícones
  - Google Fonts (Inter) - Tipografia
- **Arquitetura:** MVC Pattern
- **Segurança:** 
  - PDO Prepared Statements
  - Password Hashing (bcrypt)
  - CSRF Protection
  - SQL Injection Prevention

---

## 📋 Pré-requisitos

Para executar este projeto, você precisa ter instalado:

- **XAMPP** (ou Apache + PHP + MySQL separadamente)
  - PHP 7.4 ou superior
  - MySQL 5.7 ou superior
  - Apache 2.4 ou superior
- **Navegador Web moderno** (Chrome, Firefox, Edge, Safari)

---

## 🔧 Instalação

### Passo 1: Clone o Repositório

```bash
git clone https://github.com/WelentonNG/Maintenance_Control.git
```

### Passo 2: Configure o XAMPP

1. Copie a pasta do projeto para `C:\xampp\htdocs\` (Windows) ou `/opt/lampp/htdocs/` (Linux)
2. Abra o XAMPP Control Panel
3. Inicie os serviços **Apache** e **MySQL**

### Passo 3: Crie o Banco de Dados

**Opção 1: Via phpMyAdmin**
1. Acesse: `http://localhost/phpmyadmin`
2. Clique em "Importar"
3. Selecione o arquivo `database/maintenance_control.sql`
4. Clique em "Executar"

**Opção 2: Via Linha de Comando**
```bash
mysql -u root -p < database/maintenance_control.sql
```

### Passo 4: Configure a Conexão

Edite o arquivo `config/database.php` se necessário:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'maintenance_control');
define('DB_USER', 'root');
define('DB_PASS', ''); // Sua senha do MySQL
```

### Passo 5: Acesse o Sistema

Abra seu navegador e acesse:
```
http://localhost/Maintenance_Control/
```

O sistema redirecionará automaticamente para a tela de login.

---

## 📁 Estrutura do Projeto

```
Maintenance_Control/
│
├── 📁 backend/                    # Arquivos PHP do sistema
│   ├── categories.php             # Gestão de categorias
│   ├── equipment.php              # Gestão de equipamentos
│   ├── forgot-password.php        # Recuperação de senha
│   ├── index.php                  # Dashboard principal
│   ├── locations.php              # Gestão de localizações
│   ├── login.php                  # Autenticação de usuários
│   ├── logout.php                 # Encerramento de sessão
│   ├── orders.php                 # Ordens de serviço
│   ├── parts.php                  # Gestão de peças
│   ├── preventive.php             # Manutenções preventivas
│   ├── profile.php                # Perfil do usuário
│   ├── register.php               # Cadastro de usuários
│   ├── reports.php                # Relatórios
│   ├── settings.php               # Configurações
│   ├── technicians.php            # Gestão de técnicos
│   └── users.php                  # Administração de usuários
│
|  
│
├── 📁 assets/                     # Recursos estáticos
│   ├── 📁 css/                    # Folhas de estilo
│   │   ├── style.css              # Estilos gerais
│   │   ├── dashboard.css          # Estilos do dashboard
│   │   └── auth.css               # Estilos de autenticação
│   ├── 📁 js/                     # Scripts JavaScript
│   │   └── app.js                 # JavaScript principal
│   ├── 📁 images/                 # Imagens do sistema
│   └── 📁 uploads/                # Uploads de usuários
│
├── 📁 includes/                   # Componentes reutilizáveis
│   ├── header.php                 # Cabeçalho comum
│   └── footer.php                 # Rodapé comum
│
├── 📁 docs/                       # Documentação
│
├── .htaccess                      # Configurações Apache
├── .gitignore                     # Arquivos ignorados pelo Git
├── index.php                      # Ponto de entrada principal
└── README.md                      # Este arquivo
```

---

## 👥 Níveis de Acesso

| Funcionalidade | 👨‍💼 Admin | 👔 Gerente | 👨‍🔧 Técnico | 👤 Usuário |
|----------------|:----------:|:----------:|:------------:|:----------:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Ordens de Serviço | ✅ | ✅ | ✅ | ✅ |
| Equipamentos | ✅ | ✅ | ✅ | 👁️ |
| Técnicos | ✅ | ✅ | ✅ | 👁️ |
| Peças e Materiais | ✅ | ✅ | ✅ | ❌ |
| Manutenção Preventiva | ✅ | ✅ | ✅ | ❌ |
| Relatórios | ✅ | ✅ | ❌ | ❌ |
| Gestão de Usuários | ✅ | ❌ | ❌ | ❌ |
| Configurações | ✅ | ❌ | ❌ | ❌ |
| Categorias/Locais | ✅ | ❌ | ❌ | ❌ |

> **Legenda:** ✅ Acesso Total | 👁️ Apenas Visualização | ❌ Sem Acesso

---

## 🔒 Segurança

O sistema implementa múltiplas camadas de segurança:

### Autenticação e Autorização
- ✅ Autenticação por **nome de usuário** (username) e senha
- ✅ Senhas criptografadas com **bcrypt** (PASSWORD_DEFAULT)
- ✅ Sistema de sessões seguro
- ✅ Controle de permissões por níveis de acesso
- ✅ Cookie "Lembrar-me" com tokens seguros

### Proteção de Dados
- ✅ PDO com **Prepared Statements** (proteção contra SQL Injection)
- ✅ Sanitização de todos os inputs do usuário
- ✅ Validação de dados no servidor
- ✅ Headers de segurança (X-Frame-Options, X-XSS-Protection, etc.)
- ✅ Proteção de arquivos sensíveis via .htaccess

### Logs e Auditoria
- ✅ Sistema de logs de todas as ações
- ✅ Registro de IP e User Agent
- ✅ Rastreamento de modificações em registros

---

## 📝 Licença

Este projeto está sob a licença **MIT**. Veja o arquivo [LICENSE](docs/LICENSE) para mais detalhes.

---

## 📧 Contato

**WelentonNG** - Proprietário e Desenvolvedor Principal

- GitHub: [@WelentonNG](https://github.com/WelentonNG)
- Email: welenton24@gmail.com

### 🤝 Contribuições

Contribuições são bem-vindas! Sinta-se à vontade para:

1. Fazer um Fork do projeto
2. Criar uma Branch para sua Feature (`git checkout -b feature/NovaFuncionalidade`)
3. Commit suas mudanças (`git commit -m 'Adiciona nova funcionalidade'`)
4. Push para a Branch (`git push origin feature/NovaFuncionalidade`)
5. Abrir um Pull Request

---

## 🎉 Agradecimentos

Obrigado por usar o **Sistema de Controle de Manutenção**! 

Se este projeto foi útil para você, considere dar uma ⭐ no repositório!

---

<div align="center">

**Desenvolvido por [WelentonNG](https://github.com/WelentonNG)**

© 2026 Sistema de Controle de Manutenção - Todos os direitos reservados

</div>
