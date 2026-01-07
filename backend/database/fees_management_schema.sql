-- ============================================================================
-- COMPREHENSIVE FEES MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ============================================================================
-- This migration creates a complete fees management system with:
-- - Fee categories (configurable)
-- - Student fee assignments
-- - Discount management
-- - Payment tracking with history
-- - Audit trails
-- ============================================================================

-- Step 1: Create Fee Categories Table (Admin Configurable)
CREATE TABLE IF NOT EXISTS fee_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL COMMENT 'e.g., Tuition Fee, Transport Fee',
    category_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Unique code: TUITION, TRANSPORT, BOOKS, etc.',
    description TEXT NULL,
    default_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Default fee amount',
    is_mandatory BOOLEAN DEFAULT 0 COMMENT '1 = Must be assigned to all students',
    is_active BOOLEAN DEFAULT 1,
    display_order INT DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_code (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fee categories configurable by admin';

-- Step 2: Create Student Fees Table (Links students to fee categories)
CREATE TABLE IF NOT EXISTS student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL COMMENT 'Reference to students table',
    fee_category_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL COMMENT 'e.g., 2025-2026',

    -- Fee Amount
    actual_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Original fee amount',

    -- Discount Details
    discount_type ENUM('none', 'percentage', 'flat') DEFAULT 'none',
    discount_value DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Percentage (0-100) or Flat amount',
    discount_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Calculated discount in rupees',
    discount_reason VARCHAR(255) NULL COMMENT 'Reason for discount',

    -- Final Amount
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'actual_amount - discount_amount',

    -- Payment Status
    amount_paid DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total paid so far',
    balance_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'final_amount - amount_paid',
    payment_status ENUM('not_paid', 'partially_paid', 'fully_paid') DEFAULT 'not_paid',

    -- Metadata
    due_date DATE NULL COMMENT 'Payment due date',
    waived BOOLEAN DEFAULT 0 COMMENT '1 = Fee waived completely',
    waived_reason VARCHAR(255) NULL,
    remarks TEXT NULL,

    -- Audit Trail
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (fee_category_id) REFERENCES fee_categories(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_student_fee_year (student_id, fee_category_id, academic_year),
    INDEX idx_student (student_id),
    INDEX idx_status (payment_status),
    INDEX idx_year (academic_year),
    INDEX idx_balance (balance_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fee assignments for each student';

-- Step 3: Create Payment History Table (Tracks all payments)
CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT NOT NULL COMMENT 'Reference to student_fees table',
    student_id VARCHAR(50) NOT NULL,

    -- Payment Details
    payment_date DATE NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('cash', 'upi', 'card', 'bank_transfer', 'cheque', 'dd', 'online') NOT NULL,

    -- Transaction Details
    transaction_id VARCHAR(100) NULL COMMENT 'Bank/UPI transaction ID',
    cheque_number VARCHAR(50) NULL,
    bank_name VARCHAR(100) NULL,

    -- Receipt
    receipt_number VARCHAR(50) UNIQUE NULL COMMENT 'Auto-generated receipt number',

    -- Metadata
    remarks TEXT NULL,
    collected_by INT NULL COMMENT 'User ID who collected payment',
    verified_by INT NULL COMMENT 'User ID who verified payment',
    is_verified BOOLEAN DEFAULT 0,

    -- Audit Trail
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_date (payment_date),
    INDEX idx_receipt (receipt_number),
    INDEX idx_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment history for all fee transactions';

-- Step 4: Create Discount Audit Log Table
CREATE TABLE IF NOT EXISTS fee_discount_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,

    -- Discount Details
    discount_type ENUM('percentage', 'flat') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    discount_reason VARCHAR(255) NULL,

    -- Before/After
    amount_before DECIMAL(10,2) NOT NULL COMMENT 'Amount before discount',
    amount_after DECIMAL(10,2) NOT NULL COMMENT 'Amount after discount',

    -- Authorization
    approved_by INT NULL COMMENT 'User ID who approved discount',
    approval_date TIMESTAMP NULL,

    -- Audit Trail
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for all discount changes';

-- Step 5: Insert Default Fee Categories
INSERT IGNORE INTO fee_categories (category_name, category_code, description, default_amount, is_mandatory, display_order) VALUES
('Tuition Fee', 'TUITION', 'College / Academic tuition fees', 50000.00, 1, 1),
('Transport Fee', 'TRANSPORT', 'Bus / School transport fees', 12000.00, 0, 2),
('Books & Materials', 'BOOKS', 'Books and study materials', 8000.00, 0, 3),
('Sports Fee', 'SPORTS', 'Sports and athletics fees', 3000.00, 0, 4),
('Activity Fee', 'ACTIVITY', 'Extra-curricular activities', 5000.00, 0, 5),
('Lab Fee', 'LAB', 'Laboratory and practical fees', 6000.00, 0, 6),
('Library Fee', 'LIBRARY', 'Library subscription fees', 2000.00, 0, 7),
('Exam Fee', 'EXAM', 'Examination fees', 4000.00, 0, 8),
('Development Fee', 'DEVELOPMENT', 'Infrastructure development fees', 10000.00, 0, 9),
('Miscellaneous', 'MISC', 'Other miscellaneous fees', 0.00, 0, 10);

-- Step 6: Create Views for Easy Querying

-- View: Student Fee Summary
CREATE OR REPLACE VIEW vw_student_fee_summary AS
SELECT
    sf.student_id,
    sf.academic_year,
    COUNT(sf.id) AS total_fee_categories,
    SUM(sf.actual_amount) AS total_actual_amount,
    SUM(sf.discount_amount) AS total_discount,
    SUM(sf.final_amount) AS total_payable_amount,
    SUM(sf.amount_paid) AS total_paid,
    SUM(sf.balance_amount) AS total_balance,
    CASE
        WHEN SUM(sf.balance_amount) = 0 THEN 'fully_paid'
        WHEN SUM(sf.amount_paid) = 0 THEN 'not_paid'
        ELSE 'partially_paid'
    END AS overall_status
FROM student_fees sf
WHERE sf.waived = 0
GROUP BY sf.student_id, sf.academic_year;

-- View: Fee Category Wise Collection
CREATE OR REPLACE VIEW vw_fee_category_collection AS
SELECT
    fc.id AS category_id,
    fc.category_name,
    fc.category_code,
    sf.academic_year,
    COUNT(DISTINCT sf.student_id) AS student_count,
    SUM(sf.actual_amount) AS total_actual,
    SUM(sf.discount_amount) AS total_discount,
    SUM(sf.final_amount) AS total_payable,
    SUM(sf.amount_paid) AS total_collected,
    SUM(sf.balance_amount) AS total_pending,
    ROUND((SUM(sf.amount_paid) / NULLIF(SUM(sf.final_amount), 0)) * 100, 2) AS collection_percentage
FROM fee_categories fc
LEFT JOIN student_fees sf ON fc.id = sf.fee_category_id
WHERE fc.is_active = 1 AND (sf.waived IS NULL OR sf.waived = 0)
GROUP BY fc.id, fc.category_name, fc.category_code, sf.academic_year;

-- View: Pending Fees Report
CREATE OR REPLACE VIEW vw_pending_fees_report AS
SELECT
    sf.id AS fee_id,
    sf.student_id,
    fc.category_name,
    sf.academic_year,
    sf.final_amount,
    sf.amount_paid,
    sf.balance_amount,
    sf.payment_status,
    sf.due_date,
    DATEDIFF(CURDATE(), sf.due_date) AS days_overdue
FROM student_fees sf
JOIN fee_categories fc ON sf.fee_category_id = fc.id
WHERE sf.balance_amount > 0
  AND sf.waived = 0
  AND sf.payment_status != 'fully_paid'
ORDER BY sf.due_date ASC;

-- View: Payment History Details
CREATE OR REPLACE VIEW vw_payment_history_detailed AS
SELECT
    fp.id AS payment_id,
    fp.student_id,
    fc.category_name,
    sf.academic_year,
    fp.payment_date,
    fp.amount_paid,
    fp.payment_mode,
    fp.transaction_id,
    fp.receipt_number,
    fp.collected_by,
    fp.is_verified,
    fp.created_at
FROM fee_payments fp
JOIN student_fees sf ON fp.student_fee_id = sf.id
JOIN fee_categories fc ON sf.fee_category_id = fc.id
ORDER BY fp.payment_date DESC;

-- Step 7: Create Stored Procedures

DELIMITER //

-- Procedure: Calculate and Update Fee Amounts
CREATE PROCEDURE sp_calculate_student_fee(IN p_student_fee_id INT)
BEGIN
    DECLARE v_actual_amount DECIMAL(10,2);
    DECLARE v_discount_type VARCHAR(20);
    DECLARE v_discount_value DECIMAL(10,2);
    DECLARE v_discount_amount DECIMAL(10,2);
    DECLARE v_final_amount DECIMAL(10,2);
    DECLARE v_amount_paid DECIMAL(10,2);
    DECLARE v_balance_amount DECIMAL(10,2);
    DECLARE v_payment_status VARCHAR(20);

    -- Get current values
    SELECT actual_amount, discount_type, discount_value, amount_paid
    INTO v_actual_amount, v_discount_type, v_discount_value, v_amount_paid
    FROM student_fees
    WHERE id = p_student_fee_id;

    -- Calculate discount amount
    IF v_discount_type = 'percentage' THEN
        SET v_discount_amount = (v_actual_amount * v_discount_value) / 100;
    ELSEIF v_discount_type = 'flat' THEN
        SET v_discount_amount = v_discount_value;
    ELSE
        SET v_discount_amount = 0;
    END IF;

    -- Ensure discount doesn't exceed actual amount
    IF v_discount_amount > v_actual_amount THEN
        SET v_discount_amount = v_actual_amount;
    END IF;

    -- Calculate final amount
    SET v_final_amount = v_actual_amount - v_discount_amount;

    -- Calculate balance
    SET v_balance_amount = v_final_amount - v_amount_paid;

    -- Determine payment status
    IF v_balance_amount = 0 AND v_final_amount > 0 THEN
        SET v_payment_status = 'fully_paid';
    ELSEIF v_amount_paid = 0 THEN
        SET v_payment_status = 'not_paid';
    ELSE
        SET v_payment_status = 'partially_paid';
    END IF;

    -- Update the record
    UPDATE student_fees
    SET discount_amount = v_discount_amount,
        final_amount = v_final_amount,
        balance_amount = v_balance_amount,
        payment_status = v_payment_status
    WHERE id = p_student_fee_id;
END //

-- Procedure: Add Payment and Update Balance
CREATE PROCEDURE sp_add_fee_payment(
    IN p_student_fee_id INT,
    IN p_student_id VARCHAR(50),
    IN p_amount_paid DECIMAL(10,2),
    IN p_payment_date DATE,
    IN p_payment_mode VARCHAR(50),
    IN p_transaction_id VARCHAR(100),
    IN p_collected_by INT,
    OUT p_receipt_number VARCHAR(50)
)
BEGIN
    DECLARE v_balance DECIMAL(10,2);

    -- Generate receipt number
    SET p_receipt_number = CONCAT('REC', YEAR(CURDATE()), LPAD(LAST_INSERT_ID() + 1, 6, '0'));

    -- Insert payment
    INSERT INTO fee_payments (
        student_fee_id, student_id, payment_date, amount_paid,
        payment_mode, transaction_id, receipt_number, collected_by
    ) VALUES (
        p_student_fee_id, p_student_id, p_payment_date, p_amount_paid,
        p_payment_mode, p_transaction_id, p_receipt_number, p_collected_by
    );

    -- Update student_fees amount_paid
    UPDATE student_fees
    SET amount_paid = amount_paid + p_amount_paid
    WHERE id = p_student_fee_id;

    -- Recalculate
    CALL sp_calculate_student_fee(p_student_fee_id);
END //

DELIMITER ;

-- ============================================================================
-- VERIFICATION QUERIES (commented out - run manually to verify)
-- ============================================================================

-- SELECT * FROM fee_categories ORDER BY display_order;
-- SELECT * FROM vw_student_fee_summary;
-- SELECT * FROM vw_fee_category_collection;
-- SELECT * FROM vw_pending_fees_report;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
