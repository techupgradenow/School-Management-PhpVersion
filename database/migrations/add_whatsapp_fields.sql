-- Add WhatsApp number field to students table
ALTER TABLE students
ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) NULL
COMMENT 'Parent WhatsApp number with country code (+91XXXXXXXXXX)'
AFTER contact_number;

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_whatsapp_number ON students(whatsapp_number);

-- Create notification logs table
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    type ENUM('sms', 'email', 'whatsapp', 'app') NOT NULL,
    recipient VARCHAR(100) NOT NULL COMMENT 'Phone number, email, or device token',
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs all notifications sent to parents';

-- Add sample data (optional - for testing)
-- UPDATE students SET whatsapp_number = '+919876543210' WHERE id = 1;
