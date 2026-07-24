<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Problem;
use App\Models\Cause;
use App\Models\ProblemCauseRule;
use App\Models\Process;
use App\Models\Parameter;
use App\Models\TroubleshootingCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TroubleshootingInferenceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TroubleshootingKnowledgeBaseSeeder::class);

        $user = User::where('username', 'trouble_engineer')->first();
        if (!$user) {
            $user = new User();
            $user->id = (string) \Illuminate\Support\Str::uuid();
            $user->username = 'trouble_engineer';
            $user->display_name = 'Troubleshooting Engineer';
            $user->password_hash = password_hash('eng123', PASSWORD_BCRYPT);
            $user->save();
        }
        $this->user = $user;
        $this->user->roles()->syncWithoutDetaching(
            \App\Models\Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id
        );
    }

    /**
     * Test list APIs for problems, processes, and parameters.
     */
    public function test_troubleshooting_list_apis(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/troubleshooting/problems');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/troubleshooting/processes');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/troubleshooting/parameters');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    /**
     * Test full diagnosis workflow: diagnosing, creating case, verifying score, and closing case.
     */
    public function test_diagnose_and_resolve_workflow(): void
    {
        // P001 = "色花", PR03 = "染色"
        // Let's pass parameters:
        // Temperature (P001 in parameter table) has: standard 105, LSL 100, USL 120, Score 5.
        // Let's pass Temperature actual = 4.0, setpoint = 105.0. Lower Limit = 105 - 100 = 5.0.
        // Since actual 4.0 < 5.0, status will be LOW!
        // Humidity (categorical) = "HIGH".
        
        $diagData = [
            'stage_id' => 'PR03',
            'problems' => ['P001'],
            'parameters' => [
                'Temperature' => ['actual' => 4.0, 'set' => 105.0],
                'Steam' => ['actual' => 4.5, 'set' => 4.5], // Normal
                'Air Pressure' => ['actual' => 4.5, 'set' => 4.5], // Normal
                'Speed' => ['actual' => 25.0, 'set' => 25.0], // Normal
                'Humidity' => 'HIGH'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/troubleshooting/diagnose', $diagData);

        $response->assertStatus(201);
        $caseId = $response->json('data.case_id');
        $recs = $response->json('data.recommendations');

        $this->assertNotNull($caseId);
        $this->assertNotEmpty($recs);

        // Verify the top ranked recommendation
        $topRec = $recs[0];
        $this->assertEquals(1, $topRec['rank']);
        $this->assertNotNull($topRec['cause_id']);
        $this->assertNotNull($topRec['cause_name']);

        // Check if case is registered in DB
        $case = TroubleshootingCase::with(['problems', 'evidences', 'recommendations'])->find($caseId);
        $this->assertNotNull($case);
        $this->assertEquals('OPEN', $case->status);
        $this->assertCount(1, $case->problems);
        $this->assertNotEmpty($case->evidences);
        $this->assertNotEmpty($case->recommendations);

        // Verify evidence value for Temperature is indeed recorded
        $tempEvidence = $case->evidences->first(function($ev) {
            return $ev->parameter_id === 'P001';
        });
        $this->assertNotNull($tempEvidence);
        $this->assertEquals(4.0, $tempEvidence->actual_value);
        $this->assertEquals('LOW', $tempEvidence->status);

        // Resolve / Close the case
        $resolveResponse = $this->actingAs($this->user)
            ->postJson("/api/troubleshooting/cases/{$caseId}/resolve", [
                'actual_cause_id' => $topRec['cause_id'],
                'resolution_notes' => 'Đã vệ sinh lọc bụi và căn chỉnh lại bộ gia nhiệt.',
                'effectiveness_rating' => 5
            ]);

        $resolveResponse->assertStatus(200);
        $this->assertEquals('CLOSED', $resolveResponse->json('data.status'));
        $this->assertEquals($topRec['cause_id'], $resolveResponse->json('data.actual_cause_id'));
        $this->assertEquals(5, $resolveResponse->json('data.effectiveness_rating'));

        // Clean up
        $case->delete();
    }
}
