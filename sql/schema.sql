-- Track Em schema (MySQL 8.0+ recommended, MariaDB >=10.3 ok)

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS visits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,            -- IPv4/IPv6 string, optionally masked
  user_agent VARCHAR(512) NOT NULL,
  referrer VARCHAR(512) NULL,
  path VARCHAR(512) NOT NULL,
  ts INT NOT NULL,
  meta JSON NULL
);

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(190) PRIMARY KEY,
  `value` JSON NOT NULL
);

CREATE TABLE IF NOT EXISTS plugins (
  id VARCHAR(64) PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  config JSON NULL
);

-- NEW: lightweight geolocation cache for map rendering
CREATE TABLE IF NOT EXISTS geo_cache (
  ip VARCHAR(45) PRIMARY KEY,
  lat DECIMAL(9,6) NULL,
  lon DECIMAL(9,6) NULL,
  city VARCHAR(64) NULL,
  country VARCHAR(64) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Extend visits with optional geolocation columns
-- IF NOT EXISTS is supported in MySQL 8.0.29+; harmless if older (installer runs idempotent)
ALTER TABLE visits ADD COLUMN IF NOT EXISTS lat DECIMAL(9,6) NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS lon DECIMAL(9,6) NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS city VARCHAR(64) NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS country VARCHAR(64) NULL;
