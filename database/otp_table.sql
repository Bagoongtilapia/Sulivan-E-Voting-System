-- Drop existing otp_codes table if exists
DROP TABLE IF EXISTS `otp_codes`;

-- Create otp_codes table with improved structure
CREATE TABLE `otp_codes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `otp_code` VARCHAR(6) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `is_used` TINYINT(1) DEFAULT 0,
    `attempts` INT DEFAULT 0,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes for better performance
CREATE INDEX idx_otp_lookup ON otp_codes(user_id, email, otp_code);
CREATE INDEX idx_otp_expiry ON otp_codes(expires_at);
CREATE INDEX idx_otp_used ON otp_codes(is_used);

-- Add trigger to automatically set expires_at
DELIMITER //
CREATE TRIGGER set_otp_expiry
BEFORE INSERT ON otp_codes
FOR EACH ROW
BEGIN
    SET NEW.expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE);
END//
DELIMITER ; 