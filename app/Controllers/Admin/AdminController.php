<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;

/**
 * Base des pages de l'administration : accès réservé et gabarit commun.
 */
abstract class AdminController
{
    /**
     * Renvoie une réponse de refus si personne n'est connecté.
     */
    protected function refuser(Request $requete): ?Response
    {
        if (Auth::utilisateur() !== null) {
            return null;
        }
        if ($requete->attendJson()) {
            return Response::json(['erreur' => 'Votre session a expiré. Reconnectez-vous.'], 401);
        }
        $retour = $requete->methode === 'GET' ? $requete->chemin : '/admin';
        return Response::redirection(Url::vers('admin/connexion', ['retour' => $retour]));
    }

    /**
     * @param array<string, mixed> $donnees
     */
    protected function page(string $vue, array $donnees = [], int $statut = 200): Response
    {
        $donnees += [
            'utilisateur' => Auth::utilisateur(),
            'espaceUtilise' => (int) Database::valeur('SELECT COALESCE(SUM(taille), 0) FROM fichiers'),
            'nombreCorbeille' => (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE supprime_le IS NOT NULL')
                + (int) Database::valeur('SELECT COUNT(*) FROM dossiers WHERE supprime_le IS NOT NULL'),
        ];
        return Response::html(View::page($vue, $donnees, 'layouts/admin'), $statut)
            ->entete('X-Robots-Tag', 'noindex, nofollow')
            ->entete('Cache-Control', 'no-store');
    }
}
