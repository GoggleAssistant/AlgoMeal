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
            return ['label' => 'Severely Wasted (Severe Thinness)', 'class' => 'badge warning', 'style' => 'background-color: #ffebee; color: #c62828;'];
        } else if ($bmi >= 16.0 && $bmi < 18.5) {
            return ['label' => 'Moderate/Mild Thinness', 'class' => 'badge warning', 'style' => 'background-color: #fff3e0; color: #ef6c00;'];
        } else if ($bmi >= 18.5 && $bmi < 25.0) {
            return ['label' => 'Healthy/Normal Weight', 'class' => 'badge success', 'style' => 'background-color: #e8f5e9; color: #2e7d32;'];
        } else if ($bmi >= 25.0 && $bmi < 30.0) {
            return ['label' => 'Overweight', 'class' => 'badge', 'style' => 'background-color: #f3e5f5; color: #7b1fa2;'];
        } else if ($bmi >= 30.0 && $bmi < 35.0) {
            return ['label' => 'Obese Class I (Moderate)', 'class' => 'badge', 'style' => 'background-color: #212121; color: #ffffff;'];
        } else if ($bmi >= 35.0 && $bmi < 40.0) {
            return ['label' => 'Obese Class II (Severe)', 'class' => 'badge', 'style' => 'background-color: #1a1a1a; color: #ffffff;'];
        } else {
            return ['label' => 'Obese Class III (Very Severe/Morbid)', 'class' => 'badge', 'style' => 'background-color: #000000; color: #ffffff;'];
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
