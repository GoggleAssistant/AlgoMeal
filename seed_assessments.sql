-- =====================================================
-- Seed: Multiple Assessments per Student
-- =====================================================
-- Each student gets 5-6 assessments spread over ~8 months
-- covering realistic growth patterns (some underweight recovering, some normal)

-- Maria Clara Santos (F, born 2015-03-10) - Grade 4
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475602','2024-06-10',18.2,118.0,9,2,'Wasted','Normal'),
('102938475602','2024-07-15',18.8,118.2,9,4,'Wasted','Normal'),
('102938475602','2024-08-20',19.5,118.5,9,5,'Normal','Normal'),
('102938475602','2024-10-10',20.1,119.0,9,6,'Normal','Normal'),
('102938475602','2024-12-05',20.6,119.5,9,8,'Normal','Normal'),
('102938475602','2025-02-14',21.0,120.0,9,11,'Normal','Normal');

-- Jose Reyes (M, born 2013-07-22) - Grade 6
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475603','2024-06-10',22.0,130.0,10,10,'Severely Wasted','Stunted'),
('102938475603','2024-07-15',22.8,130.3,10,11,'Wasted','Stunted'),
('102938475603','2024-08-20',23.5,130.5,11,0,'Wasted','Stunted'),
('102938475603','2024-10-10',24.3,131.0,11,2,'Normal','Stunted'),
('102938475603','2024-12-05',25.0,131.5,11,4,'Normal','Normal'),
('102938475603','2025-02-14',25.8,132.0,11,6,'Normal','Normal');

-- Ana Bautista (F, born 2014-01-05) - Grade 5
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475604','2024-06-10',19.0,121.0,10,5,'Wasted','Normal'),
('102938475604','2024-07-15',19.6,121.2,10,6,'Normal','Normal'),
('102938475604','2024-08-20',20.2,121.5,10,7,'Normal','Normal'),
('102938475604','2024-10-10',20.8,122.0,10,9,'Normal','Normal'),
('102938475604','2024-12-05',21.3,122.3,10,11,'Normal','Normal'),
('102938475604','2025-02-14',21.8,122.5,11,1,'Normal','Normal');

-- Antonio Garcia (M, born 2012-09-18) - Grade 6
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475605','2024-06-10',28.5,138.0,11,8,'Normal','Normal'),
('102938475605','2024-07-15',29.0,138.3,11,9,'Normal','Normal'),
('102938475605','2024-08-20',29.8,138.5,11,11,'Normal','Normal'),
('102938475605','2024-10-10',30.5,139.0,12,0,'Normal','Normal'),
('102938475605','2024-12-05',31.2,139.5,12,2,'Normal','Normal'),
('102938475605','2025-02-14',32.0,140.0,12,4,'Normal','Normal');

-- Elena Mendoza (F, born 2014-05-30) - Grade 5
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475606','2024-06-10',17.5,115.0,10,0,'Wasted','Normal'),
('102938475606','2024-07-15',18.0,115.2,10,1,'Wasted','Normal'),
('102938475606','2024-08-20',18.8,115.5,10,2,'Wasted','Normal'),
('102938475606','2024-10-10',19.5,116.0,10,4,'Normal','Normal'),
('102938475606','2024-12-05',20.2,116.4,10,6,'Normal','Normal'),
('102938475606','2025-02-14',20.8,117.0,10,8,'Normal','Normal');

-- Ricardo Lopez (M, born 2013-11-14) - Grade 5
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475607','2024-06-10',21.5,127.0,10,6,'Wasted','Normal'),
('102938475607','2024-07-15',22.1,127.3,10,7,'Normal','Normal'),
('102938475607','2024-08-20',22.8,127.6,10,8,'Normal','Normal'),
('102938475607','2024-10-10',23.4,128.0,10,10,'Normal','Normal'),
('102938475607','2024-12-05',24.0,128.4,11,0,'Normal','Normal'),
('102938475607','2025-02-14',24.7,128.8,11,2,'Normal','Normal');

-- Sofia Hernandez (F, born 2015-08-22) - Grade 4
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475608','2024-06-10',16.8,112.0,8,9,'Severely Wasted','Stunted'),
('102938475608','2024-07-15',17.3,112.2,8,10,'Wasted','Stunted'),
('102938475608','2024-08-20',18.0,112.5,8,11,'Wasted','Stunted'),
('102938475608','2024-10-10',18.8,113.0,9,1,'Normal','Normal'),
('102938475608','2024-12-05',19.5,113.5,9,3,'Normal','Normal'),
('102938475608','2025-02-14',20.0,114.0,9,5,'Normal','Normal');

-- Fernando Aquino (M, born 2012-04-03) - Grade 6
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475609','2024-06-10',30.0,141.0,12,2,'Normal','Normal'),
('102938475609','2024-07-15',30.6,141.3,12,3,'Normal','Normal'),
('102938475609','2024-08-20',31.2,141.5,12,4,'Normal','Normal'),
('102938475609','2024-10-10',32.0,142.0,12,6,'Normal','Normal'),
('102938475609','2024-12-05',32.8,142.5,12,8,'Normal','Normal'),
('102938475609','2025-02-14',33.5,143.0,12,10,'Normal','Normal');

-- Isabel Del Rosario (F, born 2013-12-01) - Grade 5
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475610','2024-06-10',20.5,123.0,10,6,'Normal','Normal'),
('102938475610','2024-07-15',21.0,123.3,10,7,'Normal','Normal'),
('102938475610','2024-08-20',21.5,123.6,10,8,'Normal','Normal'),
('102938475610','2024-10-10',22.1,124.0,10,10,'Normal','Normal'),
('102938475610','2024-12-05',22.7,124.4,11,0,'Normal','Normal'),
('102938475610','2025-02-14',23.3,124.8,11,2,'Normal','Normal');

-- Gabriel Villanueva (M, born 2014-06-15) - Grade 4
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475611','2024-06-10',19.5,120.0,9,11,'Wasted','Normal'),
('102938475611','2024-07-15',20.1,120.3,10,1,'Normal','Normal'),
('102938475611','2024-08-20',20.7,120.6,10,2,'Normal','Normal'),
('102938475611','2024-10-10',21.4,121.0,10,3,'Normal','Normal'),
('102938475611','2024-12-05',22.0,121.4,10,5,'Normal','Normal'),
('102938475611','2025-02-14',22.6,121.8,10,8,'Normal','Normal');

-- Patricia Castro (F, born 2015-01-25) - Grade 4
INSERT INTO nutritional_record (student_id, assessment_date, weight, height, age_years, age_months, nutritional_status, hfa_status) VALUES
('102938475612','2024-06-10',18.0,116.0,9,4,'Wasted','Normal'),
('102938475612','2024-07-15',18.5,116.2,9,5,'Wasted','Normal'),
('102938475612','2024-08-20',19.2,116.5,9,6,'Normal','Normal'),
('102938475612','2024-10-10',19.8,117.0,9,8,'Normal','Normal'),
('102938475612','2024-12-05',20.3,117.3,9,10,'Normal','Normal'),
('102938475612','2025-02-14',20.8,117.7,10,0,'Normal','Normal');
