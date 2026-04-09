<?php
/**
 * BMI Helper Utility for AlgoMeal
 * Standard Adult BMI Categories (WHO)
 */

if (!function_exists('categorizeBMI')) {
    function categorizeBMI($bmi) {
        if (!$bmi || $bmi <= 0) {
            return ['label' => 'Unknown', 'class' => 'badge-muted', 'style' => ''];
        }
        
        if ($bmi < 16.0) {
            return ['label' => 'Severely Wasted', 'class' => 'badge warning', 'style' => 'background-color: #ffebee; color: #c62828;'];
        } else if ($bmi >= 16.0 && $bmi < 18.5) {
            return ['label' => 'Wasted', 'class' => 'badge warning', 'style' => 'background-color: #fff3e0; color: #ef6c00;'];
        } else if ($bmi >= 18.5 && $bmi < 25.0) {
            return ['label' => 'Normal', 'class' => 'badge success', 'style' => 'background-color: #e8f5e9; color: #2e7d32;'];
        } else if ($bmi >= 25.0 && $bmi < 30.0) {
            return ['label' => 'Overweight', 'class' => 'badge', 'style' => 'background-color: #f3e5f5; color: #7b1fa2;'];
        } else {
            return ['label' => 'Obese', 'class' => 'badge', 'style' => 'background-color: #212121; color: #ffffff;'];
        }
    }
}

if (!function_exists('getNutritionalStatus')) {
    function getNutritionalStatus($bmi) {
        $result = categorizeBMI($bmi);
        return $result['label'];
    }
}
?>
