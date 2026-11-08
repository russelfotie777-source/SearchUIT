<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Core\Url;
use App\Domain\NomEtudiant;
use App\Qr\CodeQr;
use App\Repositories\Etudiants;

/**
 * Fiche d'état d'un étudiant au format A4 : identité, effets disponibles,
 * tampon de statut, consignes de retrait et QR code de vérification.
 */
final class FicheEtat
{
    private const ENCRE = '#0f2a1d';
    private const ENCRE_2 = '#3b5246';
    private const ENCRE_3 = '#627569';
    private const TRAIT = '#dbe4de';
    private const FOND = '#f3f6f4';
    private const VERT = '#009c3c';
    private const VERT_ACTION = '#00772e';
    private const VERT_PALE = '#e4f3e9';
    private const ROUGE = '#d80c0c';
    private const JAUNE = '#fcb400';

    private const MARGE = 20.0;

    /**
     * @param array<string, mixed> $etudiant
     * @param array<string, string> $site
     */
    public static function generer(array $etudiant, array $site): string
    {
        $pdf = new DocumentPdf(
            "Fiche d'état des effets académiques, " . $etudiant['nom'],
            $site['service'] . ', ' . $site['etablissement'],
        );
        $polices = RACINE . '/resources/fonts/';
        $pdf->ajouterPolice('texte', $polices . 'Archivo-Regular.ttf');
        $pdf->ajouterPolice('fort', $polices . 'Archivo-SemiBold.ttf');
        $pdf->ajouterPolice('titre', $polices . 'ArchivoCondensed-Bold.ttf');
        $pdf->ajouterPolice('titre-noir', $polices . 'ArchivoCondensed-ExtraBold.ttf');
        $pdf->nouvellePage();

        $largeur = $pdf->largeurPage();
        $droite = $largeur - self::MARGE;
        $retire = $etudiant['statut'] === 'retire';
        $adresse = Url::absolue('fiche/' . $etudiant['reference']);

        // Bandeau aux couleurs du drapeau.
        foreach ([self::VERT, self::ROUGE, self::JAUNE] as $i => $couleur) {
            $pdf->remplissage($couleur);
            $pdf->rectangle($i * $largeur / 3, 0, $largeur / 3 + 0.1, 2.5);
        }

        // En-tête : établissement à gauche, référence à droite.
        $pdf->imageJpeg(RACINE . '/resources/img/logo-iut-douala-pdf.jpg', self::MARGE, 12, 16.5, 16);
        $pdf->texte(self::MARGE + 21, 19, $site['etablissement'], 'fort', 10.5, self::ENCRE);
        $pdf->texte(self::MARGE + 21, 24.2, $site['service'], 'texte', 9, self::ENCRE_3);

        $pdf->texte($droite, 18.5, 'Référence', 'texte', 8, self::ENCRE_3, 'D');
        $pdf->texte($droite, 25, (string) $etudiant['reference'], 'titre', 14, self::ENCRE, 'D', 0.35);

        $pdf->trait(self::TRAIT, 0.3);
        $pdf->ligne(self::MARGE, 33, $droite, 33);

        // Titre du document.
        $pdf->texte(self::MARGE, 47, "Fiche d'état des effets académiques", 'titre-noir', 21, self::ENCRE);
        $edition = 'Éditée le ' . date_fr(new \DateTimeImmutable(), true);
        if (($site['liste_mise_a_jour'] ?? '') !== '') {
            $edition .= ', d\'après la liste mise à jour le ' . date_fr($site['liste_mise_a_jour']);
        }
        $pdf->texte(self::MARGE, 53.5, $edition . '.', 'texte', 9, self::ENCRE_3);

        // Identité.
        $pdf->texte(self::MARGE, 67, 'Étudiant', 'texte', 8, self::ENCRE_3);
        $yNom = $pdf->paragraphe(self::MARGE, 75, 108, (string) $etudiant['nom'], 'titre', 17, 7, self::ENCRE);

        self::tampon($pdf, $retire, $etudiant['retire_le'] ?? null);

        // Informations de la liste.
        $yInfos = max(84.0, $yNom + 2);
        $pdf->remplissage(self::FOND);
        $pdf->rectangle(self::MARGE, $yInfos, $droite - self::MARGE, 19, 'F', 2.5);
        $infos = [
            'Filière' => $etudiant['filiere'] ?: '—',
            'Année académique' => $etudiant['annee'] ?: '—',
            'Niveau' => $etudiant['niveau'] ?: '—',
            'N° sur la liste' => $etudiant['numero'] !== null ? (string) $etudiant['numero'] : '—',
        ];
        $colonne = ($droite - self::MARGE - 10) / 4;
        $i = 0;
        foreach ($infos as $libelle => $valeur) {
            $x = self::MARGE + 5 + $i * $colonne;
            $pdf->texte($x, $yInfos + 7, $libelle, 'texte', 8, self::ENCRE_3);
            $pdf->texte($x, $yInfos + 13.5, self::couper($pdf, (string) $valeur, 'fort', 11, $colonne - 4), 'fort', 11, self::ENCRE);
            $i++;
        }

        // Tableau des effets.
        $y = $yInfos + 32;
        $xCode = 128.0;
        $xStatut = 150.0;
        $pdf->texte(self::MARGE, $y, 'Document', 'fort', 8.5, self::ENCRE_3);
        $pdf->texte($xCode, $y, 'Code', 'fort', 8.5, self::ENCRE_3);
        $pdf->texte($xStatut, $y, 'Statut', 'fort', 8.5, self::ENCRE_3);
        $y += 3;
        $pdf->trait(self::ENCRE, 0.35);
        $pdf->ligne(self::MARGE, $y, $droite, $y);

        $effets = Etudiants::effets($etudiant);
        if ($effets === []) {
            $y += 9;
            $pdf->texte(self::MARGE, $y, 'Aucun document précisé sur la liste. Renseignez-vous au guichet de la scolarité.', 'texte', 10, self::ENCRE_2);
            $y += 5;
        }
        foreach ($effets as $effet) {
            $complet = $effet['complet'] === $effet['court'] ? 'Document « ' . $effet['court'] . ' »' : $effet['complet'];
            if (!empty($effet['note'])) {
                $complet .= ' (' . ($effet['note'] === '?' ? 'à vérifier' : $effet['note']) . ')';
            }
            $pdf->texte(self::MARGE, $y + 7, self::couper($pdf, $complet, 'texte', 10.5, $xCode - self::MARGE - 4), 'texte', 10.5, self::ENCRE);

            $largeurCode = $pdf->largeurTexte($effet['court'], 'titre', 9) + 4;
            $pdf->remplissage($retire ? self::FOND : self::VERT_PALE);
            $pdf->rectangle($xCode, $y + 2.6, $largeurCode, 6, 'F', 1.2);
            $pdf->texte($xCode + 2, $y + 6.9, $effet['court'], 'titre', 9, $retire ? self::ENCRE_2 : '#005c24');

            if ($retire) {
                $statut = 'Retiré' . (!empty($etudiant['retire_le']) ? ' le ' . date('d/m/Y', strtotime((string) $etudiant['retire_le'])) : '');
                $pdf->texte($xStatut, $y + 7, $statut, 'fort', 10, self::ENCRE_3);
            } else {
                $pdf->remplissage(self::VERT_ACTION);
                $pdf->rectangle($xStatut, $y + 4.6, 2, 2, 'F', 1);
                $pdf->texte($xStatut + 3.5, $y + 7, 'Disponible', 'fort', 10, self::VERT_ACTION);
            }

            $y += 10.5;
            $pdf->trait(self::TRAIT, 0.25);
            $pdf->ligne(self::MARGE, $y, $droite, $y);
        }

        // Mention particulière portée sur la liste papier.
        $annotation = NomEtudiant::annotationLisible($etudiant['annotation'] ?? null);
        if ($annotation !== null) {
            $y += 8;
            $pdf->remplissage(self::JAUNE);
            $pdf->rectangle(self::MARGE, $y - 4.2, 0.9, 6.5);
            $pdf->texte(self::MARGE + 4, $y, $annotation, 'texte', 9.5, self::ENCRE_2);
            $y += 2;
        }

        // Consignes de retrait.
        $y += 14;
        $pdf->texte(self::MARGE, $y, $retire ? 'Documents déjà retirés' : 'Pour retirer vos documents', 'titre', 13, self::ENCRE);
        $y += 7;
        if ($retire) {
            $y = $pdf->paragraphe(self::MARGE, $y, 170, 'Ces documents ont été remis. En cas de désaccord, adressez-vous au service de la scolarité avec cette fiche.', 'texte', 10, 5.2, self::ENCRE_2);
        } else {
            $consignes = [];
            if (($site['retrait_lieu'] ?? '') !== '') {
                $consignes[] = ['Lieu', $site['retrait_lieu']];
            }
            if (($site['retrait_horaires'] ?? '') !== '') {
                $consignes[] = ['Horaires', str_replace("\n", ', ', $site['retrait_horaires'])];
            }
            if (($site['retrait_pieces'] ?? '') !== '') {
                $consignes[] = ['À présenter', $site['retrait_pieces']];
            }
            if ($consignes === []) {
                $y = $pdf->paragraphe(self::MARGE, $y, 170, 'Présentez cette fiche au ' . mb_strtolower($site['service']) . ' de l\'' . $site['etablissement'] . '.', 'texte', 10, 5.2, self::ENCRE_2);
            }
            foreach ($consignes as [$libelle, $texte]) {
                $pdf->texte(self::MARGE, $y, $libelle, 'fort', 10, self::ENCRE);
                $y = $pdf->paragraphe(self::MARGE + 28, $y, 142, $texte, 'texte', 10, 5.2, self::ENCRE_2) + 1.5;
            }
        }

        // Pied : vérification par QR code.
        $hauteur = $pdf->hauteurPage();
        $pdf->trait(self::TRAIT, 0.3);
        $pdf->ligne(self::MARGE, $hauteur - 42, $droite, $hauteur - 42);
        $pdf->qr(CodeQr::generer($adresse), $droite - 26, $hauteur - 38, 26, self::ENCRE);
        $pdf->texte(self::MARGE, $hauteur - 31, 'Vérifier cette fiche', 'fort', 9.5, self::ENCRE);
        $pdf->texte(self::MARGE, $hauteur - 26, 'Scannez le code ou ouvrez l\'adresse suivante pour voir son état à jour :', 'texte', 8.5, self::ENCRE_2);
        $pdf->texte(self::MARGE, $hauteur - 21.5, self::couper($pdf, $adresse, 'fort', 8.5, 128), 'fort', 8.5, self::VERT_ACTION);
        $pdf->paragraphe(self::MARGE, $hauteur - 14, 130, 'Cette fiche reprend la liste publiée par la scolarité. Elle informe de la disponibilité des documents et ne remplace aucun document officiel.', 'texte', 7.5, 3.6, self::ENCRE_3);

        return $pdf->sortie();
    }

    /**
     * Tampon incliné, comme un coup de tampon encreur sur la fiche.
     */
    private static function tampon(DocumentPdf $pdf, bool $retire, ?string $retireLe): void
    {
        $couleur = $retire ? self::ENCRE_3 : self::VERT;
        $cx = 160.0;
        $cy = 66.0;
        $pdf->avecOpacite(0.88, static function (DocumentPdf $pdf) use ($couleur, $retire, $retireLe, $cx, $cy): void {
            $pdf->pivoter(-8, $cx, $cy, static function (DocumentPdf $pdf) use ($couleur, $retire, $retireLe, $cx, $cy): void {
                $l = 48.0;
                $h = 19.0;
                $pdf->trait($couleur, 0.9);
                $pdf->rectangle($cx - $l / 2, $cy - $h / 2, $l, $h, 'S', 2.6);
                $pdf->trait($couleur, 0.3);
                $pdf->rectangle($cx - $l / 2 + 1.6, $cy - $h / 2 + 1.6, $l - 3.2, $h - 3.2, 'S', 1.6);
                $pdf->texte($cx, $cy + 1.8, $retire ? 'RETIRÉ' : 'DISPONIBLE', 'titre-noir', 17, $couleur, 'C', 0.5);
                $bas = $retire
                    ? ($retireLe ? 'LE ' . date('d/m/Y', strtotime($retireLe)) : 'À LA SCOLARITÉ')
                    : 'À LA SCOLARITÉ';
                $pdf->texte($cx, $cy + 6.2, $bas, 'fort', 6.5, $couleur, 'C', 0.6);
            });
        });
    }

    /**
     * Raccourcit un texte trop long pour sa colonne, avec des points de suspension.
     */
    private static function couper(DocumentPdf $pdf, string $texte, string $police, float $taille, float $largeur): string
    {
        if ($pdf->largeurTexte($texte, $police, $taille) <= $largeur) {
            return $texte;
        }
        while (mb_strlen($texte) > 1 && $pdf->largeurTexte($texte . '…', $police, $taille) > $largeur) {
            $texte = mb_substr($texte, 0, -1);
        }
        return rtrim($texte) . '…';
    }

    public static function nomFichier(array $etudiant): string
    {
        $nom = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', \App\Domain\Texte::sansAccents((string) $etudiant['nom'])), '-'));
        return 'fiche-etat-' . ($nom !== '' ? $nom . '-' : '') . $etudiant['reference'] . '.pdf';
    }
}
