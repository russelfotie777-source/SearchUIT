<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Référence courte d'une fiche (« K7Q2M9 »), lisible au guichet :
 * ni I, ni O, ni 0, ni 1, qu'on confond à l'œil.
 */
final class Reference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    public const LONGUEUR = 6;

    /**
     * @param array<string, true> $existantes références déjà prises
     */
    public static function nouvelle(array &$existantes): string
    {
        $max = strlen(self::ALPHABET) - 1;
        do {
            $reference = '';
            for ($i = 0; $i < self::LONGUEUR; $i++) {
                $reference .= self::ALPHABET[random_int(0, $max)];
            }
        } while (isset($existantes[$reference]));

        $existantes[$reference] = true;
        return $reference;
    }

    public static function valide(string $reference): bool
    {
        return (bool) preg_match('/^[' . self::ALPHABET . ']{' . self::LONGUEUR . '}$/', $reference);
    }
}
