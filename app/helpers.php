<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    return APP_BASE_URL . '/index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = APP_ROOT . '/app/views/' . $view . '.php';
    if (!is_file($viewFile)) throw new RuntimeException('Vue introuvable.');
    require APP_ROOT . '/app/views/layouts/header.php';
    require $viewFile;
    require APP_ROOT . '/app/views/layouts/footer.php';
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e($_SESSION['_token'] ?? '') . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['_token'] ?? ''), $sent)) {
        http_response_code(419);
        exit('Votre session a expiré. Rechargez la page puis réessayez.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}
