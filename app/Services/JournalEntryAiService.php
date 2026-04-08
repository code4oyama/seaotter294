<?php

namespace App\Services;

use CodeIgniter\HTTP\CURLRequest;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class JournalEntryAiService
{
    private CURLRequest $client;

    public function __construct(?CURLRequest $client = null)
    {
        $this->client = $client ?? service('curlrequest', [
            'baseURI' => 'https://api.openai.com/v1/',
            'timeout' => 30,
        ]);
    }

    public function suggest(string $transactionText, array $accounts, ?string $entryDate = null): array
    {
        $transactionText = trim($transactionText);

        if ($transactionText === '') {
            throw new InvalidArgumentException('AIに伝える取引内容を入力してください。');
        }

        if ($accounts === []) {
            throw new RuntimeException('勘定科目が未登録のため、AI提案を利用できません。');
        }

        $apiKey = trim((string) env('openai.apiKey', ''));
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI APIキーが未設定です。.env に openai.apiKey を設定してください。');
        }

        try {
            $response = $this->client->post('chat/completions', [
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => (string) env('openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'あなたはNPO法人向け会計アシスタントです。複式簿記に従って仕訳候補を提案してください。返答は必ずJSONのみで、借方合計と貸方合計を一致させてください。',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->buildPrompt($transactionText, $accounts, $entryDate),
                        ],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('OpenAI APIへの接続に失敗しました。', 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody();
        $decodedResponse = json_decode($responseBody, true);

        if ($statusCode >= 400) {
            $apiMessage = is_array($decodedResponse) ? trim((string) ($decodedResponse['error']['message'] ?? '')) : '';
            $message = 'AI提案の取得に失敗しました。';
            if ($apiMessage !== '') {
                $message .= ' ' . $apiMessage;
            }

            throw new RuntimeException($message);
        }

        $content = trim((string) ($decodedResponse['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeException('AIから有効な回答を取得できませんでした。');
        }

        $payload = json_decode($content, true);
        if (! is_array($payload)) {
            $content = preg_replace('/^```json\s*|\s*```$/u', '', $content) ?? $content;
            $payload = json_decode($content, true);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('AIの回答形式を解釈できませんでした。');
        }

        return $this->normalizeSuggestion($payload, $accounts, $entryDate);
    }

    public function normalizeSuggestion(array $suggestion, array $accounts, ?string $fallbackEntryDate = null): array
    {
        $description = trim((string) ($suggestion['description'] ?? $suggestion['summary'] ?? ''));
        $note = trim((string) ($suggestion['note'] ?? $suggestion['reason'] ?? ''));
        $entryDate = $this->normalizeDate((string) ($suggestion['entry_date'] ?? $fallbackEntryDate ?? date('Y-m-d')));

        $lines = [];
        $debitTotal = 0;
        $creditTotal = 0;

        foreach ((array) ($suggestion['lines'] ?? []) as $index => $line) {
            $dc = strtolower(trim((string) ($line['dc'] ?? '')));
            $amount = (int) ($line['amount'] ?? 0);
            $account = $this->resolveAccount(
                $accounts,
                (string) ($line['account_code'] ?? ''),
                (string) ($line['account_name'] ?? '')
            );

            if (! in_array($dc, ['debit', 'credit'], true)) {
                throw new RuntimeException('AIの提案結果に不正な借方/貸方指定が含まれています。');
            }

            if ($amount <= 0) {
                throw new RuntimeException('AIの提案結果に不正な金額が含まれています。');
            }

            if ($account === null) {
                throw new RuntimeException('AIが返した勘定科目をシステム上の勘定科目に変換できませんでした。');
            }

            $normalizedLine = [
                'account_id' => (int) $account['id'],
                'account_code' => (string) $account['code'],
                'account_name' => (string) $account['name'],
                'dc' => $dc,
                'amount' => $amount,
                'line_description' => trim((string) ($line['line_description'] ?? $line['description'] ?? '')),
                'sort_order' => $index,
            ];

            $lines[] = $normalizedLine;

            if ($dc === 'debit') {
                $debitTotal += $amount;
            } else {
                $creditTotal += $amount;
            }
        }

        if (count($lines) < 2) {
            throw new RuntimeException('AIの提案結果を仕訳形式に変換できませんでした。');
        }

        if ($debitTotal !== $creditTotal) {
            throw new RuntimeException('AIの提案結果で貸借が一致しませんでした。内容を見直してください。');
        }

        return [
            'entry_date' => $entryDate,
            'description' => $description !== '' ? $description : 'AI提案仕訳',
            'note' => $note,
            'lines' => $lines,
        ];
    }

    private function buildPrompt(string $transactionText, array $accounts, ?string $entryDate = null): string
    {
        $accountList = array_map(
            static fn (array $account): string => sprintf(
                '- %s %s (%s)',
                (string) ($account['code'] ?? ''),
                (string) ($account['name'] ?? ''),
                (string) ($account['category'] ?? '')
            ),
            $accounts
        );

        $normalizedDate = $this->normalizeDate($entryDate ?: date('Y-m-d'));

        return implode("\n", [
            '次の取引内容を、NPO法人向けの複式簿記の仕訳候補に変換してください。',
            '利用できる勘定科目は以下のみです。必ずこの一覧から選んでください。',
            ...$accountList,
            '',
            '制約:',
            '- 借方合計と貸方合計を必ず一致させる',
            '- 金額は整数（円）で返す',
            '- 不明な勘定科目は一覧から最も近いものを選び、noteに補足を書く',
            '- 返答はJSONオブジェクトのみ',
            '- lines は2行以上',
            '',
            'JSON例:',
            '{',
            '  "entry_date": "' . $normalizedDate . '",',
            '  "description": "4月会費を現金で受領",',
            '  "note": "会費収入として処理",',
            '  "lines": [',
            '    {"dc": "debit", "account_code": "1110", "account_name": "現金", "amount": 5000, "line_description": "4月会費"},',
            '    {"dc": "credit", "account_code": "4110", "account_name": "受取会費", "amount": 5000, "line_description": "4月会費"}',
            '  ]',
            '}',
            '',
            '取引内容:',
            $transactionText,
        ]);
    }

    private function resolveAccount(array $accounts, string $accountCode, string $accountName): ?array
    {
        $normalizedCode = strtolower(trim($accountCode));
        $normalizedName = mb_strtolower(trim($accountName));

        foreach ($accounts as $account) {
            if ($normalizedCode !== '' && strtolower((string) ($account['code'] ?? '')) === $normalizedCode) {
                return $account;
            }
        }

        foreach ($accounts as $account) {
            if ($normalizedName !== '' && mb_strtolower(trim((string) ($account['name'] ?? ''))) === $normalizedName) {
                return $account;
            }
        }

        foreach ($accounts as $account) {
            $name = mb_strtolower(trim((string) ($account['name'] ?? '')));
            if ($normalizedName !== '' && $name !== '' && str_contains($normalizedName, $name)) {
                return $account;
            }
        }

        return null;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        $timestamp = strtotime($date);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }
}
