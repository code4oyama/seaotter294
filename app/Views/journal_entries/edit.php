<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldDcs = array_values((array) old('dc'));
$oldAccountIds = array_values((array) old('account_id'));
$oldAmounts = array_values((array) old('amount'));
$oldLineDescriptions = array_values((array) old('line_description'));
$existingLines = $lines ?? [];
$rowCount = max(2, count($existingLines), count($oldDcs), count($oldAccountIds), count($oldAmounts), count($oldLineDescriptions));
$categoryLabels = [
  'asset' => '資産',
  'liability' => '負債',
  'net_asset' => '正味財産',
  'revenue' => '収益',
  'expense' => '費用',
];
?>
<h1><?= esc($title) ?></h1>

<form method="post" action="/journal-entries/<?= esc((string) $entry['id']) ?>/update" id="entry-form">
  <?= csrf_field() ?>

  <label>会計期間</label>
  <select name="fiscal_period_id" required>
    <option value="">選択してください</option>
    <?php foreach ($periods as $period): ?>
      <option value="<?= esc((string) $period['id']) ?>" <?= old('fiscal_period_id', (string) $entry['fiscal_period_id']) == $period['id'] ? 'selected' : '' ?>>
        <?= esc($period['name']) ?> (<?= esc($period['start_date']) ?> - <?= esc($period['end_date']) ?>)
      </option>
    <?php endforeach; ?>
  </select>

  <label>伝票番号</label>
  <input name="voucher_number" value="<?= esc(old('voucher_number', $entry['voucher_number'])) ?>" required>

  <label>取引日</label>
  <input type="date" name="entry_date" value="<?= esc(old('entry_date', $entry['entry_date'])) ?>" required>

  <label>摘要</label>
  <input name="description" value="<?= esc(old('description', $entry['description'])) ?>" required>

  <label>AIに伝えた内容（任意）</label>
  <textarea name="ai_request_text" rows="4" placeholder="AI提案に使った自然文を保存できます。"><?= esc(old('ai_request_text', $entry['ai_request_text'] ?? '')) ?></textarea>

  <div class="stack" style="margin-top:20px; align-items:center; justify-content:space-between;">
    <h2 style="margin:0; font-size:1.1rem;">仕訳明細</h2>
    <button class="btn secondary" type="button" id="add-line-button">明細行を追加</button>
  </div>

  <table id="lines-table" class="responsive-table line-entry-table">
    <thead>
      <tr>
        <th>区分</th>
        <th>勘定科目</th>
        <th>増減</th>
        <th>金額</th>
        <th>明細摘要</th>
      </tr>
    </thead>
    <tbody>
      <?php for ($i = 0; $i < $rowCount; $i++): ?>
        <?php
        $existingLine = $existingLines[$i] ?? [];
        $dcValue = $oldDcs[$i] ?? ($existingLine['dc'] ?? ($i === 1 ? 'credit' : 'debit'));
        $accountIdValue = $oldAccountIds[$i] ?? ($existingLine['account_id'] ?? '');
        $amountValue = $oldAmounts[$i] ?? ($existingLine['amount'] ?? '');
        $lineDescriptionValue = $oldLineDescriptions[$i] ?? ($existingLine['line_description'] ?? '');
        ?>
        <tr>
          <td data-label="区分">
            <select name="dc[]" required>
              <option value="debit" <?= $dcValue === 'debit' ? 'selected' : '' ?>>借方</option>
              <option value="credit" <?= $dcValue === 'credit' ? 'selected' : '' ?>>貸方</option>
            </select>
          </td>
          <td data-label="勘定科目">
            <select name="account_id[]" required>
              <option value="">選択</option>
              <?php foreach ($accounts as $account): ?>
                <?php $categoryLabel = $categoryLabels[$account['category']] ?? $account['category']; ?>
                <option value="<?= esc((string) $account['id']) ?>" data-category="<?= esc($account['category']) ?>" <?= (string) $accountIdValue === (string) $account['id'] ? 'selected' : '' ?>>
                  <?= esc($account['code'] . ' ' . $account['name'] . ' [' . $categoryLabel . ']') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="増減">
            <span class="change-indicator" style="display:inline-block; min-width:52px; padding:4px 8px; border-radius:9999px; text-align:center; background:#e2e8f0; color:#475569; font-size:0.9rem;">—</span>
          </td>
          <td data-label="金額"><input type="number" min="1" step="1" name="amount[]" value="<?= esc((string) $amountValue) ?>" required></td>
          <td data-label="明細摘要"><input name="line_description[]" value="<?= esc((string) $lineDescriptionValue) ?>"></td>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <template id="line-row-template">
    <tr>
      <td data-label="区分">
        <select name="dc[]" required>
          <option value="debit">借方</option>
          <option value="credit">貸方</option>
        </select>
      </td>
      <td data-label="勘定科目">
        <select name="account_id[]" required>
          <option value="">選択</option>
          <?php foreach ($accounts as $account): ?>
            <?php $categoryLabel = $categoryLabels[$account['category']] ?? $account['category']; ?>
            <option value="<?= esc((string) $account['id']) ?>" data-category="<?= esc($account['category']) ?>"><?= esc($account['code'] . ' ' . $account['name'] . ' [' . $categoryLabel . ']') ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td data-label="増減">
        <span class="change-indicator" style="display:inline-block; min-width:52px; padding:4px 8px; border-radius:9999px; text-align:center; background:#e2e8f0; color:#475569; font-size:0.9rem;">—</span>
      </td>
      <td data-label="金額"><input type="number" min="1" step="1" name="amount[]" required></td>
      <td data-label="明細摘要"><input name="line_description[]"></td>
    </tr>
  </template>

  <div class="grid" style="margin-top:12px;">
    <div class="card">借方合計: <strong id="debit-total">0</strong></div>
    <div class="card">貸方合計: <strong id="credit-total">0</strong></div>
  </div>

  <div class="stack" style="margin-top:14px;">
    <button class="btn" type="submit">更新</button>
    <a class="btn secondary" href="/journal-entries/<?= esc((string) $entry['id']) ?>">詳細へ戻る</a>
  </div>
</form>

<script>
  const table = document.getElementById('lines-table');
  const tableBody = table.querySelector('tbody');
  const debitTotalEl = document.getElementById('debit-total');
  const creditTotalEl = document.getElementById('credit-total');
  const addLineButton = document.getElementById('add-line-button');
  const lineRowTemplate = document.getElementById('line-row-template');

  function getChangeInfo(category, dc) {
    if (!category || !dc) {
      return {
        label: '—',
        background: '#e2e8f0',
        color: '#475569'
      };
    }

    const isIncrease = ['asset', 'expense'].includes(category)
      ? dc === 'debit'
      : dc === 'credit';

    return isIncrease
      ? { label: '増加', background: '#dcfce7', color: '#166534' }
      : { label: '減少', background: '#fee2e2', color: '#991b1b' };
  }

  function updateChangeIndicator(row) {
    const dc = row.querySelector('select[name="dc[]"]')?.value || '';
    const accountSelect = row.querySelector('select[name="account_id[]"]');
    const selectedOption = accountSelect?.options[accountSelect.selectedIndex];
    const category = selectedOption?.dataset.category || '';
    const indicator = row.querySelector('.change-indicator');

    if (!indicator) {
      return;
    }

    const changeInfo = getChangeInfo(category, dc);
    indicator.textContent = changeInfo.label;
    indicator.style.background = changeInfo.background;
    indicator.style.color = changeInfo.color;
  }

  function refreshChangeIndicators() {
    tableBody.querySelectorAll('tr').forEach((row) => updateChangeIndicator(row));
  }

  function recalcTotals() {
    let debit = 0;
    let credit = 0;
    tableBody.querySelectorAll('tr').forEach((row) => {
      const dc = row.querySelector('select[name="dc[]"]').value;
      const amount = Number(row.querySelector('input[name="amount[]"]').value || 0);
      if (dc === 'debit') debit += amount;
      if (dc === 'credit') credit += amount;
    });
    debitTotalEl.textContent = debit.toLocaleString('ja-JP');
    creditTotalEl.textContent = credit.toLocaleString('ja-JP');
    refreshChangeIndicators();
  }

  function addLineRow(line = {}) {
    const fragment = lineRowTemplate.content.cloneNode(true);
    const row = fragment.querySelector('tr');

    row.querySelector('select[name="dc[]"]').value = line.dc || 'debit';
    row.querySelector('select[name="account_id[]"]').value = line.account_id ? String(line.account_id) : '';
    row.querySelector('input[name="amount[]"]').value = line.amount ? String(line.amount) : '';
    row.querySelector('input[name="line_description[]"]').value = line.line_description || '';

    tableBody.appendChild(row);
    updateChangeIndicator(row);
  }

  function addLinePair() {
    addLineRow();
    addLineRow({ dc: 'credit' });
  }

  addLineButton.addEventListener('click', () => addLinePair());
  table.addEventListener('input', recalcTotals);
  table.addEventListener('change', recalcTotals);

  recalcTotals();
</script>
<?= $this->endSection() ?>
