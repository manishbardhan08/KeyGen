<?php

/**
 * KeyGen routes.
 *
 * NOTE: every state-changing action is POST (CSRF-protected).
 * Destructive POSTs (delete/logout) additionally use a *_method_override-free
 * explicit POST route so a bare <img> or link cannot trigger them.
 */
$routes->get('/', 'Home::index');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->post('logout', 'Auth::logout');

$routes->group('owner', ['filter' => 'role:owner'], function ($r) {
    $r->get('dashboard', 'Owner::dashboard');
    $r->get('admins', 'Owner::admins');
    $r->post('admins/create', 'Owner::createAdmin');
    $r->post('admins/delete/(:num)', 'Owner::deleteAdmin/$1');
    $r->post('admins/password/(:num)', 'Owner::changeAdminPassword/$1');
    $r->get('profile', 'Owner::profile');
    $r->post('profile/password', 'Owner::changePassword');
});

$routes->group('admin', ['filter' => 'role:admin'], function ($r) {
    $r->get('dashboard', 'Admin::dashboard');
    $r->get('sellers', 'Admin::sellers');
    $r->post('sellers/create', 'Admin::createSeller');
    $r->post('sellers/delete/(:num)', 'Admin::deleteSeller/$1');
    $r->get('sellers/edit/(:num)', 'Admin::editSeller/$1');
    $r->post('sellers/update/(:num)', 'Admin::updateSeller/$1');
    $r->post('sellers/recharge/(:num)', 'Admin::rechargeSeller/$1');
    $r->post('sellers/extend/(:num)', 'Admin::extendSeller/$1');
    $r->post('sellers/ban/(:num)', 'Admin::banSeller/$1');
    $r->get('keys', 'Admin::keys');
    $r->post('keys/generate', 'Admin::generateKeys');
    $r->post('keys/extend', 'Admin::extendAll');
    $r->post('keys/extend/(:num)', 'Admin::extendKey/$1');
    $r->post('keys/reset-hwid', 'Admin::resetHwid');
    $r->get('keys/list', 'Admin::allKeys');
    $r->get('keys/export', 'Admin::exportKeys');
    $r->post('keys/delete-expired', 'Admin::deleteExpiredKeys');
    $r->post('keys/purge', 'Admin::purgeKeys');
    $r->post('keys/delete/(:num)', 'Admin::deleteKey/$1');
    $r->post('keys/toggle/(:num)', 'Admin::toggleKeyStatus/$1');
    $r->get('plans', 'Admin::plans');
    $r->post('plans/save', 'Admin::savePlan');
    $r->post('plans/delete/(:num)', 'Admin::deletePlan/$1');
    $r->get('levels', 'Admin::levels');
    $r->post('levels/save', 'Admin::saveLevel');
    $r->post('levels/delete/(:num)', 'Admin::deleteLevel/$1');
    $r->get('profile', 'Admin::profile');
    $r->post('profile/password', 'Admin::changePassword');
    $r->post('profile/update', 'Admin::updateProfile');
    $r->post('profile/product', 'Admin::updateProduct');
    $r->post('profile/software', 'Admin::updateSoftware');
    $r->post('profile/regen', 'Admin::regenApiKey');
    $r->get('docs', 'Admin::docs');
});

$routes->group('seller', ['filter' => 'role:seller'], function ($r) {
    $r->get('dashboard', 'Seller::dashboard');
    $r->get('keys', 'Seller::keys');
    $r->post('keys/generate', 'Seller::generateKeys');
    $r->post('keys/reset-hwid', 'Seller::resetHwid');
    $r->get('keys/list', 'Seller::allKeys');
    $r->get('keys/export', 'Seller::exportKeys');
    $r->post('keys/toggle/(:num)', 'Seller::toggleKeyStatus/$1');
    $r->post('keys/extend/(:num)', 'Seller::extendKey/$1');
    $r->post('keys/delete/(:num)', 'Seller::deleteKey/$1');
    $r->get('profile', 'Seller::profile');
    $r->post('profile/password', 'Seller::changePassword');
});

$routes->group('api/v1', ['filter' => 'apiguard'], function ($r) {
    $r->post('auth', 'Api::auth');
    $r->post('validate', 'Api::validateToken');
    $r->post('logout', 'Api::logout');
});