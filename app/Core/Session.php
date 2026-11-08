<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_name('searchiut');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (Url::base() ?: '') . '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        // Sur un mutualisé, le dossier de sessions partagé est parfois nettoyé
        // trop tôt : on garde nos sessions dans storage/ quand c'est possible.
        $dossier = RACINE . '/storage/cache/sessions';
        if (is_dir($dossier) || @mkdir($dossier, 0700, true)) {
            session_save_path($dossier);
        }

        session_start();
    }

    public static function get(string $cle, mixed $defaut = null): mixed
    {
        return $_SESSION[$cle] ?? $defaut;
    }

    public static function set(string $cle, mixed $valeur): void
    {
        $_SESSION[$cle] = $valeur;
    }

    public static function oublier(string $cle): void
    {
        unset($_SESSION[$cle]);
    }

    public static function regenerer(): void
    {
        session_regenerate_id(true);
    }

    public static function detruire(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }

    /**
     * Message affiché une seule fois, à la page suivante.
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function messages(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}
