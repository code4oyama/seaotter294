<?php
$entry = $entry ?? [];
$isEdit = isset($entry['id']);
$isLocked = (int) ($entry['journal_entry_id'] ?? 0) > 0;
$currentPeriodId = (string) old('fiscal_period_id', (string) ($entry['fiscal_period_id'] ?? ''));
$currentDate = (string) old('transaction_date', (string) ($entry['transaction_date'] ?? date('Y-m-d')));
$currentCashAccountId = (string) old('cash_account_id', (string) ($entry['cash_account_id'] ?? ''));
$currentDirection = (string) old('direction', (string) ($entry['direction'] ?? 'receipt'));
$currentAmount = (string) old('amount', (string) ($entry['amount'] ?? ''));
$currentDescription = (string) old('description', (string) ($entry['description'] ?? ''));
$currentCounterpartId = (string) old('counterpart_account_id', (string) ($entry['counterpart_account_id'] ?? ''));
$currentNotes = (string) old('notes', (string) ($entry['notes'] ?? ''));
?>

<h1><?= esc($title) ?></h1>
<p>現金・預金の入出金を先に記録し、確認後に `仕訳一覧` へ安全に転記できます。</p>

<?php if ($isLocked): ?>
  <div class="card" style="margin-bottom:16px; background:#ecfdf3; border-color:#bbf7d0;">
    この出納帳はすでに仕訳化済みです。監査性を保つため、編集・削除はできません。
    <div style="margin-top:10px;">
      <a class="btn secondary" href="/journal-entries/<?= esc((string) $entry['journal_entry_id']) ?>">仕訳詳細を開く</a>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px; background:#eff6ff; border-color:#bfdbfe;">
  <strong>AIで相手科目候補を提案</strong>
  <div style="margin-top:6px; font-size:0.95rem; line-height:1.6;">
    摘要・金額・出納口座をもとに、AIが相手勘定科目の候補を提案します。
    最終確定前に内容を必ず確認してください。
  </div>
  <label for="ai-transaction-text">AIに伝える内容</label>
  <textarea id="ai-transaction-text" rows="4" <?= $isLocked ? 'disabled' : '' ?>><?= esc(trim($currentDescription . "\n" . $currentNotes)) ?></textarea>
  <div class="stack" style="margin-top:10px; align-items:center;">
    <button class="btn" type="button" id="ai-suggest-button" <?= (! $aiEnabled || $isLocked) ? 'disabled' : '' ?>>AIで提案</button>
    <span id="ai-status" style="font-size:0.92rem; color:#334155;">
      <?= $aiEnabled ? '内容を入力してボタンを押してください。' : '.env に openai.apiKey を設定すると利用できます。' ?>
    </span>
  </div>
  <div id="ai-note" style="display:none; margin-top:10px; padding:10px 12px; border-radius:8px; background:#f8fafc; color:#334155;"></div>
</div>

<form method="post" action="<?= esc($action) ?>">
  <?= csrf_field() ?>

  <div class="grid">
    <div>
      <label>会計期間</label>
      <select name="fiscal_period_id" required <?= $isLocked ? 'disabled' : '' ?>>
        <option value="">選択してください</option>
        <?php foreach ($periods as $period): ?>
          <option value="<?= esc((string) $period['id']) ?>" <?= $currentPeriodId === (string) $period['id'] ? 'selected' : '' ?>>
            <?= esc($period['name']) ?> (<?= esc($period['start_date']) ?> - <?= esc($period['end_date']) ?>)<?= ! empty($period['is_closed']) ? ' / 締め済み' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>取引日</label>
      <input type="date" name="transaction_date" value="<?= esc($currentDate) ?>" required <?= $isLocked ? 'disabled' : '' ?>>
    </div>
  </div>

  <div class="grid">
    <div>
      <label>出納口座</label>
      <select name="cash_account_id" required <?= $isLocked ? 'disabled' : '' ?>>
        <option value="">選択してください</option>
        <?php foreach ($cashAccounts as $account): ?>
          <option value="<?= esc((string) $account['id']) ?>" <?= $currentCashAccountId === (string) $account['id'] ? 'selected' : '' ?>>
            <?= esc($account['code'] . ' ' . $account['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>入出金区分</label>
      <select name="direction" required <?= $isLocked ? 'disabled' : '' ?>>
        <option value="receipt" <?= $currentDirection === 'receipt' ? 'selected' : '' ?>>入金</option>
        <option value="payment" <?= $currentDirection === 'payment' ? 'selected' : '' ?>>出金</option>
      </select>
    </div>
    <div>
      <label>金額（円）</label>
      <input type="number" name="amount" min="1" step="1" value="<?= esc($currentAmount) ?>" required <?= $isLocked ? 'disabled' : '' ?>>
    </div>
  </div>

  <label>摘要</label>
  <input name="description" value="<?= esc($currentDescription) ?>" required <?= $isLocked ? 'readonly' : '' ?>>

  <label>相手勘定科目（任意）</label>
  <select name="counterpart_account_id" <?= $isLocked ? 'disabled' : '' ?>>
    <option value="">あとで選択する</option>
    <?php foreach ($counterpartAccounts as $account): ?>
      <option value="<?= esc((string) $account['id']) ?>" <?= $currentCounterpartId === (string) $account['id'] ? 'selected' : '' ?>>
        <?= esc($account['code'] . ' ' . $account['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div style="margin-top:6px; color:#475569; font-size:0.92rem;">
    相手勘定科目を設定すると、保存後に一覧から `仕訳へ転記` を実行できます。
  </div>

  <label>メモ</label>
  <textarea name="notes" rows="4" <?= $isLocked ? 'readonly' : '' ?>><?= esc($currentNotes) ?></textarea>

  <div class="stack" style="margin-top:14px;">
    <?php if (! $isLocked): ?>
      <button class="btn" type="submit">保存</button>
    <?php endif; ?>
    <a class="btn secondary" href="/cashbook">戻る</a>
    <?php if ($isEdit && ! empty($entry['journal_entry_id'])): ?>
      <a class="btn secondary" href="/journal-entries/<?= esc((string) $entry['journal_entry_id']) ?>">仕訳詳細</a>
    <?php endif; ?>
  </div>
</form>

<script>
  const aiButton = document.getElementById('ai-suggest-button');
  const aiStatusEl = document.getElementById('ai-status');
  const aiNoteEl = document.getElementById('ai-note');
  const aiTransactionText = document.getElementById('ai-transaction-text');
  const descriptionInput = document.querySelector('input[name="description"]');
  const counterpartSelect = document.querySelector('select[name="counterpart_account_id"]');

  async function requestAiSuggestion() {
    const payload = new FormData();
    payload.append('transaction_text', aiTransactionText.value || '');
    payload.append('fiscal_period_id', document.querySelector('select[name="fiscal_period_id"]').value || '');
    payload.append('transaction_date', document.querySelector('input[name="transaction_date"]').value || '');
    payload.append('cash_account_id', document.querySelector('select[name="cash_account_id"]').value || '');
    payload.append('direction', document.querySelector('select[name="direction"]').value || '');
    payload.append('amount', document.querySelector('input[name="amount"]').value || '');
    payload.append('description', descriptionInput.value || '');
    payload.append('notes', document.querySelector('textarea[name="notes"]').value || '');
    payload.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    aiButton.disabled = true;
    aiStatusEl.textContent = 'AIが候補を作成中です…';

    try {
      const response = await fetch('/cashbook/ai-suggest', {
        method: 'POST',
        body: payload,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const data = await response.json();

      if (!response.ok || data.status !== 'ok') {
        throw new Error(data.message || 'AI提案に失敗しました。');
      }

      const suggestion = data.suggestion || {};
      if (suggestion.description) {
        descriptionInput.value = suggestion.description;
      }
      if (suggestion.counterpart_account_id) {
        counterpartSelect.value = String(suggestion.counterpart_account_id);
      }

      aiStatusEl.textContent = '候補を反映しました。保存前に必ず確認してください。';
      aiNoteEl.style.display = 'block';
      aiNoteEl.textContent = suggestion.note || ('候補科目: ' + (suggestion.counterpart_account_label || '未判定'));
    } catch (error) {
      aiStatusEl.textContent = error.message || 'AI提案に失敗しました。';
      aiNoteEl.style.display = 'none';
    } finally {
      aiButton.disabled = false;
    }
  }

  if (aiButton) {
    aiButton.addEventListener('click', requestAiSuggestion);
  }
</script>
