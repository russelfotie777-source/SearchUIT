<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Reference;
use App\Pdf\FicheEtat;
use App\Repositories\Etudiants;
use App\Repositories\Parametres;

/**
 * Fiche d'état d'un étudiant : page consultable et PDF téléchargeable.
 */
final class FicheController
{
    public function afficher(Request $requete): Response
    {
        $etudiant = $this->trouver($requete);
        if ($etudiant === null) {
            return $this->introuvable();
        }

        $homonymes = Database::tous(
            'SELECT * FROM etudiants WHERE nom_recherche = ? AND reference <> ? ORDER BY annee_debut DESC LIMIT 10',
            [$etudiant['nom_recherche'], $etudiant['reference']],
        );

        return Response::html(View::page('public/fiche', [
            'titre' => $etudiant['nom'],
            'description' => 'Fiche d\'état des effets académiques disponibles au service de la scolarité.',
            'pageActive' => 'recherche',
            'etudiant' => $etudiant,
            'effets' => Etudiants::effets($etudiant),
            'homonymes' => $homonymes,
            'scripts' => ['js/fiche.js'],
            'robots' => 'noindex',
        ]))->entete('X-Robots-Tag', 'noindex');
    }

    public function pdf(Request $requete): Response
    {
        $etudiant = $this->trouver($requete);
        if ($etudiant === null) {
            return $this->introuvable();
        }

        return Response::contenu(
            FicheEtat::generer($etudiant, Parametres::site()),
            FicheEtat::nomFichier($etudiant),
            'application/pdf',
            $requete->texte('apercu') === '1',
        )->entete('X-Robots-Tag', 'noindex');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function trouver(Request $requete): ?array
    {
        $reference = strtoupper($requete->parametre('ref'));
        return Reference::valide($reference) ? Etudiants::parReference($reference) : null;
    }

    private function introuvable(): Response
    {
        return Response::html(View::page('public/fiche-introuvable', [
            'titre' => 'Fiche introuvable',
            'pageActive' => 'recherche',
        ]), 404);
    }
}
