-- Create food_donations table
CREATE TABLE IF NOT EXISTS food_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    food_type ENUM('cooked', 'raw', 'packaged', 'beverages', 'other') NOT NULL,
    quantity VARCHAR(100) NOT NULL,
    expiration_date DATE,
    location_address TEXT NOT NULL,
    location_lat DECIMAL(10, 8) NULL,
    location_lng DECIMAL(11, 8) NULL,
    pickup_time_start TIME,
    pickup_time_end TIME,
    contact_method ENUM('phone', 'email', 'both') NOT NULL,
    contact_info VARCHAR(255) NOT NULL,
    images JSON,
    dietary_info TEXT,
    allergens TEXT,
    storage_instructions TEXT,
    status ENUM('available', 'reserved', 'claimed', 'expired', 'cancelled') DEFAULT 'available',
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_location (location_lat, location_lng),
    INDEX idx_food_type (food_type),
    INDEX idx_expiration_date (expiration_date)
);

-- Create food_donation_reservations table
CREATE TABLE IF NOT EXISTS food_donation_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    requester_id INT NOT NULL,
    message TEXT,
    contact_info VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (donation_id) REFERENCES food_donations(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    INDEX idx_donation_id (donation_id),
    INDEX idx_requester_id (requester_id),
    INDEX idx_status (status)
);

-- Create food_donation_feedback table
CREATE TABLE IF NOT EXISTS food_donation_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    donor_id INT NOT NULL,
    requester_id INT NOT NULL,
    rating TINYINT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    feedback_type ENUM('donor_to_requester', 'requester_to_donor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES food_donations(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    INDEX idx_donation_id (donation_id),
    INDEX idx_feedback_type (feedback_type)
);
