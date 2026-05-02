-- ============================================
-- FLYNOW - SISTEMA DE GESTÃO DE COMISSÕES  
-- Schema PostgreSQL 14+
-- ============================================

-- ============================================
-- TABELA: CARGOS
-- ============================================
CREATE TABLE cargos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    setor VARCHAR(20) NOT NULL CHECK (setor IN ('trafego', 'copy', 'operacoes', 'backend', 'outro')),
    percentual_comissao_default DECIMAL(5,2),
    comissiona BOOLEAN DEFAULT TRUE,
    eh_head BOOLEAN DEFAULT FALSE,
    escalonamento_aplicavel BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABELA: COLABORADORES
-- ============================================
CREATE TABLE colaboradores (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14),
    cargo_id INT NOT NULL,
    canal VARCHAR(20),
    salario_base DECIMAL(10,2) NOT NULL,
    percentual_comissao DECIMAL(5,2),
    status VARCHAR(20) DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo', 'em_teste')),
    data_admissao DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

-- Trigger para updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_colaboradores_updated_at BEFORE UPDATE ON colaboradores
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- TABELA: ESCALONAMENTO
-- ============================================
CREATE TABLE escalonamento (
    id SERIAL PRIMARY KEY,
    valor_gatilho DECIMAL(15,2) NOT NULL,
    incremento_percentual DECIMAL(5,2) NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABELA: FECHAMENTOS MENSAIS
-- ============================================
CREATE TABLE fechamentos (
    id SERIAL PRIMARY KEY,
    mes INT NOT NULL,
    ano INT NOT NULL,
    
    lucro_bruto_meta DECIMAL(15,2) DEFAULT 0,
    lucro_bruto_google DECIMAL(15,2) DEFAULT 0,
    lucro_bruto_taboola DECIMAL(15,2) DEFAULT 0,
    lucro_bruto_tiktok DECIMAL(15,2) DEFAULT 0,
    
    faturamento_backend DECIMAL(15,2) DEFAULT 0,
    custos_backend DECIMAL(15,2) DEFAULT 0,
    custos_terceirizados DECIMAL(15,2) DEFAULT 0,
    
    percentual_imposto DECIMAL(5,2) DEFAULT 12,
    percentual_reembolso DECIMAL(5,2) DEFAULT 15,
    percentual_outras DECIMAL(5,2) DEFAULT 0,
    descricao_outras TEXT,
    
    lucro_bruto_total DECIMAL(15,2),
    lucro_liquido_total DECIMAL(15,2),
    total_salarios DECIMAL(15,2),
    total_comissoes DECIMAL(15,2),
    total_bonus DECIMAL(15,2),
    total_folha DECIMAL(15,2),
    percentual_folha_lucro DECIMAL(5,2),
    
    meta_escalonamento_atingida BOOLEAN DEFAULT FALSE,
    
    status VARCHAR(20) DEFAULT 'rascunho' CHECK (status IN ('rascunho', 'calculado', 'aprovado', 'pago')),
    data_aprovacao TIMESTAMP,
    aprovado_por INT,
    data_pagamento DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE (mes, ano)
);

CREATE TRIGGER update_fechamentos_updated_at BEFORE UPDATE ON fechamentos
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- TABELA: LUCRO POR COLABORADOR
-- ============================================
CREATE TABLE lucro_colaborador (
    id SERIAL PRIMARY KEY,
    fechamento_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    lucro_gerado DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fechamento_id) REFERENCES fechamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id),
    UNIQUE (fechamento_id, colaborador_id)
);

-- ============================================
-- TABELA: COMISSÕES CALCULADAS
-- ============================================
CREATE TABLE comissoes (
    id SERIAL PRIMARY KEY,
    fechamento_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    valor_comissao DECIMAL(15,2) NOT NULL DEFAULT 0,
    percentual_aplicado DECIMAL(5,2) NOT NULL,
    bonus DECIMAL(15,2) DEFAULT 0,
    deducoes DECIMAL(15,2) DEFAULT 0,
    valor_liquido DECIMAL(15,2) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fechamento_id) REFERENCES fechamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id),
    UNIQUE (fechamento_id, colaborador_id)
);

-- ============================================
-- TABELA: USUÁRIOS
-- ============================================
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) DEFAULT 'usuario' CHECK (perfil IN ('admin', 'gestor', 'usuario')),
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABELA: LOGS DE AUDITORIA
-- ============================================
CREATE TABLE logs (
    id SERIAL PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(50) NOT NULL,
    tabela VARCHAR(50),
    registro_id INT,
    dados_anteriores TEXT,
    dados_novos TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX idx_logs_usuario ON logs(usuario_id);
CREATE INDEX idx_logs_created ON logs(created_at DESC);

-- ============================================
-- TABELA: CONFIGURAÇÕES DO SISTEMA
-- ============================================
CREATE TABLE configuracoes (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_configuracoes_updated_at BEFORE UPDATE ON configuracoes
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- ÍNDICES
-- ============================================
CREATE INDEX idx_colaboradores_status ON colaboradores(status);
CREATE INDEX idx_colaboradores_cargo ON colaboradores(cargo_id);
CREATE INDEX idx_fechamentos_mes_ano ON fechamentos(ano, mes);
CREATE INDEX idx_comissoes_fechamento ON comissoes(fechamento_id);
CREATE INDEX idx_comissoes_colaborador ON comissoes(colaborador_id);
