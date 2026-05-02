-- ============================================
-- FLYNOW - DADOS INICIAIS (SEED)
-- ============================================

-- ============================================
-- CARGOS (conforme PRD seção 3)
-- ============================================
INSERT INTO cargos (nome, setor, percentual_comissao_default, comissiona, eh_head, escalonamento_aplicavel) VALUES
('Copy de Ads', 'copy', 2.50, TRUE, FALSE, TRUE),
('Copy de Oferta', 'copy', 2.50, TRUE, FALSE, TRUE),
('Gestor de Tráfego Meta', 'trafego', 2.00, TRUE, FALSE, FALSE),
('Gestor de Tráfego Google', 'trafego', 2.00, TRUE, FALSE, FALSE),
('Gestor de Tráfego Taboola', 'trafego', 2.00, TRUE, FALSE, FALSE),
('Gestor de Tráfego TikTok', 'trafego', 2.00, TRUE, FALSE, FALSE),
('Head de Tráfego', 'trafego', 1.00, TRUE, TRUE, FALSE),
('Head de Copy', 'copy', 1.00, TRUE, TRUE, FALSE),
('Head de Operações', 'operacoes', 1.50, TRUE, TRUE, FALSE),
('Editor de Vídeo', 'outro', NULL, FALSE, FALSE, FALSE),
('SAC', 'backend', NULL, FALSE, FALSE, FALSE);

-- ============================================
-- ESCALONAMENTO (R$ 1.000.000 = +0.5%)
-- ============================================
INSERT INTO escalonamento (valor_gatilho, incremento_percentual, ativo) VALUES
(1000000.00, 0.50, TRUE);

-- ============================================
-- USUÁRIO ADMIN PADRÃO
-- Senha: admin123
-- IMPORTANTE: Altere esta senha após fazer login!
-- ============================================
INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo) VALUES
('Administrador', 'admin@flynow.com', '$2y$10$IAC81LRUbic4AD85oR0idOReeAm4HWPSCdzHuEuMDMVnfvPlcTxbi', 'admin', TRUE);

-- ============================================
-- CONFIGURAÇÕES PADRÃO
-- ============================================
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('percentual_imposto_default', '12', 'Percentual de imposto padrão para deduções'),
('percentual_reembolso_default', '15', 'Percentual de reembolso/chargeback padrão'),
('percentual_outras_default', '0', 'Percentual de outras deduções padrão'),
('alerta_percentual_folha', '25', 'Alerta quando folha excede este % do lucro'),
('session_timeout_minutes', '30', 'Tempo de expiração da sessão em minutos');
