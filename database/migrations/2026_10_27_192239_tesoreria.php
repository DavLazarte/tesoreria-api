<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tablas de negocio de tesorería (MySQL 8.0.16+): cajas, entidades, categorías,
 * libro de movimientos inmutable, anulaciones, adjuntos, auditoría y vista de saldos.
 * Usa SQL crudo porque Blueprint no soporta columnas generadas, CHECK, vistas ni triggers.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->statements() as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS v_fund_balances');

        foreach ([
            'access_logs', 'audit_logs', 'movement_attachments', 'movement_voids',
            'movements', 'categories', 'entities', 'funds', 'entity_types',
        ] as $table) {
            DB::unprepared("DROP TABLE IF EXISTS {$table}");
        }

        DB::unprepared('ALTER TABLE users DROP COLUMN is_active, DROP COLUMN last_login_at, DROP COLUMN deleted_at');
    }

    private function statements(): array
    {
        return [
<<<'SQL'
ALTER TABLE users
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password,
  ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER is_active,
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
SQL,
<<<'SQL'
CREATE TABLE entity_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY entity_types_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE funds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  kind ENUM('general','group') NOT NULL,
  general_flag TINYINT GENERATED ALWAYS AS (IF(kind = 'general', 1, NULL)) VIRTUAL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY funds_name_unique (name),
  UNIQUE KEY funds_one_general (general_flag),
  CONSTRAINT funds_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE entities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type_id BIGINT UNSIGNED NOT NULL,
  group_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  document_number VARCHAR(30) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  address VARCHAR(255) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY entities_name_index (name),
  KEY entities_document_index (document_number),
  KEY entities_group_index (group_id),
  CONSTRAINT entities_type_fk FOREIGN KEY (entity_type_id) REFERENCES entity_types (id),
  CONSTRAINT entities_group_fk FOREIGN KEY (group_id) REFERENCES funds (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  flow ENUM('ingreso','egreso') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY categories_name_flow_unique (name, flow)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('ingreso','egreso','asignacion','devolucion') NOT NULL,
  movement_date DATE NOT NULL,
  from_fund_id BIGINT UNSIGNED NULL,
  to_fund_id BIGINT UNSIGNED NULL,
  entity_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  amount DECIMAL(15,2) NOT NULL,
  concept VARCHAR(255) NOT NULL,
  reference VARCHAR(60) NULL,
  note TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY movements_date_index (movement_date),
  KEY movements_type_index (type),
  KEY movements_from_date_index (from_fund_id, movement_date),
  KEY movements_to_date_index (to_fund_id, movement_date),
  KEY movements_entity_index (entity_id),
  KEY movements_category_index (category_id),
  CONSTRAINT movements_from_fk FOREIGN KEY (from_fund_id) REFERENCES funds (id),
  CONSTRAINT movements_to_fk FOREIGN KEY (to_fund_id) REFERENCES funds (id),
  CONSTRAINT movements_entity_fk FOREIGN KEY (entity_id) REFERENCES entities (id),
  CONSTRAINT movements_category_fk FOREIGN KEY (category_id) REFERENCES categories (id),
  CONSTRAINT movements_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id),
  CONSTRAINT movements_amount_chk CHECK (amount > 0),
  CONSTRAINT movements_flow_chk CHECK (
    (type = 'ingreso' AND from_fund_id IS NULL AND to_fund_id IS NOT NULL)
    OR (type = 'egreso' AND from_fund_id IS NOT NULL AND to_fund_id IS NULL)
    OR (type IN ('asignacion','devolucion') AND from_fund_id IS NOT NULL AND to_fund_id IS NOT NULL AND from_fund_id <> to_fund_id)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE movement_voids (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  movement_id BIGINT UNSIGNED NOT NULL,
  voided_by BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  voided_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY movement_voids_movement_unique (movement_id),
  CONSTRAINT movement_voids_movement_fk FOREIGN KEY (movement_id) REFERENCES movements (id),
  CONSTRAINT movement_voids_user_fk FOREIGN KEY (voided_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE movement_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  movement_id BIGINT UNSIGNED NOT NULL,
  disk VARCHAR(30) NOT NULL DEFAULT 'local',
  path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NULL,
  size_bytes INT UNSIGNED NULL,
  sha256 CHAR(64) NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY movement_attachments_movement_index (movement_id),
  CONSTRAINT movement_attachments_movement_fk FOREIGN KEY (movement_id) REFERENCES movements (id),
  CONSTRAINT movement_attachments_user_fk FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  event VARCHAR(40) NOT NULL,
  auditable_type VARCHAR(100) NOT NULL,
  auditable_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY audit_logs_auditable_index (auditable_type, auditable_id),
  KEY audit_logs_user_index (user_id, created_at),
  KEY audit_logs_event_index (event, created_at),
  CONSTRAINT audit_logs_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE TABLE access_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  email_attempted VARCHAR(190) NULL,
  event ENUM('login','logout','login_failed') NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY access_logs_user_index (user_id, created_at),
  CONSTRAINT access_logs_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
<<<'SQL'
CREATE VIEW v_fund_balances AS
SELECT f.id AS fund_id, f.name, f.kind,
  COALESCE(SUM(CASE WHEN v.id IS NULL AND m.to_fund_id = f.id THEN m.amount END), 0)
  - COALESCE(SUM(CASE WHEN v.id IS NULL AND m.from_fund_id = f.id THEN m.amount END), 0) AS balance
FROM funds f
LEFT JOIN movements m ON m.to_fund_id = f.id OR m.from_fund_id = f.id
LEFT JOIN movement_voids v ON v.movement_id = m.id
GROUP BY f.id, f.name, f.kind;
SQL,
<<<'SQL'
CREATE TRIGGER movements_no_update BEFORE UPDATE ON movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos no se modifican: se anulan';
SQL,
<<<'SQL'
CREATE TRIGGER movements_no_delete BEFORE DELETE ON movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos no se borran: se anulan';
SQL,
<<<'SQL'
CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se modifica';
SQL,
<<<'SQL'
CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se borra';
SQL,
<<<'SQL'
INSERT INTO entity_types (name, is_active, created_at, updated_at) VALUES
  ('Persona física', 1, NOW(), NOW()), ('Iglesia', 1, NOW(), NOW()), ('Comedor', 1, NOW(), NOW()),
  ('Donante', 1, NOW(), NOW()), ('Proveedor', 1, NOW(), NOW()), ('Otro', 1, NOW(), NOW());
SQL,
<<<'SQL'
INSERT INTO funds (name, kind, is_active, created_at, updated_at) VALUES ('Caja general', 'general', 1, NOW(), NOW());
SQL,
<<<'SQL'
INSERT INTO categories (name, flow, is_active, created_at, updated_at) VALUES
  ('Donaciones', 'ingreso', 1, NOW(), NOW()), ('Depósitos', 'ingreso', 1, NOW(), NOW()), ('Otros ingresos', 'ingreso', 1, NOW(), NOW()),
  ('Ayudas y subsidios', 'egreso', 1, NOW(), NOW()), ('Servicios', 'egreso', 1, NOW(), NOW()),
  ('Gastos generales', 'egreso', 1, NOW(), NOW()), ('Otros egresos', 'egreso', 1, NOW(), NOW());
SQL,
        ];
    }
};