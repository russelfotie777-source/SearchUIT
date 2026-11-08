<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\Etudiants;
use App\Repositories\Legende;

/**
 * Liste complète des effets académiques, filtrable par filière, année et document.
 */
final class ListeController
{
    private const PAR_PAGE = 50;

    public function afficher(Request $requete): Response
    {
        $filtres = [
            'nom' => mb_substr($requete->texte('nom'), 0, 80),
            'filiere' => mb_substr($requete->texte('filiere'), 0, 20),
            'annee' => $requete->texte('annee'),
            'document' => mb_substr($requete->texte('document'), 0, 20),
            'statut' => in_array($requete->texte('statut'), ['disponible', 'retire', 'tous'], true) ? $requete->texte('statut') : 'disponible',
            'tri' => array_key_exists($requete->texte('tri'), Etudiants::TRIS) ? $requete->texte('tri') : 'nom',
        ];

        $resultat = Etudiants::lister($filtres, max(1, $requete->entier('page', 1)), self::PAR_PAGE);
        $statistiques = Etudiants::statistiques();

        return Response::html(View::page('public/liste', [
            'titre' => 'Liste des effets disponibles',
            'description' => 'Tous les relevés de notes, attestations et certificats en attente au service de la scolarité, par filière et par année.',
            'pageActive' => 'liste',
            'filtres' => $filtres,
            'resultat' => $resultat,
            'parPage' => self::PAR_PAGE,
            'filieres' => Etudiants::filieres(),
            'annees' => Etudiants::annees(),
            'documents' => Legende::utilisee($statistiques['codes']),
            'filtresActifs' => count(array_filter([
                $filtres['nom'], $filtres['filiere'], $filtres['annee'], $filtres['document'],
                $filtres['statut'] !== 'disponible' ? 'x' : '',
            ], static fn ($v): bool => $v !== '')),
            'scripts' => ['js/liste.js'],
        ]));
    }
}
