-- ============================================================
--  Rutas Seguras CBTis 258 - Esquema de Base de Datos
--  Motor: MySQL / MariaDB (compatible con XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS rutas_seguras
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rutas_seguras;

-- ------------------------------------------------------------
-- Tabla: reportes de la comunidad estudiantil
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type        VARCHAR(80)  NOT NULL,
    location    VARCHAR(255) NOT NULL,
    description TEXT         NOT NULL,
    latitude    DECIMAL(10,7) NOT NULL,
    longitude   DECIMAL(10,7) NOT NULL,
    severity    ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
    status      ENUM('activo','resuelto')   NOT NULL DEFAULT 'activo',
    likes       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status   (status),
    INDEX idx_type     (type),
    INDEX idx_created  (created_at)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Cuentas administrativas (no se crean usuarios por registro web)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
-- Sin reportes de ejemplo; esta tabla inicia vacía.

CREATE TABLE IF NOT EXISTS campus_location (
  id          TINYINT UNSIGNED PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  address     VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  latitude    DECIMAL(10,7) NOT NULL,
  longitude   DECIMAL(10,7) NOT NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS safe_points (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  address     VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  latitude    DECIMAL(10,7) NOT NULL,
  longitude   DECIMAL(10,7) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_safe_point_name (name)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS safe_routes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  color       CHAR(7) NOT NULL DEFAULT '#059669',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS safe_route_points (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id    INT UNSIGNED NOT NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL,
  latitude    DECIMAL(10,7) NOT NULL,
  longitude   DECIMAL(10,7) NOT NULL,
  UNIQUE KEY uq_route_sequence (route_id, sequence_no),
  CONSTRAINT fk_safe_route_points_route FOREIGN KEY (route_id)
    REFERENCES safe_routes(id) ON DELETE CASCADE
) ENGINE = InnoDB;



