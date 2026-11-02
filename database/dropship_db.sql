CREATE DATABASE IF NOT EXISTS dropship-db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dropship-db;

CREATE TABLE IF NOT EXISTS suppliers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 contact_name VARCHAR(120) NULL,
 phone VARCHAR(40) NULL,
 email VARCHAR(150) NULL,
 address TEXT NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 sku VARCHAR(80) NULL UNIQUE,
 supplier_id INT UNSIGNED NULL,
 cost_price DECIMAL(14,2) NOT NULL DEFAULT 0,
 selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
 stock INT UNSIGNED NOT NULL DEFAULT 0,
 category VARCHAR(100) NULL,
 description TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_code VARCHAR(40) NOT NULL UNIQUE,
 customer_name VARCHAR(150) NOT NULL,
 customer_phone VARCHAR(40) NULL,
 shipping_address TEXT NOT NULL,
 subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
 shipping_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
 total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 status ENUM('Menunggu diproses','Diproses supplier','Dikirim','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu diproses',
 payment_status ENUM('Belum dibayar','Menunggu konfirmasi','Lunas','Dikembalikan') NOT NULL DEFAULT 'Belum dibayar',
 tracking_number VARCHAR(100) NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_orders_created (created_at),
 INDEX idx_orders_status (status),
 INDEX idx_orders_payment (payment_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NULL,
 product_name VARCHAR(180) NOT NULL,
 cost_price DECIMAL(14,2) NOT NULL DEFAULT 0,
 selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
 quantity INT UNSIGNED NOT NULL DEFAULT 1,
 line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tambah/Sesuaikan Tabel Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'staff') DEFAULT 'staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users MODIFY COLUMN password VARCHAR(128) NOT NULL;

-- Masukkan Data Default (Password di-hash MD5 atau gunakan password_hash jika disesuaikan)
-- Admin: admin / admin123
-- Karyawan: budi / karyawan123
INSERT INTO users (username, password, name, role) VALUES 
('admin', MD5('admin123'), 'Administrator', 'admin');

-- Optional sample supplier and products for initial testing:
INSERT INTO suppliers (name,contact_name,phone,email,notes)
SELECT 'Supplier Contoh','Admin Supplier','081234567890','supplier@example.com','Data contoh, silakan ganti.'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name='Supplier Contoh');

INSERT INTO products (name,sku,supplier_id,cost_price,selling_price,stock,category,description)
SELECT 'Botol Minum 600ml','BOTOL-600',s.id,25000,35000,20,'Peralatan','Produk contoh untuk uji coba.'
FROM suppliers s WHERE s.name='Supplier Contoh'
AND NOT EXISTS (SELECT 1 FROM products WHERE sku='BOTOL-600');

-- Hapus Kolom Stock dari Tabel Products
ALTER TABLE `products` DROP COLUMN IF EXISTS `stock`;

ALTER TABLE orders ADD COLUMN partial_amount DECIMAL(12,2) DEFAULT 0.00 AFTER payment_status;

-- Mengubah payment_status menjadi VARCHAR agar aman dari penolakan ENUM MySQL
ALTER TABLE orders MODIFY COLUMN payment_status VARCHAR(50) DEFAULT 'Belum dibayar';

-- Memastikan kolom partial_amount ada dan bertipe decimal
ALTER TABLE orders MODIFY COLUMN partial_amount DECIMAL(12,2) DEFAULT 0.00;

ALTER TABLE orders 
ADD COLUMN fulfillment_cost DECIMAL(12,2) DEFAULT 0.00 AFTER shipping_cost,
ADD COLUMN ad_cost DECIMAL(12,2) DEFAULT 0.00 AFTER fulfillment_cost;