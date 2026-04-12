<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=AlgoMeal_Student_Template.csv');

$output = fopen('php://output', 'w');

// Headers based on the student table and common requirements
fputcsv($output, [
    'Student ID (LRN)', 
    'Last Name', 
    'First Name', 
    'Sex (Male/Female)', 
    'Birth Date (YYYY-MM-DD)', 
    'Grade Level (e.g. Grade 1)', 
    'Section'
]);

// Include some sample data rows (commented out or just one example)
fputcsv($output, ['123456789012', 'Dela Cruz', 'Juan', 'Male', '2015-05-20', 'Grade 4', 'Aguinaldo']);

fclose($output);
exit;
