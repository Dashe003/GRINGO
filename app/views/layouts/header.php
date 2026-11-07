<?php $user = current_user(); $theme = $user['theme'] ?? 'dark'; ?>
<!doctype html>
<html lang="fr" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#11110f">
  <title><?= e($title ?? 'GRINGO') ?> · GRINGO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/public/assets/app.css?v=<?= (int) filemtime(APP_ROOT.'/public/assets/app.css') ?>">
</head>
<body>
<?php if ($user): ?>
<div class="app-shell">
  <aside class="sidebar">
    <a class="brand" href="<?= e(url('dashboard')) ?>"><span class="brand-mark">G</span><span>GRINGO<small>MY WARDROBE. MY STYLE.</small></span></a>
    <div class="side-label">ESPACE PERSONNEL</div>
    <?php
      $navCurrentRoute = (string) ($_GET['page'] ?? 'dashboard');
      $navGroups = [
        ['label' => 'Mon dressing', 'icon' => '◇', 'items' => [
          ['page' => 'clothes', 'label' => 'Ma garde-robe', 'icon' => '▧', 'routes' => ['clothes', 'clothes-create', 'clothes-show', 'clothes-edit', 'clothes-delete', 'clothes-restore', 'clothing-photo-delete', 'clothing-favorite', 'clothing-rate']],
          ['page' => 'outfits', 'label' => 'Mes tenues', 'icon' => '◈', 'routes' => ['outfits', 'outfits-create', 'outfits-show', 'outfits-edit', 'outfits-delete', 'outfits-restore', 'outfits-duplicate', 'outfits-wear', 'outfits-favorite', 'outfits-rate']],
          ['page' => 'favorites', 'label' => 'Favoris', 'icon' => '♡', 'routes' => ['favorites']],
          ['page' => 'collections', 'label' => 'Collections', 'icon' => '▤', 'routes' => ['collections', 'collection-show', 'collection-save', 'collection-delete']],
          ['page' => 'wishlist', 'label' => 'Envies', 'icon' => '☆', 'routes' => ['wishlist', 'wishlist-save', 'wishlist-status', 'wishlist-delete', 'wishlist-convert']],
        ]],
        ['label' => 'Organisation', 'icon' => '▦', 'items' => [
          ['page' => 'generator', 'label' => 'Suggestions', 'icon' => '✳', 'routes' => ['generator', 'generator-save']],
          ['page' => 'calendar', 'label' => 'Calendrier', 'icon' => '▦', 'routes' => ['calendar', 'calendar-save', 'calendar-autosave', 'calendar-status', 'calendar-delete']],
          ['page' => 'laundry', 'label' => 'Linge', 'icon' => '↻', 'routes' => ['laundry', 'laundry-status']],
          ['page' => 'trips', 'label' => 'Valises', 'icon' => '⌑', 'routes' => ['trips', 'trip-show', 'trip-save', 'trip-packed', 'trip-delete']],
        ]],
        ['label' => 'Suivi & style', 'icon' => '◉', 'items' => [
          ['page' => 'colors', 'label' => 'Couleurs', 'icon' => '◌', 'routes' => ['colors']],
          ['page' => 'budget', 'label' => 'Budget', 'icon' => '◉', 'routes' => ['budget', 'purchase-save', 'purchase-delete', 'budget-save', 'exchange-save']],
          ['page' => 'statistics', 'label' => 'Statistiques', 'icon' => '⌁', 'routes' => ['statistics']],
        ]],
        ['label' => 'Mon compte', 'icon' => '⚙', 'items' => [
          ['page' => 'notifications', 'label' => 'Notifications', 'icon' => '♧', 'routes' => ['notifications', 'notification-read']],
          ['page' => 'profile', 'label' => 'Mon profil', 'icon' => '◉', 'routes' => ['profile']],
          ['page' => 'settings', 'label' => 'Paramètres', 'icon' => '⚙', 'routes' => ['settings', 'category-save', 'category-status', 'subcategory-save', 'subcategory-delete', 'category-edit', 'category-delete', 'subcategory-edit', 'label-save', 'label-delete', 'account-delete']],
          ['page' => 'backup', 'label' => 'Sauvegarde', 'icon' => '⇧', 'routes' => ['backup', 'backup-create', 'backup-download', 'backup-restore']],
        ]],
      ];
    ?>
    <nav class="side-nav" aria-label="Navigation principale">
      <a class="side-nav-home <?= $navCurrentRoute === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>" <?= $navCurrentRoute === 'dashboard' ? 'aria-current="page"' : '' ?>><span>◫</span>Vue d’ensemble</a>
      <?php foreach ($navGroups as $navGroup): $navGroupActive = false; foreach ($navGroup['items'] as $navEntry) { if (in_array($navCurrentRoute, $navEntry['routes'], true)) { $navGroupActive = true; break; } } ?>
        <details class="side-nav-group" <?= $navGroupActive ? 'open' : '' ?>>
          <summary><span class="nav-group-icon"><?= e($navGroup['icon']) ?></span><span><?= e($navGroup['label']) ?></span><span class="nav-group-chevron" aria-hidden="true">⌄</span></summary>
          <div class="side-nav-links">
            <?php foreach ($navGroup['items'] as $navEntry): $navEntryActive = in_array($navCurrentRoute, $navEntry['routes'], true); ?>
              <a class="<?= $navEntryActive ? 'active' : '' ?>" href="<?= e(url($navEntry['page'])) ?>" <?= $navEntryActive ? 'aria-current="page"' : '' ?>><span><?= e($navEntry['icon']) ?></span><?= e($navEntry['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom"><div class="profile-chip"><span class="avatar"><?php if(!empty($user['avatar_path'])): ?><img src="<?= e(APP_BASE_URL.'/'.$user['avatar_path']) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?><?php endif; ?></span><span><?= e($user['full_name']) ?><small>@<?= e($user['username']) ?></small></span></div>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="logout-link" type="submit">Déconnexion <span>↗</span></button></form>
    </div>
  </aside>
  <main class="main-area"><header class="topbar"><div class="breadcrumb">ESPACE PERSONNEL <span>/</span> <?= e(mb_strtoupper($title ?? 'GRINGO')) ?></div><div class="top-actions"><form class="global-search" method="get" action="<?= e(APP_BASE_URL) ?>/index.php"><input type="hidden" name="page" value="search"><input name="q" placeholder="Rechercher…" aria-label="Recherche globale"><button aria-label="Rechercher">⌕</button></form><a class="notification-link" href="<?= e(url('notifications')) ?>" aria-label="Notifications">♧</a><span class="today-label"><?= e(date('d.m.Y')) ?></span><a class="top-avatar" href="<?= e(url('profile')) ?>" aria-label="Mon profil"><?php if(!empty($user['avatar_path'])): ?><img src="<?= e(APP_BASE_URL.'/'.$user['avatar_path']) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?><?php endif; ?></a></div></header><div class="page-content">
<?php else: ?>
<main class="auth-shell">
<?php endif; ?>
<?php if ($message = flash('success')): ?><div class="flash success"><?= e($message) ?></div><?php endif; ?>
<?php if ($message = flash('error')): ?><div class="flash error"><?= e($message) ?></div><?php endif; ?>
