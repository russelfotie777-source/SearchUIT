<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeInterface;

/**
 * Normalise la colonne « Année » : « 2015-2016 », « 2025/2026 », « 2023- 2024 »,
 * « Juil 2016 », 2002 (nombre), une date Excel, « 2014-2015 (?) »…
 */
final class Annee
{
    private const MOIS = [
        1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
    ];

    /**
     * @return array{libelle: ?string, debut: ?int, incertaine: bool}
     */
    public static function analyser(mixed $brut): array
    {
        if ($brut instanceof DateTimeInterface) {
            return [
                'libelle' => self::MOIS[(int) $brut->format('n')] . ' ' . $brut->format('Y'),
                'debut' => (int) $brut->format('Y'),
                'incertaine' => false,
            ];
        }

        if (is_int($brut) || is_float($brut)) {
            $annee = (int) $brut;
            return ['libelle' => (string) $annee, 'debut' => self::plausible($annee), 'incertaine' => false];
        }

        $texte = Texte::espaces((string) $brut);
        $incertaine = str_contains($texte, '(?)');
        $texte = trim(str_replace('(?)', '', $texte));

        if ($texte === '' || preg_match('/^[—–-]+$/u', $texte)) {
            return ['libelle' => null, 'debut' => null, 'incertaine' => $incertaine];
        }

        // « 2025/2026 », « 2023- 2024 » → « 2025-2026 », « 2023-2024 ».
        $texte = (string) preg_replace('/^(\d{4})\s*[-\/–]\s*(\d{4})$/u', '$1-$2', $texte);

        $debut = preg_match('/(\d{4})/', $texte, $m) ? self::plausible((int) $m[1]) : null;

        return ['libelle' => $texte, 'debut' => $debut, 'incertaine' => $incertaine];
    }

    private static function plausible(int $annee): ?int
    {
        return $annee >= 1950 && $annee <= 2100 ? $annee : null;
    }
}
