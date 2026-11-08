<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Construit les adresses du site, qu'il soit installé à la racine du domaine
 * (iutdouala.example/) ou dans un sous-dossier (localhost/projetiut/).
 */
final class Url
{
    private static ?string $base = null;

    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        $configuree = Config::get('url_base');
        if (is_string($configuree) && $configuree !== '') {
            return self::$base = rtrim((string) parse_url($configuree, PHP_URL_PATH), '/');
        }

        // Le contrôleur frontal vit dans public/. Quand le .htaccess racine
        // redirige vers public/, l'adresse visible ne contient pas « /public ».
        $script = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
        $script = rtrim($script, '/');
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        if ($script !== '' && ($uri === $script || str_starts_with($uri, $script . '/'))) {
            return self::$base = $script;
        }
        if (str_ends_with($script, '/public')) {
            return self::$base = substr($script, 0, -strlen('/public'));
        }
        return self::$base = $script;
    }

    public static function vers(string $chemin = '/', array $query = []): string
    {
        $adresse = self::base() . '/' . ltrim($chemin, '/');
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        if ($query !== []) {
            $adresse .= '?' . http_build_query($query);
        }
        return $adresse;
    }

    /**
     * Adresse complète avec le domaine (pour les QR codes et les PDF).
     */
    public static function absolue(string $chemin = '/'): string
    {
        $configuree = Config::get('url_base');
        if (is_string($configuree) && $configuree !== '') {
            $origine = rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', $configuree), '/');
        } else {
            $https = ($_SERVER['HTTPS'] ?? '') === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            $hote = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $origine = ($https ? 'https' : 'http') . '://' . $hote;
        }
        return $origine . self::vers($chemin);
    }

    public static function asset(string $chemin): string
    {
        $fichier = RACINE . '/public/assets/' . ltrim($chemin, '/');
        $version = is_file($fichier) ? (string) filemtime($fichier) : '1';
        return self::vers('assets/' . ltrim($chemin, '/')) . '?v=' . $version;
    }
}
