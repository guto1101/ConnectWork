-- ConnectWork — Migração das lacunas administrativas
-- Aplicar uma única vez no banco connectwork já existente.

START TRANSACTION;

-- Configuração de geofence padrão da empresa.
ALTER TABLE empresa_config
  ADD COLUMN cerca_padrao_id INT(10) UNSIGNED NULL AFTER exigir_gps,
  ADD KEY idx_config_cerca_padrao (cerca_padrao_id),
  ADD CONSTRAINT fk_config_cerca_padrao
    FOREIGN KEY (cerca_padrao_id) REFERENCES cercas_virtuais (id)
    ON DELETE SET NULL;

-- Feriados específicos de cada empresa.
CREATE TABLE feriados (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa_id INT(10) UNSIGNED NOT NULL,
  data DATE NOT NULL,
  nome VARCHAR(120) NOT NULL,
  tipo ENUM('nacional','estadual','municipal','empresa') NOT NULL DEFAULT 'empresa',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feriado_empresa_data (empresa_id, data),
  KEY idx_feriado_empresa_data (empresa_id, data),
  CONSTRAINT fk_feriado_empresa
    FOREIGN KEY (empresa_id) REFERENCES empresas (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A marcação de disponibilidade passa a ter decisão administrativa.
ALTER TABLE disponibilidade
  ADD COLUMN status ENUM('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente' AFTER disponivel,
  ADD COLUMN decidido_por_usuario_id INT(10) UNSIGNED NULL AFTER observacao,
  ADD COLUMN decidido_em DATETIME NULL AFTER decidido_por_usuario_id,
  ADD COLUMN motivo_decisao VARCHAR(255) NULL AFTER decidido_em,
  ADD KEY idx_disp_empresa_status_data (empresa_id, status, data),
  ADD KEY idx_disp_decidido_por (decidido_por_usuario_id),
  ADD CONSTRAINT fk_disp_decidido_por
    FOREIGN KEY (decidido_por_usuario_id) REFERENCES usuarios (id)
    ON DELETE SET NULL;

-- Registros legados disponíveis passam a aguardar decisão; indisponibilidades
-- anteriores são tratadas como recusadas e não entram na fila.
UPDATE disponibilidade
   SET status = CASE WHEN disponivel = 1 THEN 'pendente' ELSE 'recusada' END;

COMMIT;
