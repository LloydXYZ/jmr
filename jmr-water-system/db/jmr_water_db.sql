DROP DATABASE IF EXISTS jmr_water_db;
CREATE DATABASE jmr_water_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE jmr_water_db;

-- 1. USERS (admin, staff, customer logins)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. CUSTOMERS
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. SETTINGS (admin-changeable price)
CREATE TABLE settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES ('price_per_gallon', '20.00');

-- 4. RESERVATIONS
CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reservation_type ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 20.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reservation_date DATE NOT NULL,
    time_slot ENUM('morning', 'noon', 'afternoon') NOT NULL,
    delivery_address TEXT NULL,
    notes TEXT NULL,
    status ENUM('pending', 'confirmed', 'scheduled', 'out_for_delivery', 'completed', 'cancelled')
        NOT NULL DEFAULT 'pending',
    cancelled_at DATETIME NULL,
    cancelled_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (cancelled_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 5. EMPLOYEES
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    job_position VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    monthly_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    date_hired DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 6. DELIVERIES
CREATE TABLE deliveries (
    delivery_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    employee_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    time_slot ENUM('morning', 'noon', 'afternoon') NOT NULL,
    status ENUM('scheduled', 'out_for_delivery', 'delivered', 'cancelled')
        NOT NULL DEFAULT 'scheduled',
    delivered_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 7. PAYMENTS (Cash on Delivery only)
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cod') NOT NULL DEFAULT 'cod',
    payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
    date_paid DATETIME NULL,
    received_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
    FOREIGN KEY (received_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 8. INVENTORY (one row for the 20-liter gallon)
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(50) NOT NULL,
    stock_on_hand INT NOT NULL DEFAULT 0,
    low_stock_limit INT NOT NULL DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO inventory (item_name, stock_on_hand, low_stock_limit)
VALUES ('20-Liter Water Gallon', 0, 10);

-- 9. INVENTORY TRANSACTIONS (history)
CREATE TABLE inventory_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    transaction_type ENUM('add', 'deduct', 'reserve', 'release', 'deliver') NOT NULL,
    quantity INT NOT NULL,
    balance_after INT NOT NULL DEFAULT 0,
    reservation_id INT NULL,
    remarks VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 10. PAYROLL
CREATE TABLE payroll (
    payroll_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payroll_date DATE NOT NULL,
    payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'paid',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 11. NOTIFICATIONS
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    notification_type VARCHAR(30) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. ACTIVITY LOGS
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;