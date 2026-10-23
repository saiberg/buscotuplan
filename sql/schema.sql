-- =====================================================================
--  BuscoTuPlan — Esquema de base de datos
--  MariaDB 10.2+ / MySQL 5.7+ / MySQL 8
--  Charset utf8mb4 (soporta tildes y ñ correctamente)
-- =====================================================================
--
--  IMPORTANTE (Hostinger / producción):
--  1. Crea la base de datos desde hPanel (Bases de datos > MySQL).
--  2. Copia el nombre real (ej: u12345_buscotuplan) en config.php -> DB_NAME.
--  3. Importa SOLO este archivo desde phpMyAdmin.
--  4. No necesitas crear usuarios: hPanel ya te da uno con la BD.
--
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `buscotuplan`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `buscotuplan`;

-- ---------------------------------------------------------------------
--  leads: prospectos capturados por la landing
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id`                   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`               VARCHAR(120)     NOT NULL,
  `telefono`             VARCHAR(32)      NOT NULL COMMENT 'Ej: +56987654321',
  `telefono_normalizado` VARCHAR(32)      NOT NULL COMMENT 'Solo dígitos nacionales',
  `email`                VARCHAR(160)     DEFAULT NULL,
  `isapre_actual`        VARCHAR(60)      DEFAULT NULL,
  `necesidad`            VARCHAR(60)      DEFAULT NULL,
  `cargas`               TINYINT UNSIGNED DEFAULT NULL,
  `mensaje`              TEXT             DEFAULT NULL,

  -- procedencia / marketing (para saber qué campaña funciona)
  `origen`               VARCHAR(40)      NOT NULL DEFAULT 'landing',
  `utm_source`           VARCHAR(120)     DEFAULT NULL,
  `utm_medium`           VARCHAR(120)     DEFAULT NULL,
  `utm_campaign`         VARCHAR(160)     DEFAULT NULL,
  `utm_content`          VARCHAR(160)     DEFAULT NULL,
  `utm_term`             VARCHAR(160)     DEFAULT NULL,
  `variante`             VARCHAR(24)      DEFAULT NULL COMMENT 'CTA que vio: formulario | whatsapp',
  `referrer`             VARCHAR(500)     DEFAULT NULL,
  `landing_url`          VARCHAR(500)     DEFAULT NULL,
  `dispositivo`          VARCHAR(80)      DEFAULT NULL,
  `ip`                   VARCHAR(45)      DEFAULT NULL,
  `user_agent`           VARCHAR(500)     DEFAULT NULL,

  -- seguimiento comercial (lo usará la app de CRM)
  `estado`               ENUM('nuevo','contactado','interesado','cotizado',
                               'convertido','perdido') NOT NULL DEFAULT 'nuevo',
  `consentimiento`       TINYINT(1)       NOT NULL DEFAULT 0,
  `contactado_en`        DATETIME         DEFAULT NULL,
  `notas`                TEXT             DEFAULT NULL,

  `created_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_estado`  (`estado`),
  KEY `idx_created` (`created_at`),
  KEY `idx_tel`     (`telefono_normalizado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  lead_activities: bitácora de cada interacción con un prospecto
--  (base de la app de seguimiento que se construirá después)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_activities` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id`     INT UNSIGNED NOT NULL,
  `tipo`        ENUM('nota','llamada','whatsapp','whatsapp_abierto','email','cambio_estado','tarea')
                                NOT NULL DEFAULT 'nota',
  `direccion`   ENUM('entrada','salida','interna') NOT NULL DEFAULT 'interna',
  `asunto`      VARCHAR(200) DEFAULT NULL,
  `detalle`     TEXT         DEFAULT NULL,
  `creado_por`  VARCHAR(80)  DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead` (`lead_id`, `created_at`),
  CONSTRAINT `fk_activity_lead`
    FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
