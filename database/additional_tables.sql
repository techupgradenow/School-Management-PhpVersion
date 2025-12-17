-- Additional Tables for EduManage Pro
-- Run this to add missing tables

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    icon VARCHAR(50) DEFAULT 'fa-bell',
    target_user_id INT NULL,
    target_role VARCHAR(50) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Activity logs table (if not exists)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    module VARCHAR(100),
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Timetable table
CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(50) NOT NULL,
    section VARCHAR(10) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    period_number INT NOT NULL,
    subject_id INT,
    teacher_id VARCHAR(20),
    room VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (class, section, day_of_week, period_number),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Periods table
CREATE TABLE IF NOT EXISTS periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_number INT NOT NULL UNIQUE,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    name VARCHAR(50),
    is_break TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default periods
INSERT IGNORE INTO periods (period_number, start_time, end_time, name, is_break) VALUES
(1, '08:00:00', '08:45:00', 'Period 1', 0),
(2, '08:45:00', '09:30:00', 'Period 2', 0),
(3, '09:30:00', '10:15:00', 'Period 3', 0),
(4, '10:15:00', '10:30:00', 'Short Break', 1),
(5, '10:30:00', '11:15:00', 'Period 4', 0),
(6, '11:15:00', '12:00:00', 'Period 5', 0),
(7, '12:00:00', '12:45:00', 'Lunch Break', 1),
(8, '12:45:00', '13:30:00', 'Period 6', 0),
(9, '13:30:00', '14:15:00', 'Period 7', 0),
(10, '14:15:00', '15:00:00', 'Period 8', 0);

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value, category) VALUES
('school_name', 'EduManage Pro School', 'school'),
('school_address', '123 Education Street, City, Country', 'school'),
('school_phone', '+91 1234567890', 'school'),
('school_email', 'info@edumanagepro.com', 'school'),
('school_website', 'www.edumanagepro.com', 'school'),
('principal_name', 'Dr. John Smith', 'school'),
('established_year', '2000', 'school'),
('academic_year', '2024-2025', 'academic'),
('session_start', '2024-04-01', 'academic'),
('session_end', '2025-03-31', 'academic'),
('grading_system', 'percentage', 'academic'),
('pass_percentage', '33', 'academic'),
('working_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', 'academic'),
('currency', 'INR', 'general'),
('currency_symbol', '₹', 'general'),
('date_format', 'd-m-Y', 'general'),
('time_format', 'h:i A', 'general');

-- Insert sample timetable data
INSERT IGNORE INTO timetable (class, section, day_of_week, period_number, subject_id, teacher_id, room) VALUES
('Class 10', 'A', 'Monday', 1, 1, 'TCH001', 'Room 101'),
('Class 10', 'A', 'Monday', 2, 2, 'TCH002', 'Room 101'),
('Class 10', 'A', 'Monday', 3, 3, 'TCH003', 'Room 101'),
('Class 10', 'A', 'Monday', 5, 4, 'TCH004', 'Lab 1'),
('Class 10', 'A', 'Monday', 6, 5, 'TCH005', 'Room 101'),
('Class 10', 'A', 'Tuesday', 1, 2, 'TCH002', 'Room 101'),
('Class 10', 'A', 'Tuesday', 2, 3, 'TCH003', 'Room 101'),
('Class 10', 'A', 'Tuesday', 3, 1, 'TCH001', 'Room 101'),
('Class 10', 'A', 'Tuesday', 5, 6, 'TCH006', 'Room 101'),
('Class 10', 'A', 'Tuesday', 6, 7, 'TCH007', 'Lab 2'),
('Class 10', 'A', 'Wednesday', 1, 4, 'TCH004', 'Lab 1'),
('Class 10', 'A', 'Wednesday', 2, 1, 'TCH001', 'Room 101'),
('Class 10', 'A', 'Wednesday', 3, 5, 'TCH005', 'Room 101'),
('Class 10', 'A', 'Wednesday', 5, 2, 'TCH002', 'Room 101'),
('Class 10', 'A', 'Wednesday', 6, 3, 'TCH003', 'Room 101');
