<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$oldDcs = array_values((array) old('dc'));
$oldAccountIds = array_values((array) old('account_id'));
$oldAmounts = array_values((array) old('amount'));
$oldLineDescriptions = array_values((array) old('line_description'));
$rowCount = max(2, count($oldDcs), count($oldAccountIds), count($oldAmounts), count($oldLineDescriptions));
$categoryLabels = [
  'asset' => '資産',
  'liability' => '負債',
  'net_asset' => '正味財産',
  'revenue' => '収益',
  'expense' => '費用',
];
?>
<h1><?= esc($title) ?></h1>

<div class="card" style="margin-bottom:16px; background:#eff6ff; border-color:#bfdbfe;">
  <strong>AIで仕訳候補を作成</strong>
  <div style="margin-top:6px; font-size:0.95rem; line-height:1.6;">
    取引内容を自然文で入力すると、ChatGPT API が摘要と借方・貸方の候補を提案します。
    提案内容は自動でフォームへ反映されるので、確認後にそのまま登録できます。
  </div>
  <label for="ai-transaction-text">AIに伝える取引内容</label>
  <textarea id="ai-transaction-text" name="ai_request_text" form="entry-form" rows="4" placeholder="例: 4月分の会費5,000円を現金で受け取った。摘要は『4月会費』。"><?= esc(old('ai_request_text')) ?></textarea>
  <div class="stack" style="margin-top:10px; align-items:center;">
    <button class="btn" type="button" id="ai-suggest-button" <?= ! $aiEnabled ? 'disabled' : '' ?>>AIで提案</button>
    <span id="ai-status" style="font-size:0.92rem; color:#334155;">
      <?= $aiEnabled ? '内容を入力してボタンを押してください。' : '.env に openai.apiKey を設定すると利用できます。' ?>
    </span>
  </div>
  <div id="ai-note" style="display:none; margin-top:10px; padding:10px 12px; border-radius:8px; background:#f8fafc; color:#334155;"></div>
</div>

<form method="post" action="/journal-entries" id="entry-form">
  <?= csrf_field() ?>

  <label>会計期間</label>
  <select name="fiscal_period_id" required>
    <option value="">選択してください</option>
    <?php foreach ($periods as $period): ?>
      <option value="<?= esc((string) $period['id']) ?>" <?= old('fiscal_period_id') == $period['id'] ? 'selected' : '' ?>>
        <?= esc($period['name']) ?> (<?= esc($period['start_date']) ?> - <?= esc($period['end_date']) ?>)
      </option>
    <?php endforeach; ?>
  </select>

  <label>伝票番号</label>
  <input name="voucher_number" value="<?= esc(old('voucher_number') ?: $defaultVoucherNumber) ?>" required>

  <label>取引日</label>
  <input type="date" name="entry_date" value="<?= esc(old('entry_date') ?: date('Y-m-d')) ?>" required>

  <label>摘要</label>
  <input name="description" value="<?= esc(old('description')) ?>" required>

  <div class="card" style="margin-top:14px; background:#f0f9ff; border-color:#bae6fd;">
    <strong>借方・貸方の見方</strong>
    <div style="margin-top:6px; font-size:0.95rem; line-height:1.6;">
      借方は仕訳の左側、貸方は右側を表します。入金・出金の意味ではなく、
      科目の増減を左右に振り分けて記録します。
      増減の基本は「資産・費用は借方で増、貸方で減」「負債・純資産・収益は貸方で増、借方で減」です。
      例: 現金で会費を受け取った場合は「借方: 現金 / 貸方: 受取会費」です。
    </div>
  </div>

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
        $dcValue = $oldDcs[$i] ?? ($i === 1 ? 'credit' : 'debit');
        $accountIdValue = $oldAccountIds[$i] ?? '';
        $amountValue = $oldAmounts[$i] ?? '';
        $lineDescriptionValue = $oldLineDescriptions[$i] ?? '';
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
    <button class="btn" type="submit">登録</button>
    <a class="btn secondary" href="/journal-entries">戻る</a>
  </div>
</form>

<script>
  const table = document.getElementById('lines-table');
  const tableBody = table.querySelector('tbody');
  const debitTotalEl = document.getElementById('debit-total');
  const creditTotalEl = document.getElementById('credit-total');
  const addLineButton = document.getElementById('add-line-button');
  const lineRowTemplate = document.getElementById('line-row-template');
  const aiSuggestButton = document.getElementById('ai-suggest-button');
  const aiTransactionText = document.getElementById('ai-transaction-text');
  const aiStatusEl = document.getElementById('ai-status');
  const aiNoteEl = document.getElementById('ai-note');
  const entryDateInput = document.querySelector('input[name="entry_date"]');
  const descriptionInput = document.querySelector('input[name="description"]');

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

  function ensureLineCount(count) {
    while (tableBody.querySelectorAll('tr').length < count) {
      addLineRow();
    }
  }

  function addLinePair() {
    addLineRow();
    addLineRow({ dc: 'credit' });
  }

  function fillSuggestion(suggestion) {
    if (suggestion.entry_date) {
      entryDateInput.value = suggestion.entry_date;
    }

    if (suggestion.description) {
      descriptionInput.value = suggestion.description;
    }

    const lines = Array.isArray(suggestion.lines) ? suggestion.lines : [];
    ensureLineCount(Math.max(2, lines.length));

    tableBody.querySelectorAll('tr').forEach((row, index) => {
      const line = lines[index] || {};
      row.querySelector('select[name="dc[]"]').value = line.dc || 'debit';
      row.querySelector('select[name="account_id[]"]').value = line.account_id ? String(line.account_id) : '';
      row.querySelector('input[name="amount[]"]').value = line.amount ? String(line.amount) : '';
      row.querySelector('input[name="line_description[]"]').value = line.line_description || '';
    });

    if (suggestion.note) {
      aiNoteEl.style.display = 'block';
      aiNoteEl.textContent = 'AIメモ: ' + suggestion.note;
    } else {
      aiNoteEl.style.display = 'none';
      aiNoteEl.textContent = '';
    }

    recalcTotals();
  }

  async function requestAiSuggestion() {
    const transactionText = aiTransactionText.value.trim();
    if (!transactionText) {
      aiStatusEl.textContent = '取引内容を入力してください。';
      aiStatusEl.style.color = '#b45309';
      return;
    }

    aiSuggestButton.disabled = true;
    aiStatusEl.textContent = 'AIが仕訳候補を作成しています...';
    aiStatusEl.style.color = '#334155';

    const formData = new FormData();
    formData.append('transaction_text', transactionText);
    formData.append('entry_date', entryDateInput.value || '');

    try {
      const response = await fetch('/journal-entries/ai-suggest', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });

      let data = {};
      try {
        data = await response.json();
      } catch (error) {
        data = {};
      }

      if (!response.ok || data.status !== 'ok') {
        throw new Error(data.message || 'AI提案の取得に失敗しました。');
      }

      fillSuggestion(data.suggestion || {});
      aiStatusEl.textContent = '提案を反映しました。内容を確認してから登録してください。';
      aiStatusEl.style.color = '#166534';
    } catch (error) {
      aiStatusEl.textContent = error.message || 'AI提案の取得に失敗しました。';
      aiStatusEl.style.color = '#991b1b';
    } finally {
      aiSuggestButton.disabled = <?= $aiEnabled ? 'false' : 'true' ?>;
    }
  }

  addLineButton.addEventListener('click', () => addLinePair());
  table.addEventListener('input', recalcTotals);
  table.addEventListener('change', recalcTotals);

  if (<?= $aiEnabled ? 'true' : 'false' ?>) {
    aiSuggestButton.addEventListener('click', requestAiSuggestion);
  }

  recalcTotals();
</script>
<?= $this->endSection() ?>
