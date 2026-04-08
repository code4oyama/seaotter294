<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::attemptLogin');
$routes->get('/logout', 'Auth::logout', ['filter' => 'auth']);
$routes->post('/logout', 'Auth::logout', ['filter' => 'auth']);

$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');

    $routes->get('/accounts', 'Accounts::index');
    $routes->get('/accounts/new', 'Accounts::new', ['filter' => 'permission:edit']);
    $routes->post('/accounts', 'Accounts::create', ['filter' => 'permission:edit']);
    $routes->get('/accounts/(:num)/edit', 'Accounts::edit/$1', ['filter' => 'permission:edit']);
    $routes->post('/accounts/(:num)/update', 'Accounts::update/$1', ['filter' => 'permission:edit']);
    $routes->post('/accounts/(:num)/delete', 'Accounts::delete/$1', ['filter' => 'permission:delete']);

    $routes->get('/cashbook', 'Cashbook::index');
    $routes->get('/cashbook/pdf', 'Cashbook::pdf');
    $routes->get('/cashbook/new', 'Cashbook::new', ['filter' => 'permission:edit']);
    $routes->post('/cashbook', 'Cashbook::create', ['filter' => 'permission:edit']);
    $routes->get('/cashbook/(:num)/edit', 'Cashbook::edit/$1', ['filter' => 'permission:edit']);
    $routes->post('/cashbook/(:num)/update', 'Cashbook::update/$1', ['filter' => 'permission:edit']);
    $routes->post('/cashbook/(:num)/delete', 'Cashbook::delete/$1', ['filter' => 'permission:delete']);
    $routes->post('/cashbook/(:num)/journalize', 'Cashbook::journalize/$1', ['filter' => 'permission:edit']);
    $routes->post('/cashbook/ai-suggest', 'Cashbook::aiSuggest', ['filter' => 'permission:edit']);

    $routes->get('/journal-entries', 'JournalEntries::index');
    $routes->get('/journal-entries/new', 'JournalEntries::new', ['filter' => 'permission:edit']);
    $routes->get('/journal-entries/(:num)/edit', 'JournalEntries::edit/$1', ['filter' => 'permission:edit']);
    $routes->post('/journal-entries/(:num)/update', 'JournalEntries::update/$1', ['filter' => 'permission:edit']);
    $routes->post('/journal-entries/(:num)/delete', 'JournalEntries::delete/$1', ['filter' => 'permission:delete']);
    $routes->post('/journal-entries/ai-suggest', 'JournalEntries::aiSuggest', ['filter' => 'permission:edit']);
    $routes->post('/journal-entries', 'JournalEntries::create', ['filter' => 'permission:edit']);
    $routes->get('/journal-entries/(:num)', 'JournalEntries::show/$1');

    $routes->get('/fiscal-periods', 'FiscalPeriods::index');
    $routes->get('/fiscal-periods/(:num)/edit', 'FiscalPeriods::edit/$1', ['filter' => 'permission:edit']);
    $routes->post('/fiscal-periods/(:num)/update', 'FiscalPeriods::update/$1', ['filter' => 'permission:edit']);

    $routes->get('/reports', 'Reports::index');
    $routes->get('/reports/balance-sheet/(:num)/pdf', 'Reports::balanceSheetPdf/$1');
    $routes->get('/reports/profit-loss/(:num)/pdf', 'Reports::profitLossPdf/$1');

    $routes->get('/users', 'Users::index', ['filter' => 'permission:admin']);
    $routes->get('/users/new', 'Users::new', ['filter' => 'permission:admin']);
    $routes->post('/users', 'Users::create', ['filter' => 'permission:admin']);
    $routes->get('/users/(:num)/edit', 'Users::edit/$1', ['filter' => 'permission:admin']);
    $routes->post('/users/(:num)/update', 'Users::update/$1', ['filter' => 'permission:admin']);
    $routes->post('/users/(:num)/delete', 'Users::delete/$1', ['filter' => 'permission:admin']);
});
