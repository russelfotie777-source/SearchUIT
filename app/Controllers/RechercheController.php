<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Domain\Recherche;
use App\Repositories\Etudiants;

/**
 * Recherche en direct pendant la saisie (appelée par recherche.js).
 */
final class RechercheController
{
    public function api(Request $requete): Response
    {
        $saisie = mb_substr($requete->texte('q'), 0, 120);
        $recherche = Etudiants::rechercher($saisie);

        $resultats = array_map(static fn (array $e): array => [
            'reference' => $e['reference'],
            'nom' => $e['nom'],
            'segments' => Recherche::segments((string) $e['nom'], $recherche['mots']),
            'filiere' => $e['filiere'],
            'annee' => $e['annee'],
            'niveau' => $e['niveau'],
            'statut' => $e['statut'],
            'effets' => array_column(Etudiants::effets($e), 'court'),
            'adresse' => Url::vers('fiche/' . $e['reference']),
        ], $recherche['resultats']);

        return Response::json([
            'saisie' => $saisie,
            'total' => $recherche['total'],
            'proches' => $recherche['proches'],
            'resultats' => $resultats,
            'html' => View::rendre('partials/resultats', ['saisie' => $saisie, 'recherche' => $recherche]),
        ]);
    }
}
