<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<?= view('users/_form', [
    'action' => '/users/' . (int) ($user['id'] ?? 0) . '/update',
    'user' => $user,
    'roles' => $roles,
]) ?>
<?= $this->endSection() ?>
