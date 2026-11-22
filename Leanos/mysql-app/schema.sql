CREATE DATABASE IF NOT EXISTS leanos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE leanos;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NULL,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(120) NOT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'Média',
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(160) NULL,
  password_hash VARCHAR(255) NOT NULL
);
CREATE TABLE IF NOT EXISTS processes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NULL,
  name VARCHAR(160) NOT NULL,
  owner VARCHAR(120) NOT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'Média',
  category VARCHAR(80) NOT NULL DEFAULT 'Operacional',
  start_date DATE NULL,
  due_date DATE NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status VARCHAR(30) NOT NULL DEFAULT 'Planejado',
  progress INT NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  process_id INT NOT NULL,
  name VARCHAR(160) NOT NULL,
  step_order INT NOT NULL DEFAULT 1,
  FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS measurements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  step_id INT NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NOT NULL,
  minutes DECIMAL(10,2) NOT NULL,
  operator_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (step_id) REFERENCES steps(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS delay_causes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  measurement_id INT NOT NULL,
  cause VARCHAR(120) NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (measurement_id) REFERENCES measurements(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS improvements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  process_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  problem TEXT NOT NULL,
  action_text TEXT NOT NULL,
  before_minutes DECIMAL(10,2) NOT NULL,
  after_minutes DECIMAL(10,2) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Proposta',
  FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NULL,
  process_id INT NULL,
  title VARCHAR(180) NOT NULL,
  owner VARCHAR(120) NOT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'Média',
  status VARCHAR(30) NOT NULL DEFAULT 'Pendente',
  due_date DATE NULL,
  progress INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES company_registrations(id) ON DELETE CASCADE,
  FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS company_registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  legal_name VARCHAR(180) NOT NULL,
  cnpj VARCHAR(30) NOT NULL,
  email VARCHAR(160) NOT NULL,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Pendente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_registrations_username (username),
  UNIQUE KEY uq_company_registrations_cnpj (cnpj)
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS company_id INT NULL;
ALTER TABLE processes ADD COLUMN IF NOT EXISTS company_id INT NULL;
ALTER TABLE processes ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'Média';
ALTER TABLE processes ADD COLUMN IF NOT EXISTS category VARCHAR(80) NOT NULL DEFAULT 'Operacional';
ALTER TABLE processes ADD COLUMN IF NOT EXISTS start_date DATE NULL;
ALTER TABLE processes ADD COLUMN IF NOT EXISTS due_date DATE NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(160) NULL;
ALTER TABLE users ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES company_registrations(id) ON DELETE CASCADE;
ALTER TABLE processes ADD CONSTRAINT fk_processes_company FOREIGN KEY (company_id) REFERENCES company_registrations(id) ON DELETE CASCADE;
