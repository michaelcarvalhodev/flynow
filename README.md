# Sistema de Gestão de Comissões - FlyNow

Sistema web para cálculo automático de comissões com regras por setor.

**Demo:** https://flynow-8kzu.onrender.com  
**Login:** `admin@flynow.com` / `admin123`

## Stack

PHP 8.1 | PostgreSQL | Docker | Render

## Funcionalidades

- Cálculo automático de comissões por setor
- Sistema de escalonamento e metas
- Fluxo de aprovação de pagamentos
- Exportação CSV
- Controle de permissões

## Processo

1. **Desenvolvimento:** XAMPP + MySQL local
2. **Migração:** MySQL → PostgreSQL (adaptações de sintaxe e tipos para as ferramentas usadas)
3. **Deploy:** Docker + Supabase + Render

## Instalação

```bash
git clone https://github.com/michaelcarvalhodev/flynow.git
cd flynow
# Configure .env com credenciais Supabase
# Execute sql/schema.sql e sql/seed.sql
docker build -t flynow .
docker run -p 8080:80 --env-file .env flynow
```

