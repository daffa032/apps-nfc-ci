<?php

namespace Config;

$routes = Services::routes();

$routes->get('/', 'Home::index');
$routes->get('login', 'Home::index');
$routes->post('loginPost', 'Auth_login::loginPost');
$routes->post('logout', 'Auth_login::logout');

$routes->get('forgot_password', 'ResetPasswordPenggunaController::forgotPassword');
$routes->get('forgot_password/link-success', 'ResetPasswordPenggunaController::resetPasswordSent');
$routes->post('forgot_password/send-reset-link', 'ResetPasswordPenggunaController::sendResetLink');
$routes->get('forgot-password/reset/(:any)', 'ResetPasswordPenggunaController::reset/$1');
$routes->post('forgot-password/reset_password', 'ResetPasswordPenggunaController::updatePassword');

$routes->group('dashboard', ['filter' => 'auth'], static function ($routes): void {
    // Available to every signed-in owner, including an expired one (PRD EX-01).
    $routes->get('/', 'Dashboard::index');
    $routes->get('billing', 'BillingController::index');
    // Checkout and status check stay open for expired owners, so they can renew (PRD EX-01).
    $routes->post('billing/checkout', 'BillingController::checkout');
    $routes->post('billing/check/(:segment)', 'BillingController::check/$1');
    $routes->post('billing/cancel/(:segment)', 'BillingController::cancel/$1');
    $routes->get('billing/receipt/(:segment)', 'BillingController::receipt/$1');
    $routes->get('setting/(:any)', 'ResetPasswordPenggunaController::edit_userPengguna/$1');
    $routes->post('setting/update/(:any)', 'ResetPasswordPenggunaController::update_userPengguna/$1');

    // Card features need an active card; expired owners are sent to billing.
    $routes->group('', ['filter' => 'subscription'], static function ($routes): void {
        $routes->get('personal_info_form', 'ProfileController::addProfileForm');
        $routes->post('add_profile', 'ProfileController::addProfile');
        $routes->get('personal_info_page', 'ProfileController::personalInfo');
        $routes->post('personal_info_page/upload', 'ProfileController::uploadMultipleImages');
        $routes->post('personal_info_page/delete', 'ProfileController::deleteMultipleImages');
        $routes->post('personal_info_page/gallery_layout', 'ProfileController::saveGalleryLayout');
        $routes->post('personal_info_page/language', 'ProfileController::saveCardLanguage');
        $routes->post('personal_info_page/caption', 'ProfileController::updateCaption');
        $routes->post('personal_info_page/save_edit_profile', 'ProfileController::updateProfile');
        $routes->post('personal_info_page/save_social', 'ProfileController::saveSocialMedia');
        $routes->post('personal_info_page/delete_social', 'ProfileController::deleteSocialMedia');
        $routes->post('personal_info_page/brand', 'ProfileController::saveBrandAssets');
        $routes->get('personal_info_page/preview', 'ProfileController::preview');
        $routes->get('templates', 'TemplatesController::templatesPage');
        $routes->get('templates/preview/(:num)', 'TemplatesController::preview/$1');
        $routes->post('templates/update_templates', 'TemplatesController::selectTemplate');
        $routes->get('analytics', 'AnalyticsController::index');
        $routes->get('analytics/data', 'AnalyticsController::data');
    });

    $routes->group('', ['filter' => 'access:Admin,Super Admin'], static function ($routes): void {
        $routes->get('add_user', 'UserController::addUserForm');
        $routes->post('save_user', 'UserController::addUser');
        $routes->get('user_table', 'UserController::listUsers');
        $routes->get('user_table/edit_user/(:num)', 'UserController::editUser/$1');
        $routes->post('user_table/update_user/(:num)', 'UserController::updateUser/$1');
        $routes->post('user_table/delete/(:num)', 'UserController::deleteUser/$1');
        // Payments monitor (PRD AD-01): read and request a verified status check; no "mark paid".
        $routes->get('payments', 'PaymentAdminController::index');
        // Live list (polling JSON) and its background status sync; both before the (:segment) routes.
        $routes->get('payments/live', 'PaymentAdminController::live');
        $routes->post('payments/sync', 'PaymentAdminController::sync');
        $routes->get('payments/(:segment)', 'PaymentAdminController::show/$1');
        $routes->post('payments/(:segment)/check', 'PaymentAdminController::check/$1');
    });

    $routes->group('', ['filter' => 'access:Super Admin'], static function ($routes): void {
        $routes->get('add_admin', 'AdminController::addAdminForm');
        $routes->post('save_admin', 'AdminController::addAdmin');
        $routes->get('admin_table', 'AdminController::listAdmins');
        $routes->get('admin_table/edit_admin/(:num)', 'AdminController::editAdmin/$1');
        $routes->post('admin_table/update_admin/(:num)', 'AdminController::updateAdmin/$1');
        $routes->post('admin_table/delete/(:num)', 'AdminController::deleteAdmin/$1');
        $routes->post('user_table/activate', 'UserController::activateUser');
        $routes->post('user_table/add_activate_user', 'UserController::addActivateUser');
        $routes->post('user_table/delete_access/(:num)', 'UserController::deleteUserAccess/$1');
        $routes->get('activity-logs', 'ActivityLogController::index');
        $routes->get('master/plans', 'MasterDataController::plans');
        $routes->post('master/plans/(:segment)', 'MasterDataController::updatePlan/$1');
        $routes->get('master/templates', 'MasterDataController::templates');
        $routes->post('master/templates/create', 'MasterDataController::createTemplate');
        $routes->post('master/templates/preview', 'MasterDataController::previewTemplate');
        $routes->post('master/templates/limits', 'MasterDataController::updateSlotLimits');
        $routes->post('master/templates/(:num)/rename', 'MasterDataController::renameTemplate/$1');
        $routes->post('master/templates/(:num)', 'MasterDataController::updateTemplate/$1');
        // Resolving a payment flagged for review (PRD AD-01/02): with a reason, audited.
        $routes->post('payments/(:segment)/resolve', 'PaymentAdminController::resolve/$1');
        $routes->post('accounts/(:num)/suspend', 'AccountStatusController::suspend/$1');
        $routes->post('accounts/(:num)/restore', 'AccountStatusController::restore/$1');
    });
});

$routes->post('cards/(:segment)/events', 'CardEventController::store/$1');
$routes->post('payments/midtrans/notification', 'PaymentNotificationController::midtrans');
// Pro share page with a large QR (PRD QR-02); must come before the catch-all card route.
$routes->get('(:segment)/share', 'DigitalCardController::share/$1');
$routes->get('(:any)', 'DigitalCardController::digitalCardProfile/$1');
