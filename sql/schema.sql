-- Schéma MySQL pour le Café Politique du COJEP
-- À importer via phpMyAdmin (ou `mysql -u ... -p nom_de_la_base < schema.sql`)

CREATE TABLE IF NOT EXISTS registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom VARCHAR(100) NOT NULL,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  telephone VARCHAR(40) DEFAULT NULL,
  nombre_personnes TINYINT UNSIGNED NOT NULL DEFAULT 1,
  message VARCHAR(500) DEFAULT NULL,
  email_envoye TINYINT(1) NOT NULL DEFAULT 0,
  rappel_envoye_at DATETIME DEFAULT NULL,
  merci_envoye_at DATETIME DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  action VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_action_date (ip_address, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
