<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cycle d'une requête : installation, session, routes, erreurs.
 */
final class App
{
    public static function lancer(): void
    {
        $requete = Request::depuisGlobales();

        try {
            $reponse = self::traiter($requete);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $reponse = self::erreur($requete, $e);
        }

        $reponse->envoyer();
    }

    public static function installee(): bool
    {
        return Config::presente() && Config::get('installe') === true;
    }

    private static function traiter(Request $requete): Response
    {
        $enInstallation = str_starts_with($requete->chemin, '/installation');

        if (!self::installee() && !$enInstallation) {
            return Response::redirection(Url::vers('installation'), 302);
        }

        Session::demarrer();

        if ($requete->methode === 'POST' && !Csrf::valide($requete)) {
            $message = 'La page a expiré. Rechargez-la puis recommencez.';
            if ($requete->attendJson()) {
                return Response::json(['erreur' => $message], 419);
            }
            Session::flash('erreur', $message);
            $retour = (string) ($requete->serveur['HTTP_REFERER'] ?? Url::vers('/'));
            return Response::redirection($retour);
        }

        if (self::installee()) {
            View::partager('site', \App\Repositories\Parametres::site());
        }
        View::partager('messages', Session::messages());

        $routeur = new Router();
        (require RACINE . '/app/routes.php')($routeur);

        return $routeur->traiter($requete);
    }

    private static function erreur(Request $requete, \Throwable $e): Response
    {
        if ($requete->attendJson()) {
            return Response::json(['erreur' => "Une erreur s'est produite. Réessayez dans un instant."], 500);
        }
        $donnees = [
            'titre' => 'Erreur',
            'detail' => Config::get('debug') ? (string) $e : null,
        ];
        try {
            return Response::html(View::page('erreurs/500', $donnees), 500);
        } catch (\Throwable) {
            return Response::html('<h1>Erreur</h1><p>Le site rencontre un problème. Réessayez dans un instant.</p>', 500);
        }
    }
}
