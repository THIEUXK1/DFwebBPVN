<?php

namespace App\Services;

use App\Models\WaterConfig;

class FormulaCalculationService
{
    /**
     * Extract process code from color code.
     */
    public function getProcessCode(string $colorCode): string
    {
        $upper = strtoupper(trim($colorCode));
        if (str_starts_with($upper, 'HS')) {
            return 'HS';
        }
        if (str_starts_with($upper, 'E+')) {
            return 'E+';
        }
        if (str_starts_with($upper, 'G+')) {
            return 'G+';
        }
        if (str_starts_with($upper, 'W')) {
            return 'W';
        }
        
        // Default to the 2nd character of the color code
        return strlen($upper) >= 2 ? substr($upper, 1, 1) : '+';
    }

    /**
     * Calculate water volume based on weight, machine line, process, and white flag.
     */
    public function calculateWater(
        float $weight,
        string $machineLine,
        string $processCode,
        bool $isWhite = false,
        ?float $ratioOverride = null
    ): float {
        if ($isWhite) {
            $ratio = $ratioOverride ?? 1.1;
            $rawWater = $weight * $ratio;
            $water = $rawWater < 30.0 ? 30.0 : $rawWater;
        } else {
            // If override is provided, use it. Otherwise, look it up in water_configs.
            if ($ratioOverride !== null) {
                $ratio = $ratioOverride;
            } else {
                $config = WaterConfig::where('machine_line', $machineLine)
                    ->where('process_code', $processCode)
                    ->first();
                $ratio = $config ? (float) $config->liquor_ratio : 5.0; // Default ratio 5.0
            }
            
            $rawWater = $weight * $ratio;
            if ($rawWater + 15.0 <= 40.0) {
                $water = 40.0;
            } else {
                $water = $rawWater + 40.0;
            }
        }

        // Round up to the next multiple of 10
        // e.g., 40.0 -> 40.0, 40.1 -> 50.0, 41.0 -> 50.0
        return ceil($water / 10.0) * 10.0;
    }

    /**
     * Round weight precision based on Excel 1% rule.
     */
    public function getPrecisionRoundedWeight(float $waterVolume, float $concentration): float
    {
        $target = $waterVolume * $concentration;
        if ($target == 0.0) {
            return 0.0;
        }

        for ($decimals = 0; $decimals <= 4; $decimals++) {
            $rounded = round($target, $decimals);
            if (abs($rounded - $target) <= ($target * 0.01)) {
                return $rounded;
            }
        }
        
        return $target;
    }
}
