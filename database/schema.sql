CREATE DATABASE IF NOT EXISTS steam_festival
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE steam_festival;

CREATE TABLE IF NOT EXISTS districts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS circuits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_circuit_per_district (district_id, name),
  CONSTRAINT fk_circuit_district
    FOREIGN KEY (district_id) REFERENCES districts(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schools (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  emis_number VARCHAR(50) NULL,
  district_id INT UNSIGNED NULL,
  circuit_id INT UNSIGNED NULL,
  principal_name VARCHAR(160) NULL,
  contact_person VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(180) NULL,
  address TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_school_emis (emis_number),
  KEY idx_school_name (name),
  CONSTRAINT fk_school_district
    FOREIGN KEY (district_id) REFERENCES districts(id),
  CONSTRAINT fk_school_circuit
    FOREIGN KEY (circuit_id) REFERENCES circuits(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS learners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  race VARCHAR(60) NULL,
  grade VARCHAR(20) NOT NULL,
  gender ENUM('Female', 'Male', 'Other') NULL,
  id_number VARCHAR(30) NULL,
  date_of_birth DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_learner_school (school_id),
  KEY idx_learner_name (last_name, first_name),
  CONSTRAINT fk_learner_school
    FOREIGN KEY (school_id) REFERENCES schools(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;
