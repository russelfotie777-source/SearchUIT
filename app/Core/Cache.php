<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Petit cache sur disque (storage/cache) pour les calculs refaits à chaque visite.
 * Chaque valeur est liée à une version : quand la liste change, le cache se renouvelle.
 */
final class Cache
{
    /**
     * @template T
     * @param callable(): T $calcul
     * @return T
     */
    public static function souvenir(string $cle, string $version, callable $calcul): mixed
    {
        $fichier = RACINE . '/storage/cache/' . preg_replace('/[^a-z0-9-]/', '', $cle) . '.cache';

        if (is_file($fichier)) {
            $contenu = @unserialize((string) file_get_contents($fichier), ['allowed_classes' => false]);
            if (is_array($contenu) && ($contenu['version'] ?? null) === $version) {
                return $contenu['valeur'];
            }
        }

        $valeur = $calcul();
        @file_put_contents($fichier, serialize(['version' => $version, 'valeur' => $valeur]), LOCK_EX);
        return $valeur;
    }

    public static function vider(): void
    {
        foreach (glob(RACINE . '/storage/cache/*.cache') ?: [] as $fichier) {
            @unlink($fichier);
        }
    }
}
