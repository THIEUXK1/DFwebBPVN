<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\Workstation;
use App\Models\Material;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeMaterial;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\MaterialLabel;
use App\Models\MaterialTransport;
use App\Models\FeedOperation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class ScannerWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Machine $machine;
    private ProductionBatch $batch;
    private Material $material;
    private Recipe $recipe;
    private RecipeVersion $recipeVersion;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed workstations
        $this->artisan('db:seed', ['--class' => 'WorkstationsSeeder']);

        // Create User
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'workflow_tester';
        $user->display_name = 'Workflow Tester';
        $user->password_hash = password_hash('password', PASSWORD_BCRYPT);
        $user->save();
        $this->user = $user;

        // Create Material
        $this->material = Material::create([
            'code' => 'DYE-RED-01',
            'name' => 'Red Dye Active',
            'type' => 'DYE',
            'is_active' => true
        ]);

        // Create Machine
        $this->machine = Machine::create([
            'code' => 'VD-TEST',
            'name' => 'Machine VD Test',
            'is_active' => true
        ]);

        // Create Recipe & Active version
        $this->recipe = Recipe::create([
            'color_code' => 'RED-TEST',
            'product_code' => 'P-TEST',
            'description' => 'Test Recipe'
        ]);

        $this->recipeVersion = RecipeVersion::create([
            'recipe_id' => $this->recipe->id,
            'version' => 1,
            'status' => 'ACTIVE'
        ]);

        // Add recipe material
        RecipeMaterial::create([
            'recipe_version_id' => $this->recipeVersion->id,
            'material_code' => $this->material->code,
            'concentration' => 1.50,
            'process_code' => 'HS'
        ]);

        // Create Batch
        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'B-TEST-001',
            'color' => 'RED-TEST',
            'product_code' => 'P-TEST',
            'machine_id' => $this->machine->id,
            'cloth_weight' => 200.00,
            'status' => 'NEW'
        ]);
    }

    /**
     * Test full scan-driven workstation workflow.
     */
    public function test_full_scan_driven_weighing_and_transport_flow()
    {
        Sanctum::actingAs($this->user);

        // Step 1: Scan Order QR at DYE_WEIGHING workstation (WS-DYE)
        $wsDye = Workstation::where('code', 'WS-DYE')->firstOrFail();
        $orderQr = "DF:ORDER:{$this->batch->id}";

        $response = $this->postJson('/api/scanner/scan', [
            'qr_token' => $orderQr,
            'workstation_code' => $wsDye->code
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        
        $jobId = $response->json('data.job.id');
        $this->assertNotNull($jobId);

        // Verify weighing job items were generated
        $this->assertDatabaseHas('app.weighing_jobs', [
            'id' => $jobId,
            'job_type' => 'DYE',
            'status' => 'RECEIVED'
        ]);

        $jobItem = WeighingJobItem::where('weighing_job_id', $jobId)->firstOrFail();
        $this->assertEquals('DYE-RED-01', $jobItem->material_code);

        // Step 2: Weigh item (Dung sai ±1% -> planned_weight = calculated)
        $planned = (float)$jobItem->planned_weight;
        $this->assertGreaterThan(0, $planned);

        // Submit correct weight
        $weighResponse = $this->postJson("/api/weighing-jobs/items/{$jobItem->id}/weigh", [
            'weight' => $planned,
            'scale_device_id' => 'SCALE_DYE_01',
            'stable' => true
        ]);
        $weighResponse->assertStatus(200);
        $weighResponse->assertJsonPath('data.job_completed', true);

        // Verify batch status is WEIGHED
        $this->batch->refresh();
        $this->assertEquals('WEIGHED', $this->batch->status);

        // Step 3: Print material label
        $printResponse = $this->postJson("/api/weighing-jobs/{$jobId}/print", [
            'workstation_code' => $wsDye->code
        ]);
        $printResponse->assertStatus(200);
        
        $labelId = $printResponse->json('data.label_id');
        $this->assertNotNull($labelId);

        // Step 4: Scan label at MATERIAL_TRANSFER workstation (TRANS-01)
        $wsTrans = Workstation::where('code', 'TRANS-01')->firstOrFail();
        $labelQr = "DF:MATERIAL_LABEL:{$labelId}";

        $transResponse = $this->postJson('/api/scanner/scan', [
            'qr_token' => $labelQr,
            'workstation_code' => $wsTrans->code
        ]);
        $transResponse->assertStatus(200);
        
        $this->assertDatabaseHas('app.material_transports', [
            'material_label_id' => $labelId,
            'status' => 'IN_TRANSIT'
        ]);

        $this->batch->refresh();
        $this->assertEquals('IN_TRANSIT', $this->batch->status);

        // Step 5: Dual scan matching at TANK_RECEIVING workstation (TANK-01)
        $wsTank = Workstation::where('code', 'TANK-01')->firstOrFail();
        $machineQr = "DF:MACHINE:{$this->machine->id}";

        $verifyResponse = $this->postJson('/api/scanner/verify-tank', [
            'machine_or_tank_qr' => $machineQr,
            'material_label_qr' => $labelQr,
            'workstation_code' => $wsTank->code
        ]);
        $verifyResponse->assertStatus(200);

        $this->assertDatabaseHas('app.material_transports', [
            'material_label_id' => $labelId,
            'status' => 'ARRIVED_AT_TANK'
        ]);

        $this->batch->refresh();
        $this->assertEquals('ARRIVED_AT_TANK', $this->batch->status);

        // Step 6: Verify readiness & open feeding valve
        $readinessResponse = $this->getJson("/api/feed-operations/readiness/{$this->batch->id}");
        $readinessResponse->assertStatus(200);
        $readinessResponse->assertJsonPath('data.ready_to_feed', true);

        // Start Feed Operation
        $startFeed = $this->postJson('/api/feed-operations', [
            'batch_id' => $this->batch->id
        ]);
        $startFeed->assertStatus(201);
        $opId = $startFeed->json('data.id');

        // Verify water
        $verifyWater = $this->postJson("/api/feed-operations/{$opId}/verify-water");
        $verifyWater->assertStatus(200);

        // Complete feeding -> moves batch to DONE
        $completeFeed = $this->postJson("/api/feed-operations/{$opId}/complete");
        $completeFeed->assertStatus(200);

        $this->batch->refresh();
        $this->assertEquals('DONE', $this->batch->status);
    }
}
