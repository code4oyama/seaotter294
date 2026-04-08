<?php

use App\Database\Seeds\AccountingBootstrapSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class JournalEntriesAiFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = AccountingBootstrapSeeder::class;

    public function testAiSuggestReturnsValidationErrorWhenTransactionTextIsEmpty(): void
    {
        $result = $this->post('/journal-entries/ai-suggest', [
            'transaction_text' => '',
            'entry_date' => '2026-04-06',
        ]);

        $result->assertStatus(422);
        $result->assertSee('取引内容');
    }

    public function testAiSuggestReturnsServerErrorWhenApiKeyIsMissing(): void
    {
        putenv('openai.apiKey=');
        $_ENV['openai.apiKey'] = '';
        $_SERVER['openai.apiKey'] = '';

        $result = $this->post('/journal-entries/ai-suggest', [
            'transaction_text' => '4月分の会費5,000円を現金で受け取った。',
            'entry_date' => '2026-04-06',
        ]);

        $result->assertStatus(500);
        $result->assertSee('OpenAI APIキーが未設定');
    }
}
