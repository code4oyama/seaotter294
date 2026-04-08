<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>
<?= view('users/_form', [
    'action' => '/users',
    'roles' => $roles,
]) ?>
<?= $this->endSection() ?>
