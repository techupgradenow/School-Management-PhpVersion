-- ============================================
-- Migration: UUID-based User System with Separate Permissions Table
-- EduManage Pro - School Management System
-- ============================================
--
-- This migration:
-- 1. Adds UUID column to users table as internal primary key
-- 2. Creates separate user_permissions table
-- 3. Migrates existing JSON permissions to new table
-- 4. Maintains backward compatibility with existing id (USR001, etc.)
--
-- ============================================

-- Step 1: Add UUID column to users table
ALTER TABLE users
ADD COLUMN uuid CHAR(36) NULL AFTER id;

-- Step 2: Generate UUIDs for existing users
UPDATE users SET uuid = UUID() WHERE uuid IS NULL;

-- Step 3: Make UUID NOT NULL and add unique index
ALTER TABLE users
MODIFY COLUMN uuid CHAR(36) NOT NULL,
ADD UNIQUE INDEX idx_users_uuid (uuid);

-- Step 4: Create user_permissions table
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_uuid CHAR(36) NOT NULL,
    module VARCHAR(100) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_export TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_module (user_uuid, module),
    CONSTRAINT fk_permissions_user FOREIGN KEY (user_uuid)
        REFERENCES users(uuid) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Create index for faster permission lookups
CREATE INDEX idx_permissions_user_uuid ON user_permissions(user_uuid);
CREATE INDEX idx_permissions_module ON user_permissions(module);

-- Step 6: Migrate existing JSON permissions to new table
-- This is handled by PHP migration script below

-- ============================================
-- Table Structure Summary
-- ============================================
--
-- users table:
--   - uuid (CHAR 36): Internal primary key, never exposed
--   - id (VARCHAR 50): Display ID like USR001, USR002 (for UI/API)
--   - name, username, email, password, role, status, etc.
--
-- user_permissions table:
--   - id: Auto-increment PK
--   - user_uuid: References users.uuid (FK with CASCADE)
--   - module: Module name (dashboard, students, etc.)
--   - can_view, can_create, can_edit, can_delete, can_export: Boolean flags
--
-- ============================================
