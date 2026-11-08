<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$page = (string) ($_GET['page'] ?? (current_user() ? 'dashboard' : 'login'));
$routes = [
    'login' => ['AuthController', 'login'],
    'register' => ['AuthController', 'register'],
    'logout' => ['AuthController', 'logout'],
    'dashboard' => ['DashboardController', 'index'],
    'clothes' => ['ClothingController', 'index'],
    'clothes-create' => ['ClothingController', 'create'],
    'clothes-show' => ['ClothingController', 'show'],
    'clothes-edit' => ['ClothingController', 'edit'],
    'clothes-delete' => ['ClothingController', 'delete'],
    'clothes-delete-permanent' => ['ClothingController', 'deletePermanently'],
    'clothes-restore' => ['ClothingController', 'restore'],
    'clothing-photo-delete' => ['ClothingController', 'deleteGalleryPhoto'],
    'clothing-favorite' => ['ClothingController', 'favorite'],
    'clothing-rate' => ['ClothingController', 'rate'],
    'outfits' => ['OutfitController', 'index'],
    'outfits-create' => ['OutfitController', 'create'],
    'outfits-show' => ['OutfitController', 'show'],
    'outfits-edit' => ['OutfitController', 'edit'],
    'outfits-delete' => ['OutfitController', 'delete'],
    'outfits-delete-permanent' => ['OutfitController', 'deletePermanently'],
    'outfits-restore' => ['OutfitController', 'restore'],
    'outfits-duplicate' => ['OutfitController', 'duplicate'],
    'outfits-wear' => ['OutfitController', 'wear'],
    'outfits-favorite' => ['OutfitController', 'favorite'],
    'outfits-rate' => ['OutfitController', 'rate'],
    'calendar' => ['CalendarController', 'index'],
    'calendar-save' => ['CalendarController', 'save'],
    'calendar-autosave' => ['CalendarController', 'autoSave'],
    'calendar-status' => ['CalendarController', 'status'],
    'calendar-delete' => ['CalendarController', 'delete'],
    'laundry' => ['LaundryController', 'index'],
    'laundry-status' => ['LaundryController', 'status'],
    'favorites' => ['UtilityController', 'favorites'],
    'collections' => ['CollectionController', 'index'],
    'collection-show' => ['CollectionController', 'show'],
    'collection-save' => ['CollectionController', 'save'],
    'collection-delete' => ['CollectionController', 'delete'],
    'wishlist' => ['WishlistController', 'index'],
    'wishlist-save' => ['WishlistController', 'save'],
    'wishlist-status' => ['WishlistController', 'status'],
    'wishlist-delete' => ['WishlistController', 'delete'],
    'wishlist-convert' => ['WishlistController', 'convert'],
    'budget' => ['BudgetController', 'index'],
    'purchase-save' => ['BudgetController', 'savePurchase'],
    'purchase-delete' => ['BudgetController', 'deletePurchase'],
    'budget-save' => ['BudgetController', 'saveBudget'],
    'exchange-save' => ['BudgetController', 'saveExchange'],
    'trips' => ['TripController', 'index'],
    'trip-show' => ['TripController', 'show'],
    'trip-save' => ['TripController', 'save'],
    'trip-packed' => ['TripController', 'packed'],
    'trip-delete' => ['TripController', 'delete'],
    'generator' => ['UtilityController', 'generator'],
    'generator-save' => ['UtilityController', 'saveSuggestion'],
    'colors' => ['UtilityController', 'colors'],
    'search' => ['UtilityController', 'search'],
    'statistics' => ['UtilityController', 'statistics'],
    'notifications' => ['UtilityController', 'notifications'],
    'notification-read' => ['UtilityController', 'readNotification'],
    'profile' => ['UtilityController', 'profile'],
    'settings' => ['UtilityController', 'settings'],
    'category-save' => ['UtilityController', 'saveCategory'],
    'category-status' => ['UtilityController', 'categoryStatus'],
    'subcategory-save' => ['UtilityController', 'saveSubcategory'],
    'subcategory-delete' => ['UtilityController', 'deleteSubcategory'],
    'category-edit' => ['UtilityController', 'editCategory'],
    'category-delete' => ['UtilityController', 'deleteCategory'],
    'subcategory-edit' => ['UtilityController', 'editSubcategory'],
    'label-save' => ['UtilityController', 'saveLabel'],
    'label-delete' => ['UtilityController', 'deleteLabel'],
    'account-delete' => ['UtilityController', 'deleteAccount'],
    'backup' => ['BackupController', 'index'],
    'backup-create' => ['BackupController', 'create'],
    'backup-download' => ['BackupController', 'download'],
    'backup-restore' => ['BackupController', 'restore'],
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = '404';
    render('errors/404', ['title' => 'Page introuvable']);
    exit;
}

[$controller, $action] = $routes[$page];
(new $controller())->$action();
