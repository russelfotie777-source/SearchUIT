<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Sépare le nom d'un étudiant des annotations portées sur la liste papier :
 * « FOUDA ARMEL (nom partiellement barré) » → nom « FOUDA ARMEL », annotation « nom partiellement barré ».
 */
final class NomEtudiant
{
    /**
     * @return array{nom: string, annotation: ?string}
     */
    public static function analyser(string $brut): array
    {
        $texte = Texte::espaces($brut);

        $annotations = [];
        $texte = (string) preg_replace_callback('/\(([^()]*)\)/u', static function (array $m) use (&$annotations): string {
            $note = trim($m[1]);
            if ($note !== '') {
                $annotations[] = $note;
            }
            return ' ';
        }, $texte);

        // Parenthèse ouverte et jamais refermée : « EYENGA M. ( ».
        $texte = (string) preg_replace('/\s*\([^)]*$/u', '', $texte);

        $nom = mb_strtoupper(Texte::espaces(trim($texte, " \t-–—,;")));

        return [
            'nom' => $nom,
            'annotation' => $annotations === [] ? null : implode(' ; ', $annotations),
        ];
    }

    /**
     * Libellé lisible d'une annotation pour l'étudiant.
     */
    public static function annotationLisible(?string $annotation): ?string
    {
        if ($annotation === null || $annotation === '') {
            return null;
        }
        if (trim($annotation) === '?') {
            return "L'orthographe de ce nom est incertaine sur la liste d'origine.";
        }
        return 'Mention sur la liste d\'origine : ' . $annotation . '.';
    }
}
