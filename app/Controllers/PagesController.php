<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class PagesController
{
    public function accueil(Request $requete): Response
    {
        return Response::html(View::page('public/accueil', [
            'titre' => 'Vos effets académiques',
            'pageActive' => 'recherche',
        ]));
    }
}
