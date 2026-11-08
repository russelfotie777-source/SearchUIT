<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration lue depuis config/config.php.
 * Tant que ce fichier n'existe pas, le site redirige vers l'installation.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $valeurs = [];
    private static bool $presente = false;
    private static string $chemin = '';

    public static function charger(string $chemin): void
    {
        self::$chemin = $chemin;
        if (is_file($chemin)) {
            $valeurs = require $chemin;
            self::$valeurs = is_array($valeurs) ? $valeurs : [];
            self::$presente = true;
        }
    }

    public static function presente(): bool
    {
        return self::$presente;
    }

    public static function chemin(): string
    {
        return self::$chemin;
    }

    /**
     * Lecture avec notation pointée : Config::get('db.host').
     */
    public static function get(string $cle, mixed $defaut = null): mixed
    {
        $valeur = self::$valeurs;
        foreach (explode('.', $cle) as $morceau) {
            if (!is_array($valeur) || !array_key_exists($morceau, $valeur)) {
                return $defaut;
            }
            $valeur = $valeur[$morceau];
        }
        return $valeur;
    }

    /**
     * Écrit le fichier de configuration (utilisé par l'installation).
     *
     * @param array<string, mixed> $valeurs
     */
    public static function ecrire(array $valeurs): bool
    {
        $contenu = "<?php\n\n// Généré par l'installation le " . date('d/m/Y à H:i') . ".\n"
            . "// Ce fichier contient les accès à la base de données : ne le partagez pas.\n\n"
            . 'return ' . var_export($valeurs, true) . ";\n";

        $ok = file_put_contents(self::$chemin, $contenu, LOCK_EX) !== false;
        if ($ok) {
            self::$valeurs = $valeurs;
            self::$presente = true;
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(self::$chemin, true);
            }
        }
        return $ok;
    }
}
