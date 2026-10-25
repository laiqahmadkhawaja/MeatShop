<?php
/**
 * Database Migration & Seeder
 * Creates all tables, foreign keys, indexes and default data dynamically.
 */

require_once __DIR__ . '/database.php';

function run_migrations($pdo) {
    // Disable FK checks temporarily for safe setup
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    $tables = [
        // Settings table (dynamic key-value)
        "CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) UNIQUE NOT NULL,
            `setting_value` TEXT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Branches
        "CREATE TABLE IF NOT EXISTS `branches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_name` VARCHAR(100) NOT NULL,
            `branch_code` VARCHAR(20) UNIQUE NOT NULL,
            `phone` VARCHAR(50) NULL,
            `address` TEXT NULL,
            `is_main` TINYINT(1) DEFAULT 0,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Roles
        "CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role_name` VARCHAR(50) UNIQUE NOT NULL,
            `display_name` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Permissions
        "CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `perm_key` VARCHAR(100) UNIQUE NOT NULL,
            `group_name` VARCHAR(50) NOT NULL,
            `description` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB;",

        // Role Permissions
        "CREATE TABLE IF NOT EXISTS `role_permissions` (
            `role_id` INT NOT NULL,
            `permission_id` INT NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`),
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;",

        // Users
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NULL,
            `role_id` INT NOT NULL,
            `username` VARCHAR(50) UNIQUE NOT NULL,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NULL,
            `phone` VARCHAR(50) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `last_login` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
        ) ENGINE=InnoDB;",

        // Categories (Meat Categories: Beef, Mutton, Chicken, Fish, etc. - dynamic)
        "CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_name` VARCHAR(100) UNIQUE NOT NULL,
            `description` TEXT NULL,
            `icon` VARCHAR(50) DEFAULT 'fa-drumstick-bite',
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Meat Cut Types (Boneless, Bone-in, Mince, Ribs, Breast, Wings, etc.)
        "CREATE TABLE IF NOT EXISTS `cut_types` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `cut_name` VARCHAR(100) UNIQUE NOT NULL,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Units (KG, Gram, 500g, Piece, Whole Chicken, etc.)
        "CREATE TABLE IF NOT EXISTS `units` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `unit_name` VARCHAR(50) NOT NULL,
            `unit_code` VARCHAR(20) UNIQUE NOT NULL,
            `conversion_factor` DECIMAL(12,4) NOT NULL DEFAULT 1.0000, -- Multiplier relative to 1 KG (e.g. KG=1, Gram=0.001, 500g=0.5)
            `is_weight` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Payment Methods (Cash, Bank Transfer, JazzCash, Easypaisa, Card, etc. - dynamic)
        "CREATE TABLE IF NOT EXISTS `payment_methods` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `method_name` VARCHAR(50) UNIQUE NOT NULL,
            `method_type` ENUM('cash', 'bank', 'mobile_wallet', 'other') DEFAULT 'cash',
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Bank Accounts (dynamic)
        "CREATE TABLE IF NOT EXISTS `bank_accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bank_name` VARCHAR(100) NOT NULL,
            `account_title` VARCHAR(100) NOT NULL,
            `account_number` VARCHAR(50) NOT NULL,
            `branch_code` VARCHAR(50) NULL,
            `current_balance` DECIMAL(14,2) DEFAULT 0.00,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Expense Categories (dynamic)
        "CREATE TABLE IF NOT EXISTS `expense_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_name` VARCHAR(100) UNIQUE NOT NULL,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Suppliers
        "CREATE TABLE IF NOT EXISTS `suppliers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `supplier_name` VARCHAR(150) NOT NULL,
            `contact_person` VARCHAR(100) NULL,
            `phone` VARCHAR(50) NOT NULL,
            `email` VARCHAR(100) NULL,
            `address` TEXT NULL,
            `supplier_type` ENUM('farm', 'slaughterhouse', 'wholesaler', 'feed_trader', 'other') DEFAULT 'wholesaler',
            `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
            `current_balance` DECIMAL(14,2) DEFAULT 0.00, -- Positive means we owe supplier
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Customers
        "CREATE TABLE IF NOT EXISTS `customers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_name` VARCHAR(150) NOT NULL,
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(100) NULL,
            `address` TEXT NULL,
            `customer_type` ENUM('walk_in', 'regular', 'restaurant', 'hotel', 'caterer', 'wholesale', 'corporate') DEFAULT 'walk_in',
            `credit_limit` DECIMAL(14,2) DEFAULT 0.00,
            `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
            `current_balance` DECIMAL(14,2) DEFAULT 0.00, -- Positive means customer owes us
            `notes` TEXT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;",

        // Products
        "CREATE TABLE IF NOT EXISTS `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_id` INT NOT NULL,
            `product_name` VARCHAR(150) NOT NULL,
            `product_code` VARCHAR(50) UNIQUE NOT NULL,
            `barcode` VARCHAR(50) NULL,
            `meat_type` VARCHAR(50) NULL, -- e.g. Beef, Mutton, Chicken, Fish
            `cut_type_id` INT NULL,
            `purchase_unit_id` INT NOT NULL,
            `selling_unit_id` INT NOT NULL,
            `default_purchase_rate` DECIMAL(12,2) DEFAULT 0.00,
            `retail_selling_rate` DECIMAL(12,2) DEFAULT 0.00,
            `wholesale_selling_rate` DECIMAL(12,2) DEFAULT 0.00,
            `minimum_stock_level` DECIMAL(12,3) DEFAULT 5.000,
            `storage_type` VARCHAR(50) DEFAULT 'Cold Chiller', -- Chiller, Deep Freezer, Room
            `is_raw_material` TINYINT(1) DEFAULT 0, -- 1 if unprocessed carcass/meat
            `is_processed` TINYINT(1) DEFAULT 1, -- 1 if saleable finished cut
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
            FOREIGN KEY (`cut_type_id`) REFERENCES `cut_types`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`purchase_unit_id`) REFERENCES `units`(`id`),
            FOREIGN KEY (`selling_unit_id`) REFERENCES `units`(`id`)
        ) ENGINE=InnoDB;",

        // Branch Stock (Weight/Quantity in KG base unit)
        "CREATE TABLE IF NOT EXISTS `branch_stock` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `current_stock` DECIMAL(14,3) DEFAULT 0.000, -- Weight in KG or Pcs
            `avg_cost_price` DECIMAL(12,2) DEFAULT 0.00,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `branch_product` (`branch_id`, `product_id`),
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;",

        // Stock Batches (Perishable tracking & FIFO)
        "CREATE TABLE IF NOT EXISTS `stock_batches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `batch_no` VARCHAR(50) NOT NULL,
            `supplier_id` INT NULL,
            `initial_quantity` DECIMAL(14,3) NOT NULL,
            `remaining_quantity` DECIMAL(14,3) NOT NULL,
            `cost_price` DECIMAL(12,2) NOT NULL,
            `purchase_date` DATE NOT NULL,
            `expiry_date` DATE NULL,
            `status` ENUM('active', 'exhausted', 'expired') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
            FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;",

        // Cashier Shifts
        "CREATE TABLE IF NOT EXISTS `cashier_shifts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `opening_time` DATETIME NOT NULL,
            `closing_time` DATETIME NULL,
            `opening_cash` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `cash_sales` DECIMAL(14,2) DEFAULT 0.00,
            `cash_customer_payments` DECIMAL(14,2) DEFAULT 0.00,
            `cash_supplier_payments` DECIMAL(14,2) DEFAULT 0.00,
            `cash_expenses` DECIMAL(14,2) DEFAULT 0.00,
            `cash_withdrawals` DECIMAL(14,2) DEFAULT 0.00,
            `cash_deposits` DECIMAL(14,2) DEFAULT 0.00,
            `expected_closing_cash` DECIMAL(14,2) DEFAULT 0.00,
            `actual_closing_cash` DECIMAL(14,2) DEFAULT 0.00,
            `cash_difference` DECIMAL(14,2) DEFAULT 0.00,
            `status` ENUM('open', 'closed') DEFAULT 'open',
            `closing_notes` TEXT NULL,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Live Animals (For Slaughter & Processing module)
        "CREATE TABLE IF NOT EXISTS `live_animals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tag_id` VARCHAR(50) UNIQUE NOT NULL, -- Ear tag / RFID / ID
            `animal_type` VARCHAR(50) NOT NULL, -- Cow, Bull, Goat, Sheep, Camel
            `supplier_id` INT NOT NULL,
            `purchase_date` DATE NOT NULL,
            `live_weight` DECIMAL(10,3) NOT NULL, -- KG
            `purchase_rate_per_kg` DECIMAL(12,2) NOT NULL,
            `animal_cost` DECIMAL(14,2) NOT NULL,
            `transport_charges` DECIMAL(12,2) DEFAULT 0.00,
            `slaughter_charges` DECIMAL(12,2) DEFAULT 0.00,
            `other_expenses` DECIMAL(12,2) DEFAULT 0.00,
            `total_animal_cost` DECIMAL(14,2) NOT NULL,
            `status` ENUM('purchased', 'slaughtered', 'processed', 'sold_live') DEFAULT 'purchased',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
        ) ENGINE=InnoDB;",

        // Processing Batches (Animal or Carcass Cut Processing)
        "CREATE TABLE IF NOT EXISTS `processing_batches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `batch_no` VARCHAR(50) UNIQUE NOT NULL,
            `branch_id` INT NOT NULL,
            `live_animal_id` INT NULL,
            `raw_product_id` INT NULL,
            `input_weight` DECIMAL(12,3) NOT NULL, -- Total input KG
            `processing_date` DATE NOT NULL,
            `labor_charges` DECIMAL(12,2) DEFAULT 0.00,
            `other_costs` DECIMAL(12,2) DEFAULT 0.00,
            `total_processing_cost` DECIMAL(14,2) NOT NULL,
            `total_output_weight` DECIMAL(12,3) DEFAULT 0.000,
            `saleable_meat_weight` DECIMAL(12,3) DEFAULT 0.000,
            `bone_weight` DECIMAL(12,3) DEFAULT 0.000,
            `organ_byproduct_weight` DECIMAL(12,3) DEFAULT 0.000,
            `waste_weight` DECIMAL(12,3) DEFAULT 0.000,
            `yield_percentage` DECIMAL(6,2) DEFAULT 0.00, -- Saleable / Input * 100
            `responsible_employee` VARCHAR(100) NULL,
            `notes` TEXT NULL,
            `status` ENUM('draft', 'completed') DEFAULT 'completed',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`live_animal_id`) REFERENCES `live_animals`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`raw_product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;",

        // Processing Outputs (Products generated from the batch)
        "CREATE TABLE IF NOT EXISTS `processing_outputs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `processing_batch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `output_type` ENUM('saleable_meat', 'bone', 'organ', 'fat_trimming', 'waste') DEFAULT 'saleable_meat',
            `output_weight` DECIMAL(12,3) NOT NULL,
            `allocated_cost_per_kg` DECIMAL(12,2) NOT NULL,
            `total_allocated_cost` DECIMAL(14,2) NOT NULL,
            FOREIGN KEY (`processing_batch_id`) REFERENCES `processing_batches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB;",

        // Purchases (Bulk & Slaughterhouse)
        "CREATE TABLE IF NOT EXISTS `purchases` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `supplier_id` INT NOT NULL,
            `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
            `purchase_date` DATE NOT NULL,
            `gross_weight` DECIMAL(12,3) DEFAULT 0.000,
            `tare_weight` DECIMAL(12,3) DEFAULT 0.000,
            `net_weight` DECIMAL(12,3) DEFAULT 0.000,
            `subtotal` DECIMAL(14,2) NOT NULL,
            `transport_charges` DECIMAL(12,2) DEFAULT 0.00,
            `loading_charges` DECIMAL(12,2) DEFAULT 0.00,
            `other_expenses` DECIMAL(12,2) DEFAULT 0.00,
            `discount` DECIMAL(12,2) DEFAULT 0.00,
            `grand_total` DECIMAL(14,2) NOT NULL,
            `paid_amount` DECIMAL(14,2) DEFAULT 0.00,
            `balance_amount` DECIMAL(14,2) DEFAULT 0.00,
            `payment_status` ENUM('paid', 'partial', 'unpaid') DEFAULT 'unpaid',
            `payment_method_id` INT NULL,
            `notes` TEXT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
            FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Purchase Items
        "CREATE TABLE IF NOT EXISTS `purchase_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `purchase_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `gross_weight` DECIMAL(12,3) DEFAULT 0.000,
            `tare_weight` DECIMAL(12,3) DEFAULT 0.000,
            `net_weight` DECIMAL(12,3) NOT NULL,
            `rate_per_kg` DECIMAL(12,2) NOT NULL,
            `total_amount` DECIMAL(14,2) NOT NULL,
            `batch_no` VARCHAR(50) NULL,
            `expiry_date` DATE NULL,
            FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB;",

        // Sales (POS & Billing)
        "CREATE TABLE IF NOT EXISTS `sales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `cashier_id` INT NOT NULL,
            `shift_id` INT NULL,
            `customer_id` INT NOT NULL,
            `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
            `sale_date` DATETIME NOT NULL,
            `total_weight` DECIMAL(12,3) DEFAULT 0.000,
            `subtotal` DECIMAL(14,2) NOT NULL,
            `discount_type` ENUM('fixed', 'percent') DEFAULT 'fixed',
            `discount_value` DECIMAL(12,2) DEFAULT 0.00,
            `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
            `tax_percent` DECIMAL(5,2) DEFAULT 0.00,
            `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
            `grand_total` DECIMAL(14,2) NOT NULL,
            `cogs_total` DECIMAL(14,2) DEFAULT 0.00, -- Cost of Goods Sold for profit tracking
            `paid_amount` DECIMAL(14,2) DEFAULT 0.00,
            `balance_amount` DECIMAL(14,2) DEFAULT 0.00,
            `payment_status` ENUM('paid', 'partial', 'credit') DEFAULT 'paid',
            `payment_method_id` INT NULL,
            `status` ENUM('completed', 'hold', 'returned', 'cancelled') DEFAULT 'completed',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`),
            FOREIGN KEY (`shift_id`) REFERENCES `cashier_shifts`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
            FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;",

        // Sale Items
        "CREATE TABLE IF NOT EXISTS `sale_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sale_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `unit_id` INT NOT NULL,
            `weight_qty` DECIMAL(12,3) NOT NULL, -- e.g. 2.5 KG
            `rate_per_unit` DECIMAL(12,2) NOT NULL, -- e.g. Rs 1200 / KG
            `tare_weight` DECIMAL(10,3) DEFAULT 0.000,
            `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
            `line_total` DECIMAL(14,2) NOT NULL,
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00, -- Cost at time of sale
            `cogs_amount` DECIMAL(14,2) DEFAULT 0.00, -- Total cost for line
            FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
            FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`)
        ) ENGINE=InnoDB;",

        // Sale Payments (Split Payment Support: Cash + Bank + JazzCash)
        "CREATE TABLE IF NOT EXISTS `sale_payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sale_id` INT NOT NULL,
            `payment_method_id` INT NOT NULL,
            `bank_account_id` INT NULL,
            `amount` DECIMAL(14,2) NOT NULL,
            `transaction_ref` VARCHAR(100) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`),
            FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;",

        // Customer Ledger Transactions
        "CREATE TABLE IF NOT EXISTS `customer_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_id` INT NOT NULL,
            `branch_id` INT NOT NULL,
            `transaction_type` ENUM('opening_balance', 'sale_invoice', 'payment_received', 'sale_return', 'debit_adj', 'credit_adj') NOT NULL,
            `reference_id` INT NULL, -- e.g. sale_id or payment_id
            `reference_no` VARCHAR(100) NULL,
            `transaction_date` DATETIME NOT NULL,
            `debit` DECIMAL(14,2) DEFAULT 0.00,  -- Increases receivable (Sale)
            `credit` DECIMAL(14,2) DEFAULT 0.00, -- Decreases receivable (Payment)
            `balance_after` DECIMAL(14,2) NOT NULL,
            `payment_method_id` INT NULL,
            `notes` TEXT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Supplier Ledger Transactions
        "CREATE TABLE IF NOT EXISTS `supplier_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `supplier_id` INT NOT NULL,
            `branch_id` INT NOT NULL,
            `transaction_type` ENUM('opening_balance', 'purchase_invoice', 'payment_paid', 'purchase_return', 'debit_adj', 'credit_adj') NOT NULL,
            `reference_id` INT NULL,
            `reference_no` VARCHAR(100) NULL,
            `transaction_date` DATETIME NOT NULL,
            `debit` DECIMAL(14,2) DEFAULT 0.00,  -- Decreases payable (Payment paid)
            `credit` DECIMAL(14,2) DEFAULT 0.00, -- Increases payable (Purchase)
            `balance_after` DECIMAL(14,2) NOT NULL,
            `payment_method_id` INT NULL,
            `notes` TEXT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Wastage Records (Normal Cutting Loss vs Spoilage)
        "CREATE TABLE IF NOT EXISTS `wastage_records` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `wastage_date` DATE NOT NULL,
            `weight` DECIMAL(12,3) NOT NULL,
            `wastage_type` ENUM('cutting_loss', 'trimming_loss', 'spoilage', 'damaged', 'storage_loss', 'handling_loss', 'other') NOT NULL,
            `is_normal_loss` TINYINT(1) DEFAULT 0, -- 1 = Normal operational cutting/trimming, 0 = Spoilage/Damage
            `cost_per_kg` DECIMAL(12,2) NOT NULL,
            `total_loss_cost` DECIMAL(14,2) NOT NULL,
            `reason` TEXT NULL,
            `user_id` INT NOT NULL,
            `approved_by` INT NULL,
            `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
            FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;",

        // Expenses
        "CREATE TABLE IF NOT EXISTS `expenses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `expense_date` DATE NOT NULL,
            `amount` DECIMAL(14,2) NOT NULL,
            `payment_method_id` INT NOT NULL,
            `bank_account_id` INT NULL,
            `reference_no` VARCHAR(100) NULL,
            `description` TEXT NULL,
            `receipt_attachment` VARCHAR(255) NULL,
            `user_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`),
            FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`),
            FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Stock Movements History (Audit Trail)
        "CREATE TABLE IF NOT EXISTS `stock_movements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `movement_type` ENUM('purchase', 'sale', 'processing_in', 'processing_out', 'wastage', 'adjustment_add', 'adjustment_sub', 'transfer_in', 'transfer_out', 'return_in', 'return_out') NOT NULL,
            `quantity` DECIMAL(14,3) NOT NULL, -- Positive or negative change
            `stock_before` DECIMAL(14,3) NOT NULL,
            `stock_after` DECIMAL(14,3) NOT NULL,
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
            `reference_id` INT NULL,
            `reference_table` VARCHAR(50) NULL,
            `notes` TEXT NULL,
            `user_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Stock Adjustments
        "CREATE TABLE IF NOT EXISTS `stock_adjustments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `adjustment_type` ENUM('add', 'subtract') NOT NULL,
            `quantity` DECIMAL(12,3) NOT NULL,
            `unit_cost` DECIMAL(12,2) NOT NULL,
            `reason` TEXT NOT NULL,
            `approved_by` INT NULL,
            `user_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
            FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Multi-Branch Stock Transfers
        "CREATE TABLE IF NOT EXISTS `stock_transfers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transfer_no` VARCHAR(50) UNIQUE NOT NULL,
            `from_branch_id` INT NOT NULL,
            `to_branch_id` INT NOT NULL,
            `transfer_date` DATE NOT NULL,
            `received_date` DATE NULL,
            `status` ENUM('pending', 'dispatched', 'received', 'cancelled') DEFAULT 'pending',
            `total_items` INT DEFAULT 0,
            `total_weight` DECIMAL(12,3) DEFAULT 0.000,
            `notes` TEXT NULL,
            `dispatched_by` INT NOT NULL,
            `received_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`from_branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`to_branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`dispatched_by`) REFERENCES `users`(`id`),
            FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;",

        // Stock Transfer Items
        "CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transfer_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity` DECIMAL(12,3) NOT NULL,
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
            FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB;",

        // Advance Orders & Restaurant/Hotel Supply Bookings
        "CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_no` VARCHAR(50) UNIQUE NOT NULL,
            `branch_id` INT NOT NULL,
            `customer_id` INT NOT NULL,
            `order_type` ENUM('booking', 'wholesale_supply', 'home_delivery') DEFAULT 'booking',
            `order_date` DATE NOT NULL,
            `delivery_datetime` DATETIME NOT NULL,
            `delivery_address` TEXT NULL,
            `rider_name` VARCHAR(100) NULL,
            `delivery_charges` DECIMAL(12,2) DEFAULT 0.00,
            `subtotal` DECIMAL(14,2) NOT NULL,
            `grand_total` DECIMAL(14,2) NOT NULL,
            `advance_paid` DECIMAL(14,2) DEFAULT 0.00,
            `balance_amount` DECIMAL(14,2) DEFAULT 0.00,
            `status` ENUM('pending', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
            `notes` TEXT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Order Items
        "CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `weight_qty` DECIMAL(12,3) NOT NULL,
            `unit_rate` DECIMAL(12,2) NOT NULL,
            `line_total` DECIMAL(14,2) NOT NULL,
            `special_instructions` VARCHAR(255) NULL, -- e.g. Fine mince, small curry pieces, bone-in
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB;",

        // Sales Returns
        "CREATE TABLE IF NOT EXISTS `sales_returns` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `return_no` VARCHAR(50) UNIQUE NOT NULL,
            `sale_id` INT NOT NULL,
            `customer_id` INT NOT NULL,
            `branch_id` INT NOT NULL,
            `return_date` DATETIME NOT NULL,
            `total_refund` DECIMAL(14,2) NOT NULL,
            `notes` TEXT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`),
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
            FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB;",

        // Sales Return Items
        "CREATE TABLE IF NOT EXISTS `sales_return_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `return_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `weight_qty` DECIMAL(12,3) NOT NULL,
            `unit_rate` DECIMAL(12,2) NOT NULL,
            `refund_amount` DECIMAL(14,2) NOT NULL,
            `return_to_stock` TINYINT(1) DEFAULT 1,
            FOREIGN KEY (`return_id`) REFERENCES `sales_returns`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB;",

        // Audit Logs
        "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `action` VARCHAR(50) NOT NULL,
            `module` VARCHAR(50) NOT NULL,
            `record_id` INT NULL,
            `details` TEXT NULL,
            `ip_address` VARCHAR(50) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    seed_default_data($pdo);
}

function seed_default_data($pdo) {
    // 1. Settings
    $settings = [
        'shop_name' => 'AL-BARAKAH MEAT & BUTCHER SHOP',
        'tagline' => 'Fresh Halal Beef, Mutton, Chicken & Meat Cuts',
        'phone' => '+92 300 1234567',
        'email' => 'info@albarakahmeat.com',
        'address' => 'Shop #12, Meat Market, Commercial Avenue, Lahore, Pakistan',
        'currency_symbol' => 'Rs.',
        'currency_code' => 'PKR',
        'decimal_precision' => '2',
        'weight_precision' => '3',
        'tax_enabled' => '0',
        'default_tax_rate' => '0',
        'default_tare_weight' => '0.050', // 50 grams tray tare default
        'receipt_footer_text' => 'Thank you for choosing Al-Barakah Meat Shop! We guarantee 100% fresh, hygienic & halal meat.',
        'barcode_type' => 'CODE128',
        'low_stock_threshold' => '10.000',
        'scale_baud_rate' => '9600'
    ];

    $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // 2. Default Branch
    $pdo->exec("INSERT IGNORE INTO `branches` (`id`, `branch_name`, `branch_code`, `phone`, `address`, `is_main`) 
        VALUES (1, 'Main Branch - Lahore Cantt', 'BR-01', '+92 300 1234567', 'Commercial Avenue, Lahore', 1)");

    // 3. Roles
    $roles = [
        [1, 'admin', 'Administrator', 'Full system access and configuration'],
        [2, 'manager', 'Shop Manager', 'Sales, inventory, suppliers, customers, and operations'],
        [3, 'cashier', 'Cashier', 'POS sales, customer payments, shift management'],
        [4, 'accountant', 'Accountant', 'Ledgers, financial reports, expenses, audits'],
        [5, 'inventory_manager', 'Inventory Manager', 'Stock, processing, wastage, adjustments']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `roles` (`id`, `role_name`, `display_name`, `description`) VALUES (?, ?, ?, ?)");
    foreach ($roles as $r) {
        $stmt->execute($r);
    }

    // 4. Permissions
    $permissions = [
        ['pos_access', 'POS', 'Access to POS terminal and billing'],
        ['pos_discount', 'POS', 'Apply custom discounts on POS'],
        ['pos_price_override', 'POS', 'Override product unit rate on POS'],
        ['pos_return', 'POS', 'Process sales returns & refunds'],
        ['shift_manage', 'Shift', 'Open and close cashier shifts'],
        ['products_manage', 'Catalog', 'Add, edit, delete products & categories'],
        ['purchases_manage', 'Purchasing', 'Manage meat purchases and animal intake'],
        ['processing_manage', 'Processing', 'Manage slaughter, meat cuts and yield breakdown'],
        ['inventory_manage', 'Inventory', 'View stock, manage transfers & adjustments'],
        ['wastage_manage', 'Wastage', 'Record and approve meat wastage & spoilage'],
        ['customers_manage', 'Customers', 'Manage customers and khata ledgers'],
        ['suppliers_manage', 'Suppliers', 'Manage suppliers and payment ledgers'],
        ['expenses_manage', 'Expenses', 'Record and manage operational expenses'],
        ['reports_view', 'Reports', 'Access sales, purchases, inventory and P&L reports'],
        ['settings_manage', 'Settings', 'Manage master system settings and branches'],
        ['users_manage', 'Security', 'Manage user accounts and roles']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `permissions` (`perm_key`, `group_name`, `description`) VALUES (?, ?, ?)");
    foreach ($permissions as $p) {
        $stmt->execute($p);
    }

    // Grant all permissions to Admin
    $all_perms = $pdo->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1, ?)");
    foreach ($all_perms as $pid) {
        $stmt->execute([$pid]);
    }

    // 5. Default Users (Admin, Cashier)
    // admin / admin123
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $cashier_pass = password_hash('cashier123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT IGNORE INTO `users` (`id`, `branch_id`, `role_id`, `username`, `full_name`, `email`, `phone`, `password_hash`, `status`) 
        VALUES (1, 1, 1, 'admin', 'System Administrator', 'admin@meatshop.com', '03001234567', ?, 'active')")
        ->execute([$admin_pass]);
    $pdo->prepare("INSERT IGNORE INTO `users` (`id`, `branch_id`, `role_id`, `username`, `full_name`, `email`, `phone`, `password_hash`, `status`) 
        VALUES (2, 1, 3, 'cashier', 'Shift Cashier', 'cashier@meatshop.com', '03007654321', ?, 'active')")
        ->execute([$cashier_pass]);

    // 6. Units (All dynamic, pre-seeded for convenience)
    $units = [
        ['Kilogram', 'KG', 1.0000, 1],
        ['Gram', 'G', 0.0010, 1],
        ['Half Kilogram', '500G', 0.5000, 1],
        ['Quarter Kilogram', '250G', 0.2500, 1],
        ['Piece', 'PC', 1.0000, 0],
        ['Whole Chicken', 'WHOLE', 1.0000, 0],
        ['Dozen', 'DOZ', 12.0000, 0]
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `units` (`unit_name`, `unit_code`, `conversion_factor`, `is_weight`) VALUES (?, ?, ?, ?)");
    foreach ($units as $u) {
        $stmt->execute($u);
    }

    // 7. Payment Methods (Dynamic)
    $pay_methods = [
        ['Cash', 'cash', 1],
        ['Bank Transfer', 'bank', 1],
        ['JazzCash', 'mobile_wallet', 1],
        ['Easypaisa', 'mobile_wallet', 1],
        ['Debit / Credit Card', 'bank', 1]
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `payment_methods` (`method_name`, `method_type`, `is_active`) VALUES (?, ?, ?)");
    foreach ($pay_methods as $pm) {
        $stmt->execute($pm);
    }

    // 8. Bank Accounts (Dynamic)
    $banks = [
        ['Meezan Bank Ltd', 'Al-Barakah Meat Main Account', '0101010203040506', 'Main Branch', 150000.00],
        ['Habib Bank Limited (HBL)', 'Shop Operations Account', '12345678901234', 'Cantt Branch', 50000.00],
        ['JazzCash Merchant', 'Al-Barakah Shop Wallet', '03001234567', 'Lahore', 25000.00]
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `bank_accounts` (`bank_name`, `account_title`, `account_number`, `branch_code`, `current_balance`) VALUES (?, ?, ?, ?, ?)");
    foreach ($banks as $b) {
        $stmt->execute($b);
    }

    // 9. Categories (Dynamic)
    $cats = [
        ['Beef', 'Fresh prime beef cuts and mince', 'fa-cow'],
        ['Mutton', 'Pure goat and sheep fresh mutton', 'fa-paw'],
        ['Chicken', 'Farm fresh broiler, desi chicken and cuts', 'fa-egg'],
        ['Fish & Seafood', 'Fresh river fish and marine cuts', 'fa-fish'],
        ['Organ Meat', 'Liver, kidney, brain, trotters, paya', 'fa-heart-pulse'],
        ['Minced Meat (Keema)', 'Fresh beef, mutton and chicken mince', 'fa-burger'],
        ['Frozen & Marinated', 'Marinated tikka, kabab, frozen cuts', 'fa-snowflake']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `categories` (`category_name`, `description`, `icon`) VALUES (?, ?, ?)");
    foreach ($cats as $c) {
        $stmt->execute($c);
    }

    // 10. Cut Types (Dynamic)
    $cuts = [
        ['Mix with Bone', 'Standard retail mix pieces with bone'],
        ['Boneless', 'Clean boneless meat chunks'],
        ['Hand Mince (Keema)', 'Coarse freshly hand-cut mince'],
        ['Machine Mince (Keema)', 'Finely ground meat mince'],
        ['Ribs (Chaanp)', 'Prime ribs and chops cut'],
        ['Breast Boneless', 'Skinless breast fillet'],
        ['Chicken Wings', 'Cut chicken wings'],
        ['Chicken Leg / Drumstick', 'Whole leg and drumsticks'],
        ['Liver (Kaleji)', 'Fresh liver portion'],
        ['Paya / Trotters', 'Cleaned goat or cow trotters']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `cut_types` (`cut_name`, `description`) VALUES (?, ?)");
    foreach ($cuts as $ct) {
        $stmt->execute($ct);
    }

    // 11. Expense Categories (Dynamic)
    $exp_cats = [
        ['Shop Rent', 'Monthly shop rental payment'],
        ['Electricity Bill', 'Commercial chiller and power bill'],
        ['Water & Utilities', 'Sanitation and municipal water charges'],
        ['Staff Salaries', 'Butchers, cutters and cashier wages'],
        ['Ice & Cooling Gas', 'Ice blocks, dry ice, refrigerator maintenance'],
        ['Packaging & Bags', 'Polythene bags, butcher paper, plastic trays'],
        ['Transportation & Fuel', 'Livestock hauling and delivery transport'],
        ['Slaughterhouse Fee', 'Slaughter charges and municipal tax'],
        ['Cleaning & Hygiene Supplies', 'Disinfectants, sanitizers, aprons'],
        ['Miscellaneous Expense', 'Tea, snacks, general maintenance']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `expense_categories` (`category_name`, `description`) VALUES (?, ?)");
    foreach ($exp_cats as $ec) {
        $stmt->execute($ec);
    }

    // 12. Default Suppliers (Dynamic)
    $suppliers = [
        ['Punjab Livestock Farm', 'Haji Munir', '+92 300 8877665', 'lahore_farms@gmail.com', 'Raiwind Road, Lahore', 'farm', 0.00, 0.00],
        ['City Model Slaughterhouse', 'Malik Riaz', '+92 321 4455667', 'slaughter_city@gmail.com', 'Bakar Mandi, Shahpur Kanjra', 'slaughterhouse', 0.00, 0.00],
        ['Sabir Poultry Farm & Feeds', 'Tariq Mehmood', '+92 333 9988771', 'sabir_poultry@gmail.com', 'Sheikhupura Road', 'wholesaler', 0.00, 0.00]
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `suppliers` (`supplier_name`, `contact_person`, `phone`, `email`, `address`, `supplier_type`, `opening_balance`, `current_balance`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($suppliers as $s) {
        $stmt->execute($s);
    }

    // 13. Default Customers (Walk-in, Restaurant, Regular)
    $customers = [
        ['Walk-in Customer (Counter Sale)', '03000000000', '', 'Counter Cash Customer', 'walk_in', 0.00, 0.00, 0.00, 'Standard retail walk-in'],
        ['Haji Aslam (Regular)', '03014445556', 'aslam@gmail.com', 'House 45, Street 10, Lahore Cantt', 'regular', 10000.00, 0.00, 0.00, 'VIP retail customer'],
        ['Royal BBQ & Shinwari Restaurant', '03225556667', 'royalbbq@gmail.com', 'Main Boulevard, Gulberg, Lahore', 'restaurant', 100000.00, 0.00, 15000.00, 'Daily fresh mutton & beef supply'],
        ['Pearl Continental Catering', '03451122334', 'pc_cater@gmail.com', 'Mall Road, Lahore', 'hotel', 250000.00, 0.00, 45000.00, 'Weekly bulk boneless chicken & beef supply']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `customers` (`customer_name`, `phone`, `email`, `address`, `customer_type`, `credit_limit`, `opening_balance`, `current_balance`, `notes`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($customers as $c) {
        $stmt->execute($c);
    }

    // 14. Products & Initial Stock (Weight-based, cuts, rates)
    // Category 1=Beef, 2=Mutton, 3=Chicken, 4=Fish, 5=Organ, 6=Mince
    // Unit 1=KG, 2=G, 3=500G, 5=PC, 6=WHOLE
    $products = [
        // Name, code, barcode, meat_type, cat_id, cut_id, p_unit, s_unit, buy_rate, retail_rate, wholesale_rate, min_stock, is_raw, is_proc, initial_kg
        ['Beef with Bone', 'BF-001', '8901001', 'Beef', 1, 1, 1, 1, 950.00, 1200.00, 1100.00, 15.000, 0, 1, 65.500],
        ['Beef Boneless (Pasanday)', 'BF-002', '8901002', 'Beef', 1, 2, 1, 1, 1150.00, 1450.00, 1350.00, 10.000, 0, 1, 40.000],
        ['Beef Keema (Mince)', 'BF-003', '8901003', 'Beef', 6, 4, 1, 1, 1000.00, 1300.00, 1200.00, 10.000, 0, 1, 30.000],
        ['Beef Liver (Kaleji)', 'BF-004', '8901004', 'Beef', 5, 9, 1, 1, 750.00, 950.00, 850.00, 5.000, 0, 1, 15.000],
        ['Mutton Mix with Bone', 'MT-001', '8902001', 'Mutton', 2, 1, 1, 1, 2000.00, 2400.00, 2250.00, 10.000, 0, 1, 45.250],
        ['Mutton Boneless', 'MT-002', '8902002', 'Mutton', 2, 2, 1, 1, 2500.00, 3000.00, 2800.00, 5.000, 0, 1, 20.000],
        ['Mutton Chaanp (Ribs)', 'MT-003', '8902003', 'Mutton', 2, 5, 1, 1, 2100.00, 2500.00, 2350.00, 8.000, 0, 1, 18.500],
        ['Chicken Broiler (Fresh Cut)', 'CK-001', '8903001', 'Chicken', 3, 1, 1, 1, 520.00, 650.00, 600.00, 20.000, 0, 1, 95.000],
        ['Chicken Boneless Breast', 'CK-002', '8903002', 'Chicken', 3, 6, 1, 1, 750.00, 950.00, 880.00, 15.000, 0, 1, 35.000],
        ['Chicken Wings', 'CK-003', '8903003', 'Chicken', 3, 7, 1, 1, 400.00, 550.00, 500.00, 10.000, 0, 1, 25.000],
        ['Whole Live Broiler Chicken', 'CK-004', '8903004', 'Chicken', 3, NULL, 1, 6, 360.00, 440.00, 410.00, 30.000, 1, 0, 120.000],
        ['Fresh Rohu Fish', 'FS-001', '8904001', 'Fish', 4, 1, 1, 1, 600.00, 850.00, 780.00, 15.000, 0, 1, 28.000]
    ];

    $stmt_prod = $pdo->prepare("INSERT IGNORE INTO `products` 
        (`product_name`, `product_code`, `barcode`, `meat_type`, `category_id`, `cut_type_id`, `purchase_unit_id`, `selling_unit_id`, `default_purchase_rate`, `retail_selling_rate`, `wholesale_selling_rate`, `minimum_stock_level`, `is_raw_material`, `is_processed`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt_stock = $pdo->prepare("INSERT INTO `branch_stock` (`branch_id`, `product_id`, `current_stock`, `avg_cost_price`) 
        VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE `current_stock`=`current_stock`");

    foreach ($products as $p) {
        $stmt_prod->execute([
            $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11], $p[12], $p[13]
        ]);
        $prod_id = $pdo->lastInsertId();
        if (!$prod_id) {
            $q = $pdo->prepare("SELECT id FROM products WHERE product_code = ?");
            $q->execute([$p[1]]);
            $prod_id = $q->fetchColumn();
        }
        if ($prod_id) {
            $stmt_stock->execute([$prod_id, $p[14], $p[8]]);
        }
    }
}

// Auto-run if executed or included
run_migrations($pdo);
