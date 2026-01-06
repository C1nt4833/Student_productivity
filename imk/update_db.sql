USE imk_db;

-- Add tracking columns to tasks if they don't exist
ALTER TABLE tasks ADD COLUMN reminder_h1 TINYINT(1) DEFAULT 0;
ALTER TABLE tasks ADD COLUMN reminder_h4 TINYINT(1) DEFAULT 0;

-- Create table for Chatbot logs
CREATE TABLE IF NOT EXISTS chat_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender ENUM('bot', 'user') DEFAULT 'bot',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
