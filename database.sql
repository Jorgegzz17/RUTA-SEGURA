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

-- ------------------------------------------------------------
-- Datos de ejemplo (semilla)
-- ------------------------------------------------------------
INSERT INTO reports (type, location, description, latitude, longitude, severity, likes, created_at) VALUES
('Luz Fundida / Zona Oscura', 'Calle Plinio D. Ordóñez tramo cercano a cancha', 'Falta iluminación en la luminaria #4. Se recomienda caminar en grupos.', 25.7956000, -100.3332000, 'alta', 12, NOW() - INTERVAL 2 HOUR),
('Tráfico / Banqueta Obstruida', 'Cruce Raúl Salinas y Plinio Ordóñez', 'Tráfico pesado a las 13:45 hrs en cambio de turno. Precaución al cruzar.', 25.7964000, -100.3318000, 'media', 8, NOW() - INTERVAL 5 HOUR),
('Zona Solitaria / Sospechosa', 'Terreno baldío atrás de la parada Transmetro', 'Terreno sin iluminación, evitar pasar solos después de las 19:00 hrs.', 25.7971000, -100.3301000, 'alta', 5, NOW() - INTERVAL 1 DAY);
