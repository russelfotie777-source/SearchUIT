<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Règles de la recherche par nom : découpage de la saisie,
 * surlignage des passages trouvés et noms proches en cas de faute de frappe.
 */
final class Recherche
{
    public const LONGUEUR_MIN = 2;
    public const MOTS_MAX = 6;

    /**
     * « Ngo  Ndjock-Épée » → ['NGO', 'NDJOCK', 'EPEE'].
     *
     * @return list<string>
     */
    public static function mots(string $saisie): array
    {
        $texte = Texte::pourRecherche($saisie);
        if ($texte === '') {
            return [];
        }
        $mots = array_values(array_unique(explode(' ', $texte)));
        return array_slice($mots, 0, self::MOTS_MAX);
    }

    /**
     * Une saisie sans espace qui mêle lettres et chiffres (« 21G00123 ») est un matricule.
     */
    public static function estMatricule(string $saisie): bool
    {
        $saisie = trim($saisie);
        return (bool) preg_match('/^(?=.*\d)(?=.*[A-Za-z])[A-Za-z0-9\/._-]{5,40}$/', $saisie);
    }

    /**
     * Découpe le nom affiché en morceaux, surlignés quand un mot de la recherche
     * commence à cet endroit. Les accents et la casse n'empêchent pas la correspondance.
     *
     * @param list<string> $mots
     * @return list<array{0: string, 1: bool}>
     */
    public static function segments(string $nom, array $mots): array
    {
        $caracteres = mb_str_split($nom);
        if ($caracteres === [] || $mots === []) {
            return [[$nom, false]];
        }

        // Forme normalisée du nom, avec pour chaque caractère normalisé
        // l'index du caractère affiché d'origine.
        $normalise = '';
        $origine = [];
        foreach ($caracteres as $i => $caractere) {
            $forme = mb_strtoupper(Texte::sansAccents($caractere));
            $forme = preg_match('/^[A-Z0-9]+$/', $forme) ? $forme : ' ';
            for ($j = 0, $n = strlen($forme); $j < $n; $j++) {
                $normalise .= $forme[$j];
                $origine[] = $i;
            }
        }

        $surligne = array_fill(0, count($caracteres), false);
        foreach ($mots as $mot) {
            $longueur = strlen($mot);
            $position = 0;
            while (($trouve = strpos($normalise, $mot, $position)) !== false) {
                $debutDeMot = $trouve === 0 || $normalise[$trouve - 1] === ' ';
                if ($debutDeMot) {
                    for ($k = $trouve; $k < $trouve + $longueur; $k++) {
                        $surligne[$origine[$k]] = true;
                    }
                }
                $position = $trouve + 1;
            }
        }

        $segments = [];
        $courant = '';
        $etat = $surligne[0];
        foreach ($caracteres as $i => $caractere) {
            if ($surligne[$i] !== $etat) {
                $segments[] = [$courant, $etat];
                $courant = '';
                $etat = $surligne[$i];
            }
            $courant .= $caractere;
        }
        $segments[] = [$courant, $etat];

        return $segments;
    }

    /**
     * Ressemblance entre la saisie et un nom de la liste, de 0 à 1.
     * Chaque mot saisi est comparé au mot le plus proche du nom.
     *
     * @param list<string> $mots
     */
    public static function ressemblance(array $mots, string $nomRecherche): float
    {
        $motsNom = explode(' ', $nomRecherche);
        if ($mots === [] || $motsNom === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($mots as $mot) {
            $meilleur = 0.0;
            foreach ($motsNom as $motNom) {
                if ($motNom === '') {
                    continue;
                }
                if (str_starts_with($motNom, $mot)) {
                    $meilleur = 1.0;
                    break;
                }
                // On compare au début du mot du nom, de la longueur de la saisie :
                // « KEMENG » doit ressembler à « KEMMENGNIE ».
                $reference = strlen($mot) < strlen($motNom) ? substr($motNom, 0, strlen($mot) + 1) : $motNom;
                $distance = levenshtein($mot, $reference);
                $score = 1 - $distance / max(strlen($mot), strlen($reference));
                $meilleur = max($meilleur, $score);
            }
            $total += $meilleur;
        }
        return $total / count($mots);
    }
}
