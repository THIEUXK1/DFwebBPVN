<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FormulaCalculationService;

class FormulaCalculationServiceTest extends TestCase
{
    private FormulaCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormulaCalculationService();
    }

    /**
     * Test process code extraction from color code.
     */
    public function test_get_process_code(): void
    {
        $this->assertEquals('+', $this->service->getProcessCode('A+110293'));
        $this->assertEquals('+', $this->service->getProcessCode('B+120067'));
        $this->assertEquals('P', $this->service->getProcessCode('EP69118'));
        $this->assertEquals('S', $this->service->getProcessCode('AS96518'));
        $this->assertEquals('HS', $this->service->getProcessCode('HS51250'));
        $this->assertEquals('E+', $this->service->getProcessCode('E+90465'));
        $this->assertEquals('G+', $this->service->getProcessCode('G+60503'));
        $this->assertEquals('W', $this->service->getProcessCode('W23773'));
    }

    /**
     * Test water volume calculation logic.
     */
    public function test_calculate_water(): void
    {
        // 1. Color recipe, weight = 5kg, line L2, process P, liquor ratio = 4.0
        // weight * ratio = 20.0
        // 20.0 + 15.0 = 35.0 <= 40.0 -> water = 40.0 -> ceil(40/10)*10 = 40.0
        $this->assertEquals(40.0, $this->service->calculateWater(5.0, 'L2', 'P', false, 4.0));

        // 2. Color recipe, weight = 6.5kg, line L2, process P, liquor ratio = 4.0
        // weight * ratio = 26.0
        // 26.0 + 15.0 = 41.0 > 40.0 -> water = 26.0 + 40.0 = 66.0 -> ceil(66/10)*10 = 70.0
        $this->assertEquals(70.0, $this->service->calculateWater(6.5, 'L2', 'P', false, 4.0));

        // 3. White recipe, weight = 10kg, liquor ratio = 1.1
        // weight * ratio = 11.0
        // 11.0 < 30.0 -> water = 30.0 -> ceil(30/10)*10 = 30.0
        $this->assertEquals(30.0, $this->service->calculateWater(10.0, 'L1', 'P', true, 1.1));

        // 4. White recipe, weight = 40kg, liquor ratio = 1.1
        // weight * ratio = 44.0
        // 44.0 >= 30.0 -> water = 44.0 -> ceil(44/10)*10 = 50.0
        $this->assertEquals(50.0, $this->service->calculateWater(40.0, 'L1', 'P', true, 1.1));
    }

    /**
     * Test precision rounding logic (Excel 1% rule).
     */
    public function test_get_precision_rounded_weight(): void
    {
        // Target: 70 * 0.21 = 14.7
        // Decimals 0: 15. Error = 0.3. 0.3 <= 0.147 (False)
        // Decimals 1: 14.7. Error = 0.0. 0.0 <= 0.147 (True) -> returns 14.7
        $this->assertEquals(14.7, $this->service->getPrecisionRoundedWeight(70.0, 0.21));

        // Target: 40 * 0.37555 = 15.022
        // Decimals 0: 15. Error = 0.022. 0.022 <= 0.15022 (True) -> returns 15
        $this->assertEquals(15.0, $this->service->getPrecisionRoundedWeight(40.0, 0.37555));

        // Target: 40 * 0.0055 = 0.22
        // Decimals 0: 0. Error = 0.22 <= 0.0022 (False)
        // Decimals 1: 0.2. Error = 0.02 <= 0.0022 (False)
        // Decimals 2: 0.22. Error = 0 <= 0.0022 (True) -> returns 0.22
        $this->assertEquals(0.22, $this->service->getPrecisionRoundedWeight(40.0, 0.0055));
    }
}
