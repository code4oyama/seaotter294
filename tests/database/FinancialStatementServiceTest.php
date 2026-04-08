<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\FiscalPeriodModel;
use App\Services\FinancialStatementService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class FinancialStatementServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testBuildReturnsBalancedStatementsForFiscalPeriod(): void
    {
        $period = (new FiscalPeriodModel())->where('name', date('Y') . '年度')->first();

        $this->assertNotNull($period);

        $service = new FinancialStatementService();
        $report = $service->buildForPeriod((int) $period['id']);

        $this->assertSame(
            $report['balanceSheet']['totals']['assets'],
            $report['balanceSheet']['totals']['liabilities_and_net_assets']
        );
        $this->assertGreaterThan(0, $report['profitLoss']['totals']['revenue']);
        $this->assertGreaterThan(0, $report['profitLoss']['totals']['expense']);
        $this->assertSame('2026年度', $report['period']['name']);
    }
}
