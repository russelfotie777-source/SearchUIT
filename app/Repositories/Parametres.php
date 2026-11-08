<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Réglages stockés en base (table parametres), modifiables depuis l'administration.
 */
final class Parametres
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /** Valeurs par défaut : rien n'est inventé, l'administration les complète. */
    public const DEFAUTS = [
        'etablissement' => 'IUT de Douala',
        'service' => 'Service de la scolarité',
        'retrait_lieu' => '',
        'retrait_horaires' => '',
        'retrait_pieces' => '',
        'contact_telephone' => '',
        'contact_email' => '',
        'liste_mise_a_jour' => '',
    ];

    /** @return array<string, string|null> */
    public static function tous(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAUTS;
            try {
                foreach (Database::tous('SELECT cle, valeur FROM parametres') as $ligne) {
                    self::$cache[(string) $ligne['cle']] = $ligne['valeur'];
                }
            } catch (\Throwable) {
                // Base pas encore prête : on garde les valeurs par défaut.
            }
        }
        return self::$cache;
    }

    public static function get(string $cle, string $defaut = ''): string
    {
        $valeur = self::tous()[$cle] ?? null;
        return $valeur === null || $valeur === '' ? $defaut : (string) $valeur;
    }

    public static function set(string $cle, ?string $valeur): void
    {
        Database::requete(
            'INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)',
            [$cle, $valeur],
        );
        if (self::$cache !== null) {
            self::$cache[$cle] = $valeur;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function json(string $cle): ?array
    {
        $brut = self::tous()[$cle] ?? null;
        if (!is_string($brut) || $brut === '') {
            return null;
        }
        $valeur = json_decode($brut, true);
        return is_array($valeur) ? $valeur : null;
    }

    /**
     * Informations communes à toutes les pages publiques.
     *
     * @return array<string, string>
     */
    public static function site(): array
    {
        $tous = self::tous();
        $site = [];
        foreach (self::DEFAUTS as $cle => $defaut) {
            $site[$cle] = (string) ($tous[$cle] ?? $defaut);
        }
        return $site;
    }
}
