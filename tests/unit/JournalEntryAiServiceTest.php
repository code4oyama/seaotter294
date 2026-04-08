<?php

use App\Services\JournalEntryAiService;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class JournalEntryAiServiceTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        putenv('openai.apiKey=');
        unset($_ENV['openai.apiKey'], $_SERVER['openai.apiKey']);

        parent::tearDown();
    }

    public function testSuggestThrowsWhenTransactionTextIsEmpty(): void
    {
        $service = new JournalEntryAiService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AIに伝える取引内容を入力してください。');

        $service->suggest('   ', $this->sampleAccounts(), '2026-04-06');
    }

    public function testSuggestThrowsWhenAccountsAreEmpty(): void
    {
        $service = new JournalEntryAiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('勘定科目が未登録');

        $service->suggest('4月会費を現金で受領', [], '2026-04-06');
    }

    public function testSuggestParsesJsonWrappedInCodeFence(): void
    {
        $this->setApiKey('dummy-test-key');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'choices' => [[
                'message' => [
                    'content' => "```json\n{\"summary\":\"会費受領\",\"reason\":\"会費収入として処理\",\"lines\":[{\"dc\":\"debit\",\"account_code\":\"1110\",\"amount\":5000,\"description\":\"4月会費\"},{\"dc\":\"credit\",\"account_name\":\"受取会費\",\"amount\":5000,\"description\":\"4月会費\"}]}\n```",
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE));

        $client = $this->getMockBuilder(CURLRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['post'])
            ->getMock();
        $client->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $service = new JournalEntryAiService($client);
        $suggestion = $service->suggest('4月会費を現金で受領', $this->sampleAccounts(), '2026-04-06');

        $this->assertSame('会費受領', $suggestion['description']);
        $this->assertSame('会費収入として処理', $suggestion['note']);
        $this->assertCount(2, $suggestion['lines']);
        $this->assertSame('1110', $suggestion['lines'][0]['account_code']);
        $this->assertSame('4110', $suggestion['lines'][1]['account_code']);
    }

    public function testNormalizeSuggestionMapsAccountsAndBalancesLines(): void
    {
        $service = new JournalEntryAiService();

        $accounts = [
            ['id' => 1, 'code' => '1110', 'name' => '現金', 'category' => 'asset'],
            ['id' => 5, 'code' => '4110', 'name' => '受取会費', 'category' => 'revenue'],
        ];

        $suggestion = $service->normalizeSuggestion([
            'entry_date' => '2026-04-06',
            'description' => '会費を現金で受け取った',
            'lines' => [
                ['dc' => 'debit', 'account_code' => '1110', 'amount' => 5000, 'line_description' => '4月会費'],
                ['dc' => 'credit', 'account_name' => '受取会費', 'amount' => 5000, 'line_description' => '4月会費'],
            ],
        ], $accounts);

        $this->assertSame('2026-04-06', $suggestion['entry_date']);
        $this->assertSame('会費を現金で受け取った', $suggestion['description']);
        $this->assertCount(2, $suggestion['lines']);
        $this->assertSame(1, $suggestion['lines'][0]['account_id']);
        $this->assertSame(5, $suggestion['lines'][1]['account_id']);
    }

    public function testNormalizeSuggestionUsesFallbackFieldsAndPartialAccountName(): void
    {
        $service = new JournalEntryAiService();

        $suggestion = $service->normalizeSuggestion([
            'entry_date' => 'invalid-date',
            'summary' => '概要からの摘要',
            'reason' => '補足メモ',
            'lines' => [
                ['dc' => 'debit', 'account_name' => '現金残高', 'amount' => 7000, 'description' => '借方メモ'],
                ['dc' => 'credit', 'account_code' => '4110', 'amount' => 7000, 'description' => '貸方メモ'],
            ],
        ], $this->sampleAccounts());

        $this->assertSame(date('Y-m-d'), $suggestion['entry_date']);
        $this->assertSame('概要からの摘要', $suggestion['description']);
        $this->assertSame('補足メモ', $suggestion['note']);
        $this->assertSame(1, $suggestion['lines'][0]['account_id']);
    }

    public function testNormalizeSuggestionThrowsWhenDebitCreditSpecifierIsInvalid(): void
    {
        $service = new JournalEntryAiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('不正な借方/貸方指定');

        $service->normalizeSuggestion([
            'lines' => [
                ['dc' => 'sideways', 'account_code' => '1110', 'amount' => 3000],
                ['dc' => 'credit', 'account_code' => '4110', 'amount' => 3000],
            ],
        ], $this->sampleAccounts());
    }

    public function testNormalizeSuggestionThrowsWhenAmountIsInvalid(): void
    {
        $service = new JournalEntryAiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('不正な金額');

        $service->normalizeSuggestion([
            'lines' => [
                ['dc' => 'debit', 'account_code' => '1110', 'amount' => 0],
                ['dc' => 'credit', 'account_code' => '4110', 'amount' => 1000],
            ],
        ], $this->sampleAccounts());
    }

    public function testNormalizeSuggestionThrowsWhenAccountCannotBeResolved(): void
    {
        $service = new JournalEntryAiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('勘定科目に変換できませんでした');

        $service->normalizeSuggestion([
            'lines' => [
                ['dc' => 'debit', 'account_code' => '9999', 'amount' => 2000],
                ['dc' => 'credit', 'account_code' => '4110', 'amount' => 2000],
            ],
        ], $this->sampleAccounts());
    }

    public function testNormalizeSuggestionThrowsWhenLinesAreUnbalanced(): void
    {
        $service = new JournalEntryAiService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('貸借');

        $service->normalizeSuggestion([
            'description' => '不整合データ',
            'lines' => [
                ['dc' => 'debit', 'account_code' => '1110', 'amount' => 3000],
                ['dc' => 'credit', 'account_code' => '4110', 'amount' => 2000],
            ],
        ], [
            ['id' => 1, 'code' => '1110', 'name' => '現金', 'category' => 'asset'],
            ['id' => 5, 'code' => '4110', 'name' => '受取会費', 'category' => 'revenue'],
        ]);
    }

    private function sampleAccounts(): array
    {
        return [
            ['id' => 1, 'code' => '1110', 'name' => '現金', 'category' => 'asset'],
            ['id' => 5, 'code' => '4110', 'name' => '受取会費', 'category' => 'revenue'],
        ];
    }

    private function setApiKey(string $value): void
    {
        putenv('openai.apiKey=' . $value);
        $_ENV['openai.apiKey'] = $value;
        $_SERVER['openai.apiKey'] = $value;
    }
}
