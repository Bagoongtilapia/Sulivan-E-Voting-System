-- Add name column to candidates table
ALTER TABLE candidates ADD COLUMN name VARCHAR(255) NOT NULL AFTER id;

-- Remove the foreign key constraint for student_id
ALTER TABLE candidates DROP FOREIGN KEY IF EXISTS candidates_ibfk_1;

-- Drop the student_id column
ALTER TABLE candidates DROP COLUMN student_id;
