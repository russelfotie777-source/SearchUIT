<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Filière telle qu'écrite sur la liste (« GI2 FA ») et son code de base (« GI »),
 * utilisé pour regrouper les filtres.
 */
final class Filiere
{
    /**
     * @return array{libelle: ?string, code: ?string}
     */
    public static function analyser(mixed $brut): array
    {
        $texte = mb_strtoupper(Texte::espaces((string) $brut));
        if ($texte === '' || preg_match('/^[—–-]+$/u', $texte)) {
            return ['libelle' => null, 'code' => null];
        }

        $code = preg_match('/^([A-Z]+(?:\/[A-Z]+)?)/', Texte::sansAccents($texte), $m) ? $m[1] : $texte;

        return ['libelle' => $texte, 'code' => mb_substr($code, 0, 20)];
    }
}
