<?php

use App\Database\Seeds\DemoJournalEntriesSeeder;
use App\Models\AccountModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AccountsFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoJournalEntriesSeeder::class;

    public function testIndexAndNewPagesRender(): void
    {
        $index = $this->get('/accounts');

        $index->assertStatus(200);
        $index->assertSee('勘定科目一覧');
        $index->assertSee('資産');
        $index->assertSee('編集');
        $index->assertSee('削除');

        $new = $this->get('/accounts/new');

        $new->assertStatus(200);
        $new->assertSee('勘定科目登録');
        $new->assertSee('正味財産');
        $new->assertSee('費用');
    }

    public function testCreateStoresNewAccountWithInactiveDefault(): void
    {
        $response = $this->post('/accounts', [
            'code' => '1999',
            'name' => 'テスト用資産科目',
            'category' => 'asset',
        ]);

        $response->assertStatus(302);
        $this->seeInDatabase('accounts', [
            'code' => '1999',
            'name' => 'テスト用資産科目',
            'category' => 'asset',
            'is_active' => 0,
        ]);
    }

    public function testCreateRejectsMissingRequiredFields(): void
    {
        $response = $this->post('/accounts', [
            'code' => '',
            'name' => '必須エラー確認用',
            'category' => '',
        ]);

        $response->assertStatus(302);
        $this->dontSeeInDatabase('accounts', [
            'name' => '必須エラー確認用',
        ]);
    }

    public function testCreateRejectsInvalidCategory(): void
    {
        $response = $this->post('/accounts', [
            'code' => '1998',
            'name' => '不正区分科目',
            'category' => 'invalid-category',
            'is_active' => '1',
        ]);

        $response->assertStatus(302);
        $this->dontSeeInDatabase('accounts', [
            'code' => '1998',
        ]);
    }

    public function testCreateRejectsDuplicateCode(): void
    {
        $response = $this->post('/accounts', [
            'code' => '1110',
            'name' => '重複コード科目',
            'category' => 'asset',
            'is_active' => '1',
        ]);

        $response->assertStatus(302);
        $this->dontSeeInDatabase('accounts', [
            'name' => '重複コード科目',
        ]);
    }

    public function testEditPageAndUpdateWork(): void
    {
        $account = (new AccountModel())->where('code', '4110')->first();
        $this->assertNotNull($account);

        $editPage = $this->get('/accounts/' . $account['id'] . '/edit');
        $editPage->assertStatus(200);
        $editPage->assertSee('勘定科目編集');
        $editPage->assertSee('受取会費');

        $update = $this->post('/accounts/' . $account['id'] . '/update', [
            'code' => '4110',
            'name' => '受取会費収益',
            'category' => 'revenue',
            'is_active' => '1',
        ]);

        $update->assertStatus(302);
        $this->seeInDatabase('accounts', [
            'id' => $account['id'],
            'name' => '受取会費収益',
            'category' => 'revenue',
        ]);
    }

    public function testDeleteRemovesUnusedAccount(): void
    {
        $account = (new AccountModel())->where('code', '3120')->first();
        $this->assertNotNull($account);

        $response = $this->post('/accounts/' . $account['id'] . '/delete');

        $response->assertStatus(302);
        $this->dontSeeInDatabase('accounts', ['id' => $account['id']]);
    }

    public function testDeleteBlocksAccountUsedInJournalLines(): void
    {
        $account = (new AccountModel())->where('code', '1120')->first();
        $this->assertNotNull($account);

        $response = $this->post('/accounts/' . $account['id'] . '/delete');

        $response->assertStatus(302);
        $this->seeInDatabase('accounts', ['id' => $account['id']]);
    }

    public function testEditThrowsNotFoundWhenAccountIsMissing(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionMessage('勘定科目が見つかりません。');

        $this->get('/accounts/999999/edit');
    }
}
