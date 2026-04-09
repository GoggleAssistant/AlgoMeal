INSERT INTO dietary_restrictions (restriction_name, type) VALUES ('Lactose Intolerance', 'Allergy'), ('Peanut Allergy', 'Allergy'), ('Shellfish Allergy', 'Allergy') ON DUPLICATE KEY UPDATE restriction_name=restriction_name;

INSERT IGNORE INTO student (student_id, last_name, first_name, sex, birth_date, grade_level, section, is_4ps_beneficiary, target_weight, deworming_status) VALUES 
('LRN-001', 'Morales', 'Brent', 'Male', '2016-01-01', 'Grade 1', 'Narra', 1, 20.00, 1),
('LRN-002', 'Balana', 'Franz', 'Male', '2016-02-01', 'Grade 1', 'Narra', 0, 20.00, 1),
('LRN-003', 'Caguete', 'Earl', 'Male', '2016-03-01', 'Grade 1', 'Narra', 1, 20.50, 0),
('LRN-004', 'Tormes', 'Margaret', 'Female', '2016-04-01', 'Grade 1', 'Narra', 0, 19.50, 1),
('LRN-005', 'Burger', 'Malupiton', 'Male', '2016-05-01', 'Grade 1', 'Narra', 1, 22.00, 0),
('LRN-006', 'Dela Cruz', 'Juan', 'Male', '2016-06-01', 'Grade 2', 'Molave', 1, 25.00, 1),
('LRN-007', 'Santos', 'Maria', 'Female', '2016-07-01', 'Grade 3', 'Yakal', 0, 28.00, 1);

INSERT IGNORE INTO nutritional_record (student_id, created_by, height, weight, bmi, nutritional_status, assessment_date) VALUES 
('LRN-001', 1, 115.00, 14.50, 10.96, 'Severely Wasted', '2026-08-01'),
('LRN-001', 1, 115.00, 15.00, 11.34, 'Wasted', '2026-09-01'),
('LRN-002', 1, 112.00, 13.00, 10.36, 'Severely Wasted', '2026-08-01'),
('LRN-002', 1, 112.00, 14.20, 11.32, 'Wasted', '2026-09-01'),
('LRN-003', 1, 118.00, 17.50, 12.57, 'Normal', '2026-08-01'),
('LRN-003', 1, 118.00, 17.30, 12.42, 'Wasted', '2026-09-01'),
('LRN-004', 1, 114.00, 15.00, 11.54, 'Wasted', '2026-08-01'),
('LRN-004', 1, 114.00, 15.00, 11.54, 'Wasted', '2026-09-01'),
('LRN-005', 1, 120.00, 16.00, 11.11, 'Wasted', '2026-08-01'),
('LRN-005', 1, 120.00, 16.80, 11.67, 'Normal', '2026-09-01');

INSERT IGNORE INTO student_allergy_map (student_id, restriction_id) VALUES 
('LRN-001', 1),
('LRN-003', 2),
('LRN-005', 3);
