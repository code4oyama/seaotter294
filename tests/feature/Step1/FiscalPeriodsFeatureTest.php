<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\FiscalPeriodModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class FiscalPeriodsFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testIndexAndEditPageAreAccessible(): void
    {
        $period = (new FiscalPeriodModel())->where('name', date('Y') . '年度')->first();
        $this->assertNotNull($period);

        $index = $this->get('/fiscal-periods');
        $index->assertStatus(200);
        $index->assertSee('会計期間一覧');
        $index->assertSee((string) $period['name']);

        $edit = $this->get('/fiscal-periods/' . $period['id'] . '/edit');
        $edit->assertStatus(200);
        $edit->assertSee('会計期間編集');
    }

    public function testUpdateFiscalPeriodWorksWithoutClosingFeature(): void
    {
        $period = (new FiscalPeriodModel())->where('name', date('Y') . '年度')->first();
        $this->assertNotNull($period);

        $response = $this->post('/fiscal-periods/' . $period['id'] . '/update', [
            'name' => '2026年度（編集）',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response->assertStatus(302);
        $this->seeInDatabase('fiscal_periods', [
            'id' => $period['id'],
            'name' => '2026年度（編集）',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => 0,
        ]);
    }

    public function testUpdateRejectsInvalidDateRange(): void
    {
        $period = (new FiscalPeriodModel())->where('name', date('Y') . '年度')->first();
        $this->assertNotNull($period);

        $response = $this->post('/fiscal-periods/' . $period['id'] . '/update', [
            'name' => '2026年度',
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(302);
        $this->seeInDatabase('fiscal_periods', [
            'id' => $period['id'],
            'name' => date('Y') . '年度',
        ]);
    }
}
