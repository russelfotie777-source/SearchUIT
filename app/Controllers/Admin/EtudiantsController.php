<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Annee;
use App\Domain\Effets;
use App\Domain\Filiere;
use App\Domain\NomEtudiant;
use App\Domain\Reference;
use App\Domain\Texte;
use App\Repositories\Etudiants;
use App\Repositories\Journal;

/**
 * Étudiants en ligne : marquer les documents comme retirés, corriger une ligne,
 * ajouter un étudiant oublié, annuler un import.
 */
final class EtudiantsController extends AdminController
{
    private const PAR_PAGE = 50;

    public function index(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }

        $filtres = [
            'nom' => mb_substr($requete->texte('recherche'), 0, 80),
            'filiere' => mb_substr($requete->texte('filiere'), 0, 20),
            'statut' => in_array($requete->texte('statut'), ['disponible', 'retire'], true) ? $requete->texte('statut') : 'tous',
            'tri' => $requete->texte('tri') === 'recent' ? 'recent' : 'nom',
        ];
        $resultat = Etudiants::lister($filtres, max(1, $requete->entier('page', 1)), self::PAR_PAGE);

        $compteurs = Database::un("SELECT COUNT(*) AS total, SUM(statut = 'retire') AS retires FROM etudiants") ?? [];

        return $this->page('admin/etudiants', [
            'titre' => 'Étudiants',
            'section' => 'etudiants',
            'rechercheAction' => \App\Core\Url::vers('admin/etudiants'),
            'rechercheTexte' => 'Rechercher un étudiant',
            'rechercheValeur' => $filtres['nom'],
            'filtres' => $filtres,
            'resultat' => $resultat,
            'filieres' => Etudiants::filieres(),
            'total' => (int) ($compteurs['total'] ?? 0),
            'retires' => (int) ($compteurs['retires'] ?? 0),
            'imports' => Database::tous(
                'SELECT i.*, u.nom AS auteur, (SELECT COUNT(*) FROM etudiants e WHERE e.import_id = i.id) AS en_ligne
                 FROM imports i LEFT JOIN utilisateurs u ON u.id = i.fait_par
                 WHERE i.lignes > 0 ORDER BY i.cree_le DESC, i.id DESC LIMIT 20',
            ),
            'scripts' => ['js/etudiants.js'],
        ]);
    }

    public function creer(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $donnees = $this->lireFormulaire($requete);
        if (is_string($donnees)) {
            return Response::json(['erreur' => $donnees], 422);
        }

        $references = array_fill_keys(array_map('strval', Database::requete('SELECT reference FROM etudiants')->fetchAll(\PDO::FETCH_COLUMN)), true);
        $donnees['reference'] = Reference::nouvelle($references);
        $colonnes = array_keys($donnees);
        Database::requete(
            'INSERT INTO etudiants (' . implode(', ', $colonnes) . ') VALUES (' . implode(', ', array_fill(0, count($colonnes), '?')) . ')',
            array_values($donnees),
        );
        Etudiants::invalider();
        Journal::ajouter(Auth::id(), 'Étudiant ajouté', $donnees['nom']);

        return Response::json(['ok' => true, 'message' => 'Étudiant ajouté à la liste en ligne.', 'reference' => $donnees['reference']]);
    }

    /**
     * Actions sur une ligne : retirer, remettre, modifier, supprimer.
     */
    public function action(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        $id = (int) $requete->parametre('id');
        $etudiant = Database::un('SELECT * FROM etudiants WHERE id = ?', [$id]);
        if ($etudiant === null) {
            return Response::json(['erreur' => "Cette ligne n'existe plus. Rechargez la page."], 404);
        }
        $nom = (string) $etudiant['nom'];

        switch ($requete->parametre('action')) {
            case 'retirer':
                Database::requete(
                    "UPDATE etudiants SET statut = 'retire', retire_le = NOW(), retire_par = ?, modifie_le = NOW() WHERE id = ?",
                    [Auth::id(), $id],
                );
                Etudiants::invalider();
                Journal::ajouter(Auth::id(), 'Documents remis', $nom . ' (' . $etudiant['reference'] . ')');
                return Response::json(['ok' => true, 'message' => "Documents de {$nom} marqués comme retirés.", 'annuler' => 'remettre']);

            case 'remettre':
                Database::requete(
                    "UPDATE etudiants SET statut = 'disponible', retire_le = NULL, retire_par = NULL, modifie_le = NOW() WHERE id = ?",
                    [$id],
                );
                Etudiants::invalider();
                Journal::ajouter(Auth::id(), 'Retrait annulé', $nom . ' (' . $etudiant['reference'] . ')');
                return Response::json(['ok' => true, 'message' => "Documents de {$nom} de nouveau disponibles."]);

            case 'modifier':
                $donnees = $this->lireFormulaire($requete);
                if (is_string($donnees)) {
                    return Response::json(['erreur' => $donnees], 422);
                }
                $affectations = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($donnees)));
                Database::requete("UPDATE etudiants SET {$affectations}, modifie_le = NOW() WHERE id = ?", [...array_values($donnees), $id]);
                Etudiants::invalider();
                Journal::ajouter(Auth::id(), 'Étudiant corrigé', $donnees['nom'] . ' (' . $etudiant['reference'] . ')');
                return Response::json(['ok' => true, 'message' => 'Modifications enregistrées.']);

            case 'supprimer':
                Database::requete('DELETE FROM etudiants WHERE id = ?', [$id]);
                Etudiants::invalider();
                Journal::ajouter(Auth::id(), 'Étudiant retiré de la liste', $nom . ' (' . $etudiant['reference'] . ')');
                return Response::json(['ok' => true, 'message' => "{$nom} ne figure plus sur la liste en ligne."]);
        }

        return Response::json(['erreur' => 'Action inconnue.'], 400);
    }

    public function historique(Request $requete): Response
    {
        if ($refus = $this->refuser($requete)) {
            return $refus;
        }
        return $this->page('admin/historique', [
            'titre' => 'Historique',
            'section' => 'historique',
            'entrees' => Journal::recents(200),
        ]);
    }

    /**
     * Champs du formulaire d'ajout ou de modification, normalisés comme à l'import.
     *
     * @return array<string, mixed>|string les colonnes, ou un message d'erreur
     */
    private function lireFormulaire(Request $requete): array|string
    {
        $nom = NomEtudiant::analyser($requete->texte('nom'));
        if ($nom['nom'] === '') {
            return 'Indiquez le nom de l\'étudiant.';
        }
        $effetsBrut = Texte::espaces($requete->texte('effets'));
        if ($effetsBrut === '') {
            return 'Indiquez au moins un document, par exemple « S1 + S2 » ou « ATT-DUT ».';
        }
        $effets = Effets::analyser($effetsBrut);
        $filiere = Filiere::analyser($requete->texte('filiere'));
        $annee = Annee::analyser($requete->texte('annee'));
        $matricule = mb_strtoupper(Texte::espaces($requete->texte('matricule')));
        $niveau = Texte::espaces($requete->texte('niveau'));

        return [
            'nom' => mb_substr($nom['nom'], 0, 190),
            'nom_recherche' => mb_substr(Texte::pourRecherche($nom['nom']), 0, 190),
            'annotation' => $nom['annotation'],
            'matricule' => $matricule === '' ? null : mb_substr($matricule, 0, 40),
            'effets' => mb_substr($effetsBrut, 0, 255),
            'codes' => Effets::codesStockes($effets),
            'filiere' => $filiere['libelle'],
            'filiere_code' => $filiere['code'],
            'annee' => $annee['libelle'],
            'annee_debut' => $annee['debut'],
            'niveau' => $niveau === '' ? null : mb_substr($niveau, 0, 40),
        ];
    }
}
