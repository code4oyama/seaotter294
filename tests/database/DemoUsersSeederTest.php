<?php

use App\Database\Seeds\DemoUsersSeeder;
use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class DemoUsersSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';
    protected $seed = DemoUsersSeeder::class;

    public function testSeederCreatesTwoUsersForEachRole(): void
    {
        $model = new UserModel();

        $this->assertSame(8, $model->countAllResults());
        $this->assertSame(2, $model->where('role', AuthService::ROLE_VIEWER)->countAllResults());
        $this->assertSame(2, $model->where('role', AuthService::ROLE_EDITOR_LIMITED)->countAllResults());
        $this->assertSame(2, $model->where('role', AuthService::ROLE_EDITOR_FULL)->countAllResults());
        $this->assertSame(2, $model->where('role', AuthService::ROLE_ADMIN)->countAllResults());
    }

    public function testSeederCanBeRerunWithoutDuplicatingUsers(): void
    {
        $this->seed(DemoUsersSeeder::class);

        $count = (new UserModel())->countAllResults();
        $this->assertSame(8, $count);
    }
}
