-- POS system schema + sample data
-- Import with:  mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS pos_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stock_movements, sale_items, sales, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED NULL,
  sku           VARCHAR(40)  NOT NULL UNIQUE,
  barcode       VARCHAR(60)  NULL,
  name          VARCHAR(150) NOT NULL,
  price         DECIMAL(10,2) NOT NULL DEFAULT 0,
  cost          DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock         INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 5,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_barcode (barcode),
  INDEX idx_products_name (name),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id)
    REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sales (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no     VARCHAR(30) NOT NULL UNIQUE,
  user_id        INT UNSIGNED NOT NULL,
  subtotal       DECIMAL(10,2) NOT NULL,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax            DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL,
  amount_paid    DECIMAL(10,2) NOT NULL,
  change_due     DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','card','ewallet') NOT NULL DEFAULT 'cash',
  status         ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_created (created_at),
  CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id      INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  quantity     INT NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id      INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NULL,
  type            ENUM('purchase','sale','adjustment','void') NOT NULL,
  quantity_change INT NOT NULL,
  note            VARCHAR(255) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_moves_created (created_at),
  CONSTRAINT fk_moves_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_moves_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

/* ---------- Sample data ---------- */

-- Default logins (password for both is:  password  ) -> change after first sign in
INSERT INTO users (full_name, username, password_hash, role) VALUES
 ('Store Admin',  'admin',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
 ('Front Cashier','cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier');

INSERT INTO categories (name) VALUES ('Beverages'), ('Snacks'), ('Groceries'), ('Household'), ('Personal care');

INSERT INTO products (category_id, sku, barcode, name, price, cost, stock, reorder_level) VALUES
 (1, 'BEV-001', '4800001000011', 'Bottled Water 500ml',   15.00,  9.00, 120, 24),
 (1, 'BEV-002', '4800001000028', 'Cola 330ml Can',        38.00, 27.00,  60, 12),
 (1, 'BEV-003', '4800001000035', 'Iced Coffee 240ml',     35.00, 24.00,  40, 10),
 (2, 'SNK-001', '4800002000014', 'Potato Chips 60g',      32.00, 22.00,  48, 10),
 (2, 'SNK-002', '4800002000021', 'Chocolate Bar 40g',     28.00, 19.00,   9, 10),
 (2, 'SNK-003', '4800002000038', 'Salted Peanuts 100g',   25.00, 16.00,  35,  8),
 (3, 'GRO-001', '4800003000017', 'Jasmine Rice 1kg',      62.00, 52.00,  80, 20),
 (3, 'GRO-002', '4800003000024', 'Cooking Oil 1L',       120.00, 98.00,  22,  6),
 (3, 'GRO-003', '4800003000031', 'Instant Noodles Pack',  14.00,  9.50, 150, 30),
 (4, 'HOU-001', '4800004000010', 'Dish Soap 250ml',       45.00, 31.00,  18,  5),
 (4, 'HOU-002', '4800004000027', 'Laundry Powder 500g',   78.00, 58.00,   4,  6),
 (5, 'PER-001', '4800005000013', 'Shampoo Sachet',         8.00,  5.00, 200, 40),
 (5, 'PER-002', '4800005000020', 'Bath Soap Bar',         32.00, 21.00,  26,  8),
 (5, 'PER-003', '4800005000037', 'Toothpaste 75ml',       55.00, 38.00,   0,  6);

INSERT INTO stock_movements (product_id, user_id, type, quantity_change, note)
SELECT id, 1, 'purchase', stock, 'Opening stock' FROM products WHERE stock > 0;
