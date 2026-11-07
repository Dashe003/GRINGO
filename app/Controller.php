<?php
declare(strict_types=1);

abstract class Controller
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function requireAuth(): array
    {
        $user = current_user();
        if (!$user) {
            flash('error', 'Connectez-vous pour continuer.');
            redirect('login');
        }
        return $user;
    }

    protected function requireGuest(): void
    {
        if (current_user()) redirect('dashboard');
    }
}
