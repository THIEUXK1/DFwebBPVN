<?php
// backend/tests/Unit/WeighingCoreServiceTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WeighingCoreService;
use App\Models\Device;
use App\Models\WeighingJobItem;
use App\Models\WeighingJob;
use App\Models\ProductionBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WeighingCoreServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $weighingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->weighingService = app(WeighingCoreService::class);
    }

    /**
     * Test cleaning raw scale reading strings (ExtractLastNumber VBA parity).
     */
    public function test_raw_weight_string_cleaning()
    {
        // Simple decimal
        $this->assertEquals(12.5, $this->weighingService->cleanWeight("12.5"));
        
        // Signed value
        $this->assertEquals(123.45, $this->weighingService->cleanWeight("+123.45"));
        $this->assertEquals(-1.5, $this->weighingService->cleanWeight("-1.5"));

        // Comma separated COM data
        $this->assertEquals(10.5, $this->weighingService->cleanWeight("12,ST,GS,+000010.5g"));
        
        // No number at all
        $this->assertNull($this->weighingService->cleanWeight("no_number_here"));
    }

    /**
     * Test stability detection policy.
     */
    public function test_stability_detection()
    {
        // Not enough samples
        $this->assertFalse($this->weighingService->checkStability(["12.5"]));

        // Unstable consecutive samples
        $this->assertFalse($this->weighingService->checkStability(["12.5", "12.6"]));

        // Stable consecutive samples
        $this->assertTrue($this->weighingService->checkStability(["12.5", "12.5"]));
        $this->assertTrue($this->weighingService->checkStability(["12.5", "12.6", "12.6"]));
    }

    /**
     * Test tolerance verification logic.
     */
    public function test_tolerance_checks()
    {
        // Target: 100g, minus: 1g, plus: 1g
        $this->assertEquals('ACCEPTED', $this->weighingService->checkTolerance(100.0, 100.0, 1.0, 1.0));
        $this->assertEquals('ACCEPTED', $this->weighingService->checkTolerance(99.0, 100.0, 1.0, 1.0));
        $this->assertEquals('ACCEPTED', $this->weighingService->checkTolerance(101.0, 100.0, 1.0, 1.0));
        
        // Out of range
        $this->assertEquals('REJECTED', $this->weighingService->checkTolerance(98.9, 100.0, 1.0, 1.0));
        $this->assertEquals('REJECTED', $this->weighingService->checkTolerance(101.1, 100.0, 1.0, 1.0));
    }
}
