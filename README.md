# 🚀 Flynow - Sistema de Gestão de Comissões

Sistema completo de gestão de comissões para agência digital, desenvolvido em **PHP + PostgreSQL**.

## 📋 Tecnologias Utilizadas

- **Backend:** PHP 8.1+ com PDO
- **Banco de Dados:** PostgreSQL 14+ (Supabase)
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Deploy:** Render.com
- **Hospedagem BD:** Supabase

---

## 🎯 Funcionalidades Principais

✅ **Gestão de Colaboradores** - Cadastro completo com cargos e comissões  
✅ **Cálculo de Comissões** - Sistema automatizado por canal (Meta, Google, Taboola, TikTok)  
✅ **Fechamento Mensal** - Controle de lucros e deduções  
✅ **Escalonamento** - Bônus por meta atingida  
✅ **Relatórios** - Visualização detalhada de comissões  
✅ **Auditoria** - Logs de todas as ações do sistema  
✅ **Autenticação** - Sistema de login seguro com CSRF protection  

---

## 🔧 Configuração Local

### Pré-requisitos
- PHP 8.1+
- PostgreSQL 14+
- Composer (opcional)

### Passo a Passo

1. **Clone o repositório**
```bash
git clone https://github.com/seu-usuario/flynow.git
cd flynow
```

2. **Configure o banco de dados**

Crie um arquivo `.env` baseado no `.env.example`:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=flynow_gestao
DB_USER=postgres
DB_PASS=sua_senha
SITE_URL=http://localhost
BASE_PATH=
```

3. **Execute as migrations**

```bash
psql -U postgres -d flynow_gestao -f sql/schema.sql
psql -U postgres -d flynow_gestao -f sql/seed.sql
```

4. **Acesse o sistema**

Abra `http://localhost` no navegador.

**Login padrão:**
- Email: `admin@flynow.com`
- Senha: `admin123`

⚠️ **IMPORTANTE:** Altere a senha após o primeiro login!

---

## 🚀 Deploy no Render + Supabase

### 1. Configurar Supabase

1. Acesse [supabase.com](https://supabase.com)
2. Crie um novo projeto (ex: `flynow-production`)
3. Anote as credenciais de conexão:
   - Host: `aws-0-us-east-1.pooler.supabase.com`
   - Port: `5432`
   - Database: `postgres`
   - User: `postgres.xxxxx`
   - Password: (sua senha)

4. No Supabase SQL Editor, execute:
```sql
-- Copie todo o conteúdo de sql/schema.sql
-- Depois execute sql/seed.sql
```

### 2. Configurar Render

1. Acesse [render.com](https://render.com)
2. Conecte seu repositório GitHub
3. Crie um novo **Web Service**
4. Configurações:
   - **Name:** `flynow`
   - **Environment:** `PHP`
   - **Build Command:** (deixe vazio)
   - **Start Command:** (deixe vazio)

5. **Environment Variables** (adicione estas):
```
DB_HOST=aws-0-us-east-1.pooler.supabase.com
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.xxxxx
DB_PASS=sua_senha_supabase
SITE_URL=https://flynow.onrender.com
BASE_PATH=
```

6. Clique em **Create Web Service**

7. Aguarde o deploy (3-5 minutos)

8. Acesse `https://flynow.onrender.com`

---

## 📊 Estrutura do Banco de Dados

### Tabelas Principais

- **cargos** - Cargos da empresa (Copy, Tráfego, etc)
- **colaboradores** - Dados dos colaboradores
- **fechamentos** - Fechamentos mensais de lucro
- **comissoes** - Comissões calculadas
- **lucro_colaborador** - Lucro gerado por colaborador (copys)
- **usuarios** - Usuários do sistema
- **logs** - Auditoria de ações
- **configuracoes** - Configurações do sistema

---

## 🔐 Segurança

✅ Senhas criptografadas com bcrypt  
✅ Proteção CSRF em formulários  
✅ Prepared statements (proteção SQL Injection)  
✅ Sanitização de inputs  
✅ Logs de auditoria  
✅ Timeout de sessão (30 minutos)  

---

## 📝 Conversão MySQL → PostgreSQL

### Principais Mudanças

1. **AUTO_INCREMENT → SERIAL**
```sql
-- MySQL
id INT PRIMARY KEY AUTO_INCREMENT

-- PostgreSQL
id SERIAL PRIMARY KEY
```

2. **ENUM → VARCHAR com CHECK**
```sql
-- MySQL
status ENUM('ativo', 'inativo')

-- PostgreSQL
status VARCHAR(20) CHECK (status IN ('ativo', 'inativo'))
```

3. **DATETIME → TIMESTAMP**
```sql
-- MySQL
created_at DATETIME DEFAULT CURRENT_TIMESTAMP

-- PostgreSQL
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

4. **ON UPDATE CURRENT_TIMESTAMP → Trigger**
```sql
-- PostgreSQL requer trigger:
CREATE TRIGGER update_table_updated_at 
BEFORE UPDATE ON table_name
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

5. **ENGINE e CHARSET removidos**
```sql
-- MySQL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PostgreSQL
);
```

---

## 🎓 Para a Entrevista

### Pontos a Destacar:

1. **Migração MySQL → PostgreSQL**
   - Identifiquei diferenças de sintaxe
   - Converti ENUMs para CHECK constraints
   - Implementei triggers para updated_at
   - Adaptei conexão PDO

2. **Deploy em Produção**
   - Configuração Render (gratuito, escalável)
   - Integração com Supabase (PostgreSQL gerenciado)
   - Variáveis de ambiente para segurança
   - Sistema funcional em produção

3. **Boas Práticas**
   - PDO com prepared statements
   - Separação de configurações (.env)
   - Logs de auditoria
   - CSRF protection
   - Código limpo e comentado

---

## 📧 Suporte

Para dúvidas sobre o sistema, entre em contato:
- Email: admin@flynow.com

---

## 📄 Licença

Este projeto foi desenvolvido como parte de processo seletivo.

---

**Desenvolvido com ❤️ para Flynow**
