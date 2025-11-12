-- Fix image_path column to allow NULL values
-- This prevents errors when adding ingredients without images

ALTER TABLE ingredient 
MODIFY COLUMN image_path VARCHAR(500) NULL DEFAULT NULL 
COMMENT 'Path to ingredient image file';

-- Optional: Set existing empty or invalid paths to NULL for consistency
UPDATE ingredient 
SET image_path = NULL 
WHERE image_path = '' OR image_path IS NULL;

