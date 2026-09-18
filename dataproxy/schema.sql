-- =========================================================
-- DataProxy Platform — Combined Data Bundle + Proxy Reseller
-- =========================================================

CREATE DATABASE IF NOT EXISTS dataproxy_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dataproxy_platform;

-- ---------------------------------------------------------
-- USERS & AUTH
-- ---------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(15) NOT NULL UNIQUE,
  email VARCHAR(150) NULL,
  password_hash VARCHAR(255) NULL,          -- NULL = guest, never registered a password
  wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_guest TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- WALLET
-- ---------------------------------------------------------
CREATE TABLE wallet_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('topup','purchase','payout','refund') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  mpesa_ref VARCHAR(50) NULL,
  status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- DATA BUNDLES
-- ---------------------------------------------------------
CREATE TABLE data_bundles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  size_mb INT UNSIGNED NOT NULL,
  validity_days INT UNSIGNED NOT NULL,
  cost_price DECIMAL(10,2) NOT NULL,
  sell_price DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE data_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,                -- NULL for pure guest checkout
  guest_phone VARCHAR(15) NULL,
  guest_order_ref VARCHAR(12) NULL UNIQUE,  -- e.g. DH-7X2K9, shown to guest for lookup
  bundle_id INT UNSIGNED NOT NULL,
  recipient_phone VARCHAR(15) NOT NULL,
  payment_method ENUM('mpesa_direct','wallet') NOT NULL,
  status ENUM('pending','paid','delivered','failed','refunded') NOT NULL DEFAULT 'pending',
  mpesa_ref VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (bundle_id) REFERENCES data_bundles(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- PROXY NETWORK
-- ---------------------------------------------------------
CREATE TABLE proxy_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  bandwidth_gb INT UNSIGNED NULL,
  duration_days INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE proxy_orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,            -- accounts required for proxy
  plan_id INT UNSIGNED NOT NULL,
  proxy_host VARCHAR(100) NULL,
  proxy_port INT UNSIGNED NULL,
  proxy_username VARCHAR(100) NULL,
  proxy_password VARCHAR(100) NULL,
  status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES proxy_plans(id)
) ENGINE=InnoDB;

-- Participant devices acting as exit nodes
CREATE TABLE devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_user_id INT UNSIGNED NOT NULL,
  device_label VARCHAR(100) NULL,
  device_info VARCHAR(255) NULL,            -- OS, app version, etc.
  exit_ip VARCHAR(45) NULL,
  status ENUM('online','offline','banned') NOT NULL DEFAULT 'offline',
  last_seen TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (participant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payouts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_owner_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  mpesa_ref VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (device_owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- COMPLAINTS / SUPPORT TICKETS
-- ---------------------------------------------------------
CREATE TABLE tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  guest_phone VARCHAR(15) NULL,
  guest_order_ref VARCHAR(12) NULL,         -- must match a real data_orders.guest_order_ref
  order_type ENUM('data','proxy','wallet','other') NOT NULL,
  order_id INT UNSIGNED NULL,               -- points to data_orders.id or proxy_orders.id
  subject VARCHAR(150) NOT NULL,
  status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ticket_replies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender ENUM('user','admin') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ticket_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- BUNDLE DELIVERY QUEUE (manual-mode fallback tracking)
-- ---------------------------------------------------------
CREATE TABLE bundle_delivery_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  reason VARCHAR(255) NULL,
  status ENUM('pending','done') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES data_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ADMIN & LOGS
-- ---------------------------------------------------------
CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('superadmin','support','finance') NOT NULL DEFAULT 'support',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sms_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(15) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(30) NULL,                    -- e.g. purchase, payout, ticket_reply
  status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- SEED: default admin (change password immediately after import)
-- ---------------------------------------------------------
-- password placeholder: 'ChangeMe123!' -> replace hash before real use
INSERT INTO admin_users (username, password_hash, role)
VALUES ('admin', '$2y$10$replaceThisWithARealBcryptHashXXXXXXXXXXXXXXXXXXXX', 'superadmin');
