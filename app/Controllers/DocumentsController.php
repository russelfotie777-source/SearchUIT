<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\Fichiers;
use App\Storage\Stockage;

/**
 * Documents publiés par la scolarité : listes officielles, procès-verbaux, communiqués.
 */
final class DocumentsController
{
    public function index(Request $requete): Response
    {
        return Response::html(View::page('public/documents', [
            'titre' => 'Documents',
            'description' => 'Listes officielles, procès-verbaux et communiqués publiés par le service de la scolarité.',
            'pageActive' => 'documents',
            'groupes' => Fichiers::publicsParDossier(),
            'scripts' => ['js/documents.js'],
        ]));
    }

    /**
     * Ouvre un document dans le navigateur (PDF, image) ou le télécharge.
     */
    public function fichier(Request $requete): Response
    {
        $fichier = Fichiers::parId((int) $requete->parametre('id'));
        if ($fichier === null || !Fichiers::estPublic($fichier) || !Stockage::existe((string) $fichier['nom_stockage'])) {
            return Response::html(View::page('erreurs/404', ['titre' => 'Document introuvable']), 404);
        }

        $extension = (string) $fichier['extension'];
        $enLigne = in_array($extension, Stockage::APERCU, true) && $requete->texte('telecharger') !== '1';

        return Response::fichier(
            Stockage::chemin((string) $fichier['nom_stockage']),
            (string) $fichier['nom'],
            Stockage::TYPES_SERVIS[$extension] ?? 'application/octet-stream',
            $enLigne,
        );
    }
}
