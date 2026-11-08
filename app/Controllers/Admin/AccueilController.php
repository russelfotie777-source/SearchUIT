<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Url;

final class AccueilController extends AdminController
{
    /**
     * L'administration s'ouvre sur l'espace des fichiers, comme un Drive.
     */
    public function index(Request $requete): Response
    {
        return $this->refuser($requete) ?? Response::redirection(Url::vers('admin/fichiers'), 302);
    }
}
