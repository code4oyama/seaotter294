<?php

use App\Controllers\Home;
use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\AccountModel;
use App\Models\FiscalPeriodModel;
use App\Models\JournalEntryModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class DashboardHomeFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testDashboardShowsCurrentCountsAndLinks(): void
    {
        $accountCount = (new AccountModel())->countAllResults();
        $periodCount = (new FiscalPeriodModel())->countAllResults();
        $entryCount = (new JournalEntryModel())->countAllResults();

        $result = $this->get('/');

        $result->assertStatus(200);
        $result->assertSee('NPO会計ダッシュボード');
        $result->assertSee($accountCount . ' 件');
        $result->assertSee($periodCount . ' 件');
        $result->assertSee($entryCount . ' 件');
        $result->assertSee('/accounts/new');
        $result->assertSee('/journal-entries/new');
        $result->assertSee('/reports');
    }

    public function testHomeIndexReturnsWelcomeMessageView(): void
    {
        $controller = new Home();
        $output = $controller->index();

        $this->assertIsString($output);
        $this->assertStringContainsString('Welcome to CodeIgniter 4!', $output);
    }
}
