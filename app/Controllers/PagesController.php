<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\Etudiants;
use App\Repositories\Fichiers;
use App\Repositories\Legende;

final class PagesController
{
    public function accueil(Request $requete): Response
    {
        $saisie = mb_substr($requete->texte('q'), 0, 120);
        $statistiques = Etudiants::statistiques();

        return Response::html(View::page('public/accueil', [
            'titre' => $saisie !== '' ? 'Recherche « ' . $saisie . ' »' : 'Vos effets académiques',
            'pageActive' => 'recherche',
            'saisie' => $saisie,
            'recherche' => $saisie !== '' ? Etudiants::rechercher($saisie) : null,
            'statistiques' => $statistiques,
            'legende' => Legende::utilisee($statistiques['codes']),
            'documents' => Fichiers::derniersPublics(3),
            'scripts' => ['js/recherche.js'],
        ]));
    }
}
