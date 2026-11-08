<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Outils de texte partagés : retrait des accents et forme de recherche.
 */
final class Texte
{
    private const ACCENTS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
        'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I',
        'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ý' => 'Y', 'Ÿ' => 'Y', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe', 'ù' => 'u', 'ú' => 'u',
        'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    ];

    public static function sansAccents(string $texte): string
    {
        return strtr($texte, self::ACCENTS);
    }

    /**
     * Forme utilisée pour comparer les noms : majuscules, sans accents,
     * ponctuation remplacée par des espaces, espaces simples.
     * « Ngo Ndjock-Épée » → « NGO NDJOCK EPEE ».
     */
    public static function pourRecherche(string $texte): string
    {
        $texte = mb_strtoupper(self::sansAccents($texte));
        $texte = (string) preg_replace('/[^A-Z0-9]+/', ' ', $texte);
        return trim($texte);
    }

    /**
     * Espaces multiples et insécables ramenés à une espace simple.
     */
    public static function espaces(string $texte): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $texte));
    }
}
