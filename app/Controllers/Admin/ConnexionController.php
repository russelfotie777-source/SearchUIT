<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

final class ConnexionController
{
    public function afficher(Request $requete): Response
    {
        if (Auth::utilisateur() !== null) {
            return Response::redirection(Url::vers('admin'));
        }
        return $this->formulaire($requete->texte('retour'));
    }

    public function connecter(Request $requete): Response
    {
        $identifiant = $requete->texte('identifiant');
        $erreur = Auth::connecter($identifiant, (string) $requete->input('mot_de_passe', ''), $requete->ip());

        if ($erreur !== null) {
            return $this->formulaire($requete->texte('retour'), $identifiant, $erreur, 422);
        }

        $retour = $requete->texte('retour');
        // Seules les pages de l'administration sont acceptées comme destination.
        $destination = preg_match('#^/admin(/[A-Za-z0-9/_-]*)?$#', $retour) ? $retour : '/admin';
        return Response::redirection(Url::vers($destination));
    }

    public function deconnecter(Request $requete): Response
    {
        Auth::deconnecter();
        Session::demarrer();
        Session::flash('succes', 'Vous êtes déconnecté.');
        return Response::redirection(Url::vers('admin/connexion'));
    }

    private function formulaire(string $retour, string $identifiant = '', ?string $erreur = null, int $statut = 200): Response
    {
        return Response::html(View::page('admin/connexion', [
            'titre' => 'Connexion',
            'retour' => $retour,
            'identifiant' => $identifiant,
            'erreur' => $erreur,
        ], 'layouts/connexion'), $statut)->entete('X-Robots-Tag', 'noindex, nofollow');
    }
}
