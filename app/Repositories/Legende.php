<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Effets;

/**
 * Légende des codes d'effets : libellés par défaut, complétés ou corrigés
 * par la scolarité depuis les réglages (paramètre « legende »).
 */
final class Legende
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function complete(): array
    {
        $legende = Effets::LEGENDE;
        foreach (Parametres::json('legende') ?? [] as $code => $libelles) {
            if (is_string($code) && is_array($libelles) && isset($libelles[0], $libelles[1])) {
                $legende[$code] = [(string) $libelles[0], (string) $libelles[1]];
            }
        }
        return $legende;
    }

    /**
     * Codes réellement présents sur la liste, dans l'ordre de la légende.
     *
     * @param array<string, int> $presents code → nombre d'étudiants
     * @return list<array{code: string, court: string, complet: string}>
     */
    public static function utilisee(array $presents): array
    {
        $lignes = [];
        foreach (self::complete() as $code => [$court, $complet]) {
            if (($presents[$code] ?? 0) > 0 && $code !== 'NP') {
                $lignes[] = ['code' => $code, 'court' => $court, 'complet' => $complet];
            }
        }
        return $lignes;
    }
}
