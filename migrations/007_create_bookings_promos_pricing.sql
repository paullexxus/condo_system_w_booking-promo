-- Booking + Promo + Pricing & Availability System
-- Run once.

-- Promo codes managed by admin
CREATE TABLE IF NOT EXISTS promos (
  promo_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  type ENUM('percentage','fixed') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  expires_at DATETIME NULL,
  usage_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (promo_id),
  UNIQUE KEY promos_code_unique (code),
  KEY promos_is_active_index (is_active),
  KEY promos_expires_at_index (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Centralized bookings table (renter bookings created from unit details page)
CREATE TABLE IF NOT EXISTS bookings (
  booking_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED DEFAULT NULL,
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  nights INT UNSIGNED NOT NULL DEFAULT 1,
  base_nightly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  promo_id INT UNSIGNED DEFAULT NULL,
  promo_code VARCHAR(50) DEFAULT NULL,
  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (booking_id),
  KEY bookings_user_id_index (user_id),
  KEY bookings_unit_id_index (unit_id),
  KEY bookings_branch_id_index (branch_id),
  KEY bookings_dates_index (unit_id, check_in_date, check_out_date),
  KEY bookings_status_index (status),
  KEY bookings_promo_id_index (promo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-unit pricing settings (base nightly rate)
CREATE TABLE IF NOT EXISTS unit_pricing_settings (
  unit_id INT UNSIGNED NOT NULL,
  base_nightly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blackout dates: blocked ranges that cannot be booked
CREATE TABLE IF NOT EXISTS unit_blackouts (
  blackout_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  unit_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blackout_id),
  KEY unit_blackouts_unit_id_index (unit_id),
  KEY unit_blackouts_dates_index (unit_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pricing rules: weekend adjustment and holiday/date-range rules
CREATE TABLE IF NOT EXISTS unit_pricing_rules (
  rule_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  unit_id INT UNSIGNED NOT NULL,
  rule_type ENUM('weekend','date_range') NOT NULL,
  adjustment_type ENUM('fixed','percentage') NOT NULL,
  adjustment_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (rule_id),
  KEY unit_pricing_rules_unit_id_index (unit_id),
  KEY unit_pricing_rules_type_index (rule_type),
  KEY unit_pricing_rules_active_index (is_active),
  KEY unit_pricing_rules_dates_index (unit_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add-ons purchasable by renters (stored per unit)
CREATE TABLE IF NOT EXISTS unit_addons (
  addon_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  unit_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (addon_id),
  KEY unit_addons_unit_id_index (unit_id),
  KEY unit_addons_active_index (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

