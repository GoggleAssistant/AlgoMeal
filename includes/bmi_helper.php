<?php
/**
 * BMI Helper Utility for AlgoMeal
 * Standard Adult BMI Categories (WHO)
 */

if (!function_exists('categorizeBMI')) {
    function categorizeBMI($bmi)
    {
        if (!$bmi || $bmi <= 0) {
            return ['label' => 'Unknown', 'class' => 'badge-muted', 'style' => ''];
        }

        if ($bmi < 16.0) {
            $label = 'Severely Wasted';
        } else if ($bmi < 18.5) {
            $label = 'Wasted';
        } else if ($bmi < 25.0) {
            $label = 'Normal';
        } else if ($bmi < 30.0) {
            $label = 'Overweight';
        } else {
            $label = 'Obese';
        }

        return array_merge(['label' => $label], getStatusStyle($label));
    }
}

if (!function_exists('getStatusStyle')) {
    function getStatusStyle($status)
    {
        switch ($status) {
            case 'Severely Wasted':
                return ['class' => 'badge warning', 'style' => 'background-color: #ffebee; color: #c62828;'];
            case 'Wasted':
                return ['class' => 'badge warning', 'style' => 'background-color: #fff3e0; color: #ef6c00;'];
            case 'Normal':
                return ['class' => 'badge success', 'style' => 'background-color: #e8f5e9; color: #2e7d32;'];
            case 'Overweight':
                return ['class' => 'badge', 'style' => 'background-color: #f3e5f5; color: #7b1fa2;'];
            case 'Obese':
                return ['class' => 'badge', 'style' => 'background-color: #212121; color: #ffffff;'];
            default:
                return ['class' => 'badge-muted', 'style' => 'background-color: #f1f3f4; color: #5f6368;'];
        }
    }
}

if (!function_exists('getNutritionalStatus')) {
    function getNutritionalStatus($bmi)
    {
        $result = categorizeBMI($bmi);
        return $result['label'];
    }
}
?>