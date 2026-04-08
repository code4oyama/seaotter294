<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\FiscalPeriodModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class FinancialStatementsFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testReportsIndexShowsPdfLinks(): void
    {
        $result = $this->get('/reports');

        $result->assertStatus(200);
        $result->assertSee('貸借対照表');
        $result->assertSee('損益計算書');
    }

    public function testBalanceSheetPdfEndpointReturnsPdfResponse(): void
    {
        $period = (new FiscalPeriodModel())->where('name', date('Y') . '年度')->first();
        $this->assertNotNull($period);

        $result = $this->get('/reports/balance-sheet/' . $period['id'] . '/pdf');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-', $result->getBody());
    }

    public function testProfitLossPdfEndpointReturnsPdfResponse(): void
    {
        $period = (new FiscalPeriodModel())->where('name', date('Y') . '年度')->first();
        $this->assertNotNull($period);

        $result = $this->get('/reports/profit-loss/' . $period['id'] . '/pdf');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-', $result->getBody());
    }
}
