-- ============================================================
-- LumaStay — Esquema da base de dados
-- MySQL 8.0+ / MariaDB 10.4+
-- Importar primeiro este ficheiro e depois seed.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS lumastay
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lumastay;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS guests;
DROP TABLE IF EXISTS accommodations;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Utilizadores da área administrativa
-- ------------------------------------------------------------
CREATE TABLE admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Alojamentos
-- ------------------------------------------------------------
CREATE TABLE accommodations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150) NOT NULL,
    slug            VARCHAR(170) NOT NULL,
    description     TEXT NOT NULL,
    location        VARCHAR(120) NOT NULL,
    address         VARCHAR(255) NOT NULL,
    type            VARCHAR(40) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    capacity        TINYINT UNSIGNED NOT NULL,
    bedrooms        TINYINT UNSIGNED NOT NULL,
    bathrooms       TINYINT UNSIGNED NOT NULL,
    amenities       JSON NOT NULL,
    image_url       VARCHAR(255) NOT NULL,
    rating          DECIMAL(2,1) NOT NULL DEFAULT 4.8,
    review_count    INT UNSIGNED NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    featured        TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accommodations_slug (slug),
    KEY idx_accommodations_location (location),
    KEY idx_accommodations_active (active),
    KEY idx_accommodations_price (price_per_night),
    KEY idx_accommodations_type (type),
    CONSTRAINT chk_accommodations_price CHECK (price_per_night > 0),
    CONSTRAINT chk_accommodations_capacity CHECK (capacity BETWEEN 1 AND 30),
    CONSTRAINT chk_accommodations_bedrooms CHECK (bedrooms BETWEEN 0 AND 20),
    CONSTRAINT chk_accommodations_bathrooms CHECK (bathrooms BETWEEN 1 AND 20),
    CONSTRAINT chk_accommodations_rating CHECK (rating BETWEEN 0 AND 5),
    CONSTRAINT chk_accommodations_active CHECK (active IN (0, 1)),
    CONSTRAINT chk_accommodations_featured CHECK (featured IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Hóspedes
-- ------------------------------------------------------------
CREATE TABLE guests (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(150) NOT NULL,
    email      VARCHAR(190) NOT NULL,
    phone      VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guests_email (email),
    KEY idx_guests_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reservas
-- ------------------------------------------------------------
CREATE TABLE reservations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    accommodation_id INT UNSIGNED NOT NULL,
    guest_id         INT UNSIGNED NOT NULL,
    check_in         DATE NOT NULL,
    check_out        DATE NOT NULL,
    guests_count     TINYINT UNSIGNED NOT NULL,
    price_per_night  DECIMAL(10,2) NOT NULL,
    total_price      DECIMAL(10,2) NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'pendente',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reservations_code (reservation_code),
    KEY idx_reservations_accommodation (accommodation_id),
    KEY idx_reservations_guest (guest_id),
    KEY idx_reservations_dates (accommodation_id, check_in, check_out),
    KEY idx_reservations_status (status),
    CONSTRAINT fk_reservations_accommodation
        FOREIGN KEY (accommodation_id) REFERENCES accommodations (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_guest
        FOREIGN KEY (guest_id) REFERENCES guests (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_reservations_dates CHECK (check_out > check_in),
    CONSTRAINT chk_reservations_guests CHECK (guests_count > 0),
    CONSTRAINT chk_reservations_price_night CHECK (price_per_night > 0),
    CONSTRAINT chk_reservations_total CHECK (total_price > 0),
    CONSTRAINT chk_reservations_status
        CHECK (status IN ('pendente', 'confirmada', 'cancelada', 'concluida'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
