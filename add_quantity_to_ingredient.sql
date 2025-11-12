-- Add quantity field to ingredient table
ALTER TABLE ingredient 
ADD COLUMN IF NOT EXISTS quantity DECIMAL(10,2) NULL COMMENT 'Quantity of the ingredient';

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_ingredient_quantity ON ingredient(quantity);

