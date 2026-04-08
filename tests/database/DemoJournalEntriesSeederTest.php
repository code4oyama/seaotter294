<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\JournalEntryModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * @internal
 */
final class DemoJournalEntriesSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testSeederCreatesDemoEntriesAcrossTwelveMonths(): void
    {
        $year = (int) date('Y');
        $prefix = sprintf('DEMO-%d-', $year);

        $entries = (new JournalEntryModel())
            ->like('voucher_number', $prefix, 'after')
            ->orderBy('entry_date', 'ASC')
            ->findAll();

        $this->assertCount(60, $entries);
        $this->assertSame(sprintf('%d-01-01', $year), $entries[0]['entry_date']);
        $this->assertNotSame('', trim((string) ($entries[0]['ai_request_text'] ?? '')));

        $months = array_unique(array_map(
            static fn (array $entry): string => substr((string) $entry['entry_date'], 0, 7),
            $entries
        ));

        $this->assertCount(12, $months);

        $db = Database::connect();

        foreach ($entries as $entry) {
            $lines = $db->table('journal_lines')
                ->where('journal_entry_id', $entry['id'])
                ->orderBy('sort_order', 'ASC')
                ->get()
                ->getResultArray();

            $this->assertCount(2, $lines, (string) $entry['voucher_number']);

            $debitTotal = 0;
            $creditTotal = 0;
            foreach ($lines as $line) {
                if ($line['dc'] === 'debit') {
                    $debitTotal += (int) $line['amount'];
                }

                if ($line['dc'] === 'credit') {
                    $creditTotal += (int) $line['amount'];
                }
            }

            $this->assertSame($debitTotal, $creditTotal, (string) $entry['voucher_number']);
        }
    }

    public function testSeederCanBeRerunWithoutDuplicatingDemoEntries(): void
    {
        $year = (int) date('Y');
        $prefix = sprintf('DEMO-%d-', $year);

        $this->seed(DemoJournalEntriesSeeder::class);

        $model = new JournalEntryModel();

        $count = $model
            ->like('voucher_number', $prefix, 'after')
            ->countAllResults();

        $this->assertSame(60, $count);

        $sample = $model->like('voucher_number', $prefix, 'after')->first();
        $this->assertNotNull($sample);
        $this->assertStringContainsString('AI入力サンプル', (string) ($sample['ai_request_text'] ?? ''));
    }
}
