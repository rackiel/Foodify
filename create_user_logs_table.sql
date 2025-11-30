-- Create user_logs table for tracking user activities
CREATE TABLE IF NOT EXISTS user_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT NOT NULL,
    module VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    related_id INT NULL,
    related_type VARCHAR(50) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

