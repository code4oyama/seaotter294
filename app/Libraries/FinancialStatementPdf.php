<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

class FinancialStatementPdf
{
    public static function createBalanceSheetPdf(array $report): string
    {
        return self::renderPdf('貸借対照表', self::buildBalanceSheetHtml($report));
    }

    public static function createProfitLossPdf(array $report): string
    {
        return self::renderPdf('損益計算書', self::buildProfitLossHtml($report));
    }

    public static function createCashbookPdf(array $entries, array $summary): string
    {
        return self::renderPdf('出納帳', self::buildCashbookHtml($entries, $summary), 'landscape');
    }

    private static function renderPdf(string $title, string $htmlBody, string $orientation = 'portrait'): string
    {
        $fontPath = self::resolveJapaneseFontPath();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', [ROOTPATH, APPPATH, WRITEPATH, FCPATH]);
        $options->set('fontDir', self::ensureDompdfCachePath());
        $options->set('fontCache', self::ensureDompdfCachePath());
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', $fontPath !== null ? 'ipaexg' : 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $fontFamily = self::registerJapaneseFont($dompdf, $fontPath);

        $styles = self::buildStyles($fontFamily);
        $html = '<html><head><meta charset="UTF-8"><title>' . self::escape($title) . '</title><style>' . $styles . '</style></head><body>'
            . $htmlBody
            . '</body></html>';

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    private static function buildBalanceSheetHtml(array $report): string
    {
        $sections = $report['balanceSheet']['sections'];
        $totals = $report['balanceSheet']['totals'];

        return self::buildHeader(
            '貸借対照表',
            $report['period'],
            '基準日: ' . self::formatDate((string) $report['period']['end_date']) . ' 現在'
        )
            . self::buildSummaryTable([
                '資産合計' => $totals['assets'],
                '負債合計' => $totals['liabilities'],
                '正味財産合計' => $totals['net_assets'],
            ])
            . '<div class="note">※ 正味財産の部には当期正味財産増減額を含めています。</div>'
            . self::buildSectionTable('資産の部', $sections['assets'], '資産合計', $totals['assets'])
            . self::buildSectionTable('負債の部', $sections['liabilities'], '負債合計', $totals['liabilities'])
            . self::buildSectionTable(
                '正味財産の部',
                $sections['net_assets'],
                '正味財産合計',
                $totals['net_assets'],
                '負債・正味財産合計',
                $totals['liabilities_and_net_assets']
            );
    }

    private static function buildProfitLossHtml(array $report): string
    {
        $sections = $report['profitLoss']['sections'];
        $totals = $report['profitLoss']['totals'];

        return self::buildHeader(
            '損益計算書',
            $report['period'],
            '対象期間: ' . self::formatDate((string) $report['period']['start_date']) . ' 〜 ' . self::formatDate((string) $report['period']['end_date'])
        )
            . self::buildSummaryTable([
                '収益合計' => $totals['revenue'],
                '費用合計' => $totals['expense'],
                '当期正味財産増減額' => $totals['surplus'],
            ])
            . self::buildSectionTable('経常収益', $sections['revenue'], '収益合計', $totals['revenue'])
            . self::buildSectionTable(
                '経常費用',
                $sections['expense'],
                '費用合計',
                $totals['expense'],
                '当期正味財産増減額',
                $totals['surplus']
            );
    }

    private static function buildCashbookHtml(array $entries, array $summary): string
    {
        $dates = array_values(array_filter(array_map(
            static fn (array $entry): string => trim((string) ($entry['transaction_date'] ?? '')),
            $entries
        )));
        sort($dates);

        $period = [
            'name' => '全会計期間',
            'start_date' => $dates[0] ?? '',
            'end_date' => $dates[count($dates) - 1] ?? '',
        ];

        $html = self::buildHeader(
            '出納帳',
            $period,
            $dates === []
                ? '対象データはありません。'
                : '対象日付: ' . self::formatDate($period['start_date']) . ' 〜 ' . self::formatDate($period['end_date'])
        );

        $html .= self::buildSummaryTable([
            '入金合計' => (int) ($summary['receiptTotal'] ?? 0),
            '出金合計' => (int) ($summary['paymentTotal'] ?? 0),
            '差引残高' => (int) ($summary['balance'] ?? 0),
        ]);

        $html .= '<table class="statement-table cashbook-table">'
            . '<tr class="section-row"><th colspan="8">出納帳一覧</th></tr>'
            . '<tr class="column-row">'
            . '<th class="code">日付</th>'
            . '<th>会計期間</th>'
            . '<th>出納口座</th>'
            . '<th>区分</th>'
            . '<th class="right">金額</th>'
            . '<th>摘要・補足</th>'
            . '<th>相手科目</th>'
            . '<th>状態</th>'
            . '</tr>';

        if ($entries === []) {
            $html .= '<tr><td colspan="8" class="empty">出納帳データがありません。</td></tr>';
        } else {
            foreach ($entries as $entry) {
                $direction = (string) ($entry['direction'] ?? '') === 'payment' ? '出金' : '入金';
                $cashAccountLabel = trim((string) (($entry['cash_account_code'] ?? '') . ' ' . ($entry['cash_account_name'] ?? '')));
                $counterpartLabel = trim((string) (($entry['counterpart_account_code'] ?? '') . ' ' . ($entry['counterpart_account_name'] ?? '')));
                $status = (int) ($entry['journal_entry_id'] ?? 0) > 0 ? '仕訳化済み' : '未仕訳';
                $description = self::escape((string) ($entry['description'] ?? ''));
                $notes = trim((string) ($entry['notes'] ?? ''));

                if ($notes !== '') {
                    $description .= '<div class="cashbook-note">' . self::escape($notes) . '</div>';
                }

                $html .= '<tr>'
                    . '<td class="mono">' . self::escape((string) ($entry['transaction_date'] ?? '')) . '</td>'
                    . '<td>' . self::escape((string) ($entry['fiscal_period_name'] ?? '')) . '</td>'
                    . '<td>' . self::escape($cashAccountLabel !== '' ? $cashAccountLabel : '未設定') . '</td>'
                    . '<td class="mono">' . self::escape($direction) . '</td>'
                    . '<td class="right">¥' . self::yen((int) ($entry['amount'] ?? 0)) . '</td>'
                    . '<td>' . $description . '</td>'
                    . '<td>' . self::escape($counterpartLabel !== '' ? $counterpartLabel : '未設定') . '</td>'
                    . '<td class="mono">' . self::escape($status) . '</td>'
                    . '</tr>';
            }
        }

        return $html . '</table>';
    }

    /**
     * @param array<string, int> $items
     */
    private static function buildSummaryTable(array $items): string
    {
        $html = '<table class="summary-table"><tbody>';

        foreach ($items as $label => $amount) {
            $html .= '<tr>'
                . '<td class="label">' . self::escape($label) . '</td>'
                . '<td class="value">' . self::yen((int) $amount) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * @param list<array{code:string,name:string,amount:int}> $rows
     */
    private static function buildSectionTable(
        string $heading,
        array $rows,
        string $totalLabel,
        int $totalAmount,
        ?string $grandLabel = null,
        ?int $grandAmount = null
    ): string {
        return '<table class="statement-table">'
            . '<tr class="section-row"><th colspan="3">' . self::escape($heading) . '</th></tr>'
            . '<tr class="column-row"><th class="code">科目コード</th><th>科目名</th><th class="right">金額</th></tr>'
            . self::buildRows($rows)
            . '<tr class="total"><td colspan="2">' . self::escape($totalLabel) . '</td><td class="right">' . self::yen($totalAmount) . '</td></tr>'
            . ($grandLabel !== null && $grandAmount !== null
                ? '<tr class="grand-total"><td colspan="2">' . self::escape($grandLabel) . '</td><td class="right">' . self::yen($grandAmount) . '</td></tr>'
                : '')
            . '</table>';
    }

    /**
     * @param list<array{code:string,name:string,amount:int}> $rows
     */
    private static function buildRows(array $rows): string
    {
        if ($rows === []) {
            return '<tr><td colspan="3" class="empty">該当するデータがありません。</td></tr>';
        }

        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td class="code">' . self::escape($row['code']) . '</td>'
                . '<td>' . self::escape($row['name']) . '</td>'
                . '<td class="right">' . self::yen((int) $row['amount']) . '</td>'
                . '</tr>';
        }

        return $html;
    }

    private static function buildHeader(string $title, array $period, string $subtitle): string
    {
        return '<div class="report-header">'
            . '<div class="org-name">' . self::escape(self::organizationName()) . '</div>'
            . '<div class="title">' . self::escape($title) . '</div>'
            . '<div class="subtitle">' . self::escape($subtitle) . '</div>'
            . '<table class="meta-table">'
            . '<tr><td>会計期間</td><td>' . self::escape((string) $period['name']) . '</td><td>単位</td><td>円</td></tr>'
            . '<tr><td>対象期間</td><td>' . self::escape((string) $period['start_date']) . ' 〜 ' . self::escape((string) $period['end_date']) . '</td><td>出力日時</td><td>' . self::escape(date('Y-m-d H:i:s')) . '</td></tr>'
            . '</table>'
            . '</div>';
    }

    private static function buildStyles(string $fontFamily): string
    {
        return '*{font-family:"' . $fontFamily . '",DejaVu Sans,sans-serif;box-sizing:border-box;}'
            . 'body{font-size:10.5px;color:#111827;margin:18px 22px;line-height:1.45;}'
            . '.report-header{border-bottom:2px solid #1d4ed8;padding-bottom:10px;margin-bottom:12px;}'
            . '.org-name{text-align:right;font-size:10px;font-weight:700;color:#334155;margin-bottom:2px;}'
            . '.title{font-size:24px;font-weight:700;letter-spacing:2px;text-align:center;margin:0 0 4px 0;}'
            . '.subtitle{text-align:center;font-size:10px;color:#334155;margin-bottom:8px;}'
            . '.meta-table{width:100%;border-collapse:collapse;margin-top:6px;}'
            . '.meta-table td{border:1px solid #cbd5e1;padding:4px 6px;font-size:9px;}'
            . '.meta-table td:nth-child(odd){width:16%;background:#f8fafc;font-weight:700;}'
            . '.summary-table{width:100%;border-collapse:collapse;margin:0 0 12px 0;}'
            . '.summary-table td{border:1px solid #bfdbfe;padding:6px 8px;}'
            . '.summary-table .label{width:34%;background:#eff6ff;font-weight:700;}'
            . '.summary-table .value{text-align:right;font-size:11px;font-weight:700;}'
            . '.note{margin:0 0 10px 0;font-size:9px;color:#475569;}'
            . '.statement-table{width:100%;border-collapse:collapse;margin:0 0 14px 0;}'
            . '.statement-table th,.statement-table td{border:1px solid #94a3b8;padding:6px 8px;}'
            . '.statement-table .section-row th{background:#1d4ed8;color:#fff;text-align:left;font-size:11px;font-weight:700;}'
            . '.statement-table .column-row th{background:#dbeafe;color:#1e293b;font-size:9.5px;}'
            . '.statement-table .code{width:80px;}'
            . '.cashbook-table th,.cashbook-table td{font-size:8.5px;vertical-align:top;}'
            . '.cashbook-table .mono{white-space:nowrap;}'
            . '.cashbook-note{margin-top:3px;font-size:8px;color:#475569;}'
            . '.right{text-align:right;}'
            . '.total td{background:#f8fafc;font-weight:700;}'
            . '.grand-total td{background:#dbeafe;font-weight:700;font-size:11px;}'
            . '.empty{text-align:center;color:#64748b;}';
    }

    private static function ensureDompdfCachePath(): string
    {
        $dir = WRITEPATH . 'cache/dompdf/';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function resolveJapaneseFontPath(): ?string
    {
        $configured = (string) env('pdf.font_file', '');
        $candidates = array_filter([
            $configured,
            APPPATH . 'ThirdParty/fonts/ipaexg.ttf',
            APPPATH . 'ThirdParty/fonts/ipaexm.ttf',
            '/usr/share/fonts/truetype/ipaexfont-gothic/ipaexg.ttf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $real = realpath($candidate);
                if ($real !== false) {
                    return $real;
                }
            }
        }

        return null;
    }

    private static function registerJapaneseFont(Dompdf $dompdf, ?string $fontPath): string
    {
        if ($fontPath === null) {
            return 'DejaVu Sans';
        }

        $fontFamily = 'ipaexg';
        $fontUrl = 'file://' . str_replace(' ', '%20', str_replace('\\', '/', $fontPath));
        $metrics = $dompdf->getFontMetrics();

        $registeredNormal = $metrics->registerFont(['family' => $fontFamily, 'weight' => 'normal', 'style' => 'normal'], $fontUrl);
        $registeredBold = $metrics->registerFont(['family' => $fontFamily, 'weight' => 'bold', 'style' => 'normal'], $fontUrl);

        return ($registeredNormal || $registeredBold) ? $fontFamily : 'DejaVu Sans';
    }

    private static function organizationName(): string
    {
        return trim((string) env('report.organization_name', 'NPO会計アプリ')) ?: 'NPO会計アプリ';
    }

    private static function formatDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('Y年n月j日', $timestamp);
    }

    private static function yen(int $value): string
    {
        if ($value < 0) {
            return '△' . number_format(abs($value));
        }

        return number_format($value);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
