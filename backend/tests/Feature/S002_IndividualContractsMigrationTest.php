<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Company;
use App\Models\IndividualContract;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase C-1 (schema): individual_contracts (3者間契約書) マイグレーション
 *
 * 差分カテゴリ: schema
 */
class S002_IndividualContractsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $expected = [
            'agent_id', 'company_id',
            'contract_date', 'start_date', 'end_date', 'terms',
            'monthly_fee', 'commission_rate',
            'soship_signed', 'soship_signed_at',
            'agent_signed', 'agent_signed_at',
            'customer_signed', 'customer_signed_at',
            'contract_document_path',
            'created_by', 'updated_by',
        ];

        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('individual_contracts', $col),
                "individual_contracts.{$col} should exist",
            );
        }
    }

    public function test_can_create_and_read_contract(): void
    {
        $agent = Agent::factory()->create();
        $company = Company::create(['name' => '顧客A']);

        $contract = IndividualContract::create([
            'agent_id'        => $agent->id,
            'company_id'      => $company->id,
            'contract_date'   => '2026-05-01',
            'start_date'      => '2026-05-01',
            'end_date'        => '2027-04-30',
            'terms'           => '特約: ...',
            'monthly_fee'     => 30000,
            'commission_rate' => 0.20,
        ]);

        $fresh = $contract->fresh();
        $this->assertEquals(30000, $fresh->monthly_fee);
        $this->assertEquals('0.2000', (string) $fresh->commission_rate);
        $this->assertSame('2026-05-01', $fresh->contract_date->toDateString());
        $this->assertFalse($fresh->soship_signed);
        $this->assertFalse($fresh->isFullySigned());
    }

    public function test_unique_constraint_per_agent_company_pair(): void
    {
        $agent = Agent::factory()->create();
        $company = Company::create(['name' => '顧客A']);

        IndividualContract::create([
            'agent_id'   => $agent->id,
            'company_id' => $company->id,
        ]);

        $this->expectException(QueryException::class);
        IndividualContract::create([
            'agent_id'   => $agent->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_is_fully_signed_returns_true_when_all_three_sign(): void
    {
        $agent = Agent::factory()->create();
        $company = Company::create(['name' => '顧客A']);

        $c = IndividualContract::create([
            'agent_id'         => $agent->id,
            'company_id'       => $company->id,
            'soship_signed'    => true,
            'agent_signed'     => true,
            'customer_signed'  => true,
        ]);

        $this->assertTrue($c->fresh()->isFullySigned());
    }
}
