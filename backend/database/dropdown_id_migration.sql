-- ============================================================================
-- DYNAMIC DROPDOWN SYSTEM WITH DROPDOWN ID SUPPORT
-- ============================================================================
-- This migration adds Dropdown ID functionality to support configurable
-- dynamic dropdowns while preserving existing hardcoded dropdown behavior
--
-- Key Features:
-- 1. Each dropdown has a unique numeric Dropdown ID
-- 2. Values are stored and retrieved by Dropdown ID
-- 3. Backward compatible with existing category_key system
-- 4. Clean separation between hardcoded and dynamic dropdowns
-- ============================================================================

-- Step 1: Add dropdown_id column to dropdown_categories table
-- This allows each dropdown to have a unique numeric identifier
ALTER TABLE dropdown_categories
ADD COLUMN dropdown_id INT NULL UNIQUE COMMENT 'Unique numeric ID for configurable dynamic dropdowns (NULL for hardcoded dropdowns)'
AFTER id;

-- Step 2: Add index for faster lookups by dropdown_id
ALTER TABLE dropdown_categories
ADD INDEX idx_dropdown_id (dropdown_id);

-- Step 3: Assign Dropdown IDs to specific categories that should be dynamic
-- Example: ID 455 for user roles
UPDATE dropdown_categories
SET dropdown_id = 455
WHERE category_key = 'user_role' OR category_name = 'User Role';

-- Example: ID 456 for departments
UPDATE dropdown_categories
SET dropdown_id = 456
WHERE category_key = 'department' OR category_name = 'Department';

-- Example: ID 457 for designations
UPDATE dropdown_categories
SET dropdown_id = 457
WHERE category_key = 'designation' OR category_name = 'Designation';

-- Step 4: Update dropdown_values table to support filtering by dropdown_id
-- Add computed column indicator for quick identification
ALTER TABLE dropdown_values
ADD COLUMN is_dynamic BOOLEAN GENERATED ALWAYS AS (
    (SELECT dropdown_id FROM dropdown_categories dc WHERE dc.id = dropdown_values.category_id) IS NOT NULL
) STORED COMMENT 'Auto-computed: TRUE if category has dropdown_id (dynamic), FALSE otherwise (hardcoded)';

-- Step 5: Add index for dynamic vs hardcoded filtering
ALTER TABLE dropdown_values
ADD INDEX idx_is_dynamic (is_dynamic);

-- ============================================================================
-- VIEWS FOR EASY QUERYING
-- ============================================================================

-- View 1: All Dynamic Dropdowns (those with Dropdown IDs)
CREATE OR REPLACE VIEW vw_dynamic_dropdowns AS
SELECT
    dc.dropdown_id,
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    dc.institution_type_id,
    it.name AS institution_type,
    COUNT(dv.id) AS value_count,
    dc.display_order,
    dc.is_active
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
LEFT JOIN institution_types it ON dc.institution_type_id = it.id
WHERE dc.dropdown_id IS NOT NULL
GROUP BY dc.id, dc.dropdown_id, dc.category_key, dc.category_name,
         dc.institution_type_id, it.name, dc.display_order, dc.is_active
ORDER BY dc.dropdown_id;

-- View 2: All Hardcoded Dropdowns (those WITHOUT Dropdown IDs)
CREATE OR REPLACE VIEW vw_hardcoded_dropdowns AS
SELECT
    dc.id AS category_id,
    dc.category_key,
    dc.category_name,
    dc.institution_type_id,
    it.name AS institution_type,
    COUNT(dv.id) AS value_count,
    dc.display_order,
    dc.is_active
FROM dropdown_categories dc
LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
LEFT JOIN institution_types it ON dc.institution_type_id = it.id
WHERE dc.dropdown_id IS NULL
GROUP BY dc.id, dc.category_key, dc.category_name,
         dc.institution_type_id, it.name, dc.display_order, dc.is_active
ORDER BY dc.category_key;

-- View 3: Dropdown Values with Dropdown ID
CREATE OR REPLACE VIEW vw_dropdown_values_with_id AS
SELECT
    dc.dropdown_id,
    dc.category_key,
    dc.category_name,
    dv.id AS value_id,
    dv.value,
    dv.display_order,
    dv.is_active,
    dv.is_dynamic,
    dv.created_at,
    dv.updated_at
FROM dropdown_values dv
INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
WHERE dc.dropdown_id IS NOT NULL
ORDER BY dc.dropdown_id, dv.display_order, dv.value;

-- ============================================================================
-- STORED PROCEDURES FOR DROPDOWN ID OPERATIONS
-- ============================================================================

DELIMITER $$

-- Procedure 1: Get values by Dropdown ID
CREATE PROCEDURE sp_get_values_by_dropdown_id(IN p_dropdown_id INT)
BEGIN
    SELECT
        dv.id,
        dv.value,
        dv.display_order,
        dv.is_active,
        dc.category_key,
        dc.category_name
    FROM dropdown_values dv
    INNER JOIN dropdown_categories dc ON dv.category_id = dc.id
    WHERE dc.dropdown_id = p_dropdown_id
    AND dv.is_active = 1
    ORDER BY dv.display_order, dv.value;
END$$

-- Procedure 2: Add value to dropdown by Dropdown ID
CREATE PROCEDURE sp_add_value_by_dropdown_id(
    IN p_dropdown_id INT,
    IN p_value VARCHAR(255),
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500),
    OUT p_value_id INT
)
BEGIN
    DECLARE v_category_id INT;
    DECLARE v_exists INT;
    DECLARE v_max_order INT;

    -- Initialize output variables
    SET p_success = FALSE;
    SET p_message = '';
    SET p_value_id = NULL;

    -- Get category ID from dropdown_id
    SELECT id INTO v_category_id
    FROM dropdown_categories
    WHERE dropdown_id = p_dropdown_id
    LIMIT 1;

    -- Check if dropdown ID exists
    IF v_category_id IS NULL THEN
        SET p_message = CONCAT('Dropdown ID ', p_dropdown_id, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    -- Check if value already exists
    SELECT COUNT(*) INTO v_exists
    FROM dropdown_values
    WHERE category_id = v_category_id
    AND LOWER(TRIM(value)) = LOWER(TRIM(p_value));

    IF v_exists > 0 THEN
        SET p_message = 'Value already exists in this dropdown';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    -- Get max display order
    SELECT COALESCE(MAX(display_order), 0) + 1 INTO v_max_order
    FROM dropdown_values
    WHERE category_id = v_category_id;

    -- Insert new value
    INSERT INTO dropdown_values (category_id, value, display_order, is_active)
    VALUES (v_category_id, TRIM(p_value), v_max_order, 1);

    SET p_value_id = LAST_INSERT_ID();
    SET p_success = TRUE;
    SET p_message = 'Value added successfully';
END$$

-- Procedure 3: Delete value from dropdown by Dropdown ID
CREATE PROCEDURE sp_delete_value_by_dropdown_id(
    IN p_dropdown_id INT,
    IN p_value VARCHAR(255),
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_category_id INT;
    DECLARE v_value_id INT;

    SET p_success = FALSE;
    SET p_message = '';

    -- Get category ID
    SELECT id INTO v_category_id
    FROM dropdown_categories
    WHERE dropdown_id = p_dropdown_id
    LIMIT 1;

    IF v_category_id IS NULL THEN
        SET p_message = CONCAT('Dropdown ID ', p_dropdown_id, ' does not exist');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    -- Get value ID
    SELECT id INTO v_value_id
    FROM dropdown_values
    WHERE category_id = v_category_id
    AND value = p_value
    LIMIT 1;

    IF v_value_id IS NULL THEN
        SET p_message = 'Value not found';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
    END IF;

    -- Soft delete (set is_active = 0)
    UPDATE dropdown_values
    SET is_active = 0
    WHERE id = v_value_id;

    SET p_success = TRUE;
    SET p_message = 'Value deleted successfully';
END$$

-- Procedure 4: Check if dropdown is dynamic (has dropdown_id)
CREATE FUNCTION fn_is_dynamic_dropdown(p_category_key VARCHAR(100))
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE v_dropdown_id INT;

    SELECT dropdown_id INTO v_dropdown_id
    FROM dropdown_categories
    WHERE category_key = p_category_key
    LIMIT 1;

    RETURN v_dropdown_id IS NOT NULL;
END$$

DELIMITER ;

-- ============================================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================================

-- Insert sample dynamic dropdown category (if doesn't exist)
INSERT IGNORE INTO dropdown_categories (category_key, category_name, dropdown_id, institution_type_id, display_order, is_active)
VALUES
('relation', 'Relation/Role', 455, 1, 100, 1);

-- Insert sample values for Dropdown ID 455
INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active)
SELECT id, 'Teacher', 1, 1 FROM dropdown_categories WHERE dropdown_id = 455
UNION ALL
SELECT id, 'Parent', 2, 1 FROM dropdown_categories WHERE dropdown_id = 455;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Test 1: View all dynamic dropdowns
-- SELECT * FROM vw_dynamic_dropdowns;

-- Test 2: View all hardcoded dropdowns
-- SELECT * FROM vw_hardcoded_dropdowns;

-- Test 3: Get values for Dropdown ID 455
-- CALL sp_get_values_by_dropdown_id(455);

-- Test 4: Add a value to Dropdown ID 455
-- CALL sp_add_value_by_dropdown_id(455, 'Guardian', @success, @message, @value_id);
-- SELECT @success, @message, @value_id;

-- Test 5: Check if a dropdown is dynamic
-- SELECT fn_is_dynamic_dropdown('relation') AS is_dynamic;  -- Returns 1 (TRUE)
-- SELECT fn_is_dynamic_dropdown('gender') AS is_dynamic;    -- Returns 0 (FALSE)

-- ============================================================================
-- ROLLBACK SCRIPT (Use only if you need to undo changes)
-- ============================================================================
/*
-- Drop stored procedures and functions
DROP PROCEDURE IF EXISTS sp_get_values_by_dropdown_id;
DROP PROCEDURE IF EXISTS sp_add_value_by_dropdown_id;
DROP PROCEDURE IF EXISTS sp_delete_value_by_dropdown_id;
DROP FUNCTION IF EXISTS fn_is_dynamic_dropdown;

-- Drop views
DROP VIEW IF EXISTS vw_dynamic_dropdowns;
DROP VIEW IF EXISTS vw_hardcoded_dropdowns;
DROP VIEW IF EXISTS vw_dropdown_values_with_id;

-- Remove added columns
ALTER TABLE dropdown_values DROP COLUMN IF EXISTS is_dynamic;
ALTER TABLE dropdown_categories DROP INDEX IF EXISTS idx_dropdown_id;
ALTER TABLE dropdown_categories DROP COLUMN IF EXISTS dropdown_id;
*/

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
