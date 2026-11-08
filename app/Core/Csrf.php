<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Jeton anti-falsification pour tous les formulaires et appels POST.
 */
final class Csrf
{
    public static function jeton(): string
    {
        $jeton = Session::get('_csrf');
        if (!is_string($jeton) || strlen($jeton) !== 64) {
            $jeton = bin2hex(random_bytes(32));
            Session::set('_csrf', $jeton);
        }
        return $jeton;
    }

    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::jeton() . '">';
    }

    public static function valide(Request $requete): bool
    {
        $recu = $requete->texte('_csrf') ?: $requete->entete('X-CSRF-Token');
        $attendu = Session::get('_csrf');
        return is_string($attendu) && $recu !== '' && hash_equals($attendu, $recu);
    }
}
