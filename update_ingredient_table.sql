-- Add expiration_date and status fields to ingredient table
ALTER TABLE ingredient 
ADD COLUMN IF NOT EXISTS expiration_date DATE NULL,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'used', 'expired') DEFAULT 'active';

-- Drop feedback tables if they exist (removing like/dislike feature)
DROP TABLE IF EXISTS ingredient_feedback;

-- Create index for better performance on status queries
CREATE INDEX IF NOT EXISTS idx_ingredient_status ON ingredient(status);
CREATE INDEX IF NOT EXISTS idx_ingredient_expiration ON ingredient(expiration_date);

