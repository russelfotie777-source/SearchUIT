<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Qr\CodeQr;

/**
 * Affiche A4 à imprimer et à poser au tableau d'affichage :
 * les étudiants scannent le QR code et arrivent sur la recherche.
 */
final class Affiche
{
    /**
     * @param array<string, string> $site
     */
    public static function generer(string $adresse, array $site): string
    {
        $pdf = new DocumentPdf('Affiche, effets académiques', $site['service'] . ', ' . $site['etablissement']);
        $polices = RACINE . '/resources/fonts/';
        $pdf->ajouterPolice('texte', $polices . 'Archivo-Regular.ttf');
        $pdf->ajouterPolice('fort', $polices . 'Archivo-SemiBold.ttf');
        $pdf->ajouterPolice('titre', $polices . 'ArchivoCondensed-ExtraBold.ttf');
        $pdf->nouvellePage();

        $largeur = $pdf->largeurPage();
        $hauteur = $pdf->hauteurPage();
        $marge = 22.0;

        foreach (['#009c3c', '#d80c0c', '#fcb400'] as $i => $couleur) {
            $pdf->remplissage($couleur);
            $pdf->rectangle($i * $largeur / 3, 0, $largeur / 3 + 0.1, 5);
        }

        $pdf->imageJpeg(RACINE . '/resources/img/logo-iut-douala-pdf.jpg', $marge, 18, 22, 21.3);
        $pdf->texte($marge + 27, 27, $site['etablissement'], 'fort', 13, '#0f2a1d');
        $pdf->texte($marge + 27, 33.5, $site['service'], 'texte', 11, '#627569');

        $y = 60.0;
        foreach (['Vos effets académiques', 'sont-ils prêts ?'] as $ligne) {
            $pdf->texte($marge, $y, $ligne, 'titre', 46, '#0f2a1d');
            $y += 16.5;
        }
        $pdf->paragraphe($marge, $y + 2, $largeur - 2 * $marge, 'Relevés de notes, attestations de réussite, certificats : vérifiez si un document vous attend au service de la scolarité, et téléchargez votre fiche d\'état.', 'texte', 14, 7, '#3b5246');

        // Le QR code au centre, sur un fond vert pâle qui l'isole du reste.
        $cote = 100.0;
        $x = ($largeur - $cote) / 2;
        $yQr = 124.0;
        $pdf->remplissage('#e4f3e9');
        $pdf->rectangle($x - 10, $yQr - 10, $cote + 20, $cote + 20, 'F', 8);
        $pdf->qr(CodeQr::generer($adresse), $x, $yQr, $cote, '#0f2a1d', 2);

        $pdf->texte($largeur / 2, $yQr + $cote + 23, 'Scannez avec l\'appareil photo de votre téléphone,', 'fort', 15, '#0f2a1d', 'C');
        $pdf->texte($largeur / 2, $yQr + $cote + 30.5, 'puis tapez votre nom.', 'fort', 15, '#0f2a1d', 'C');
        $pdf->texte($largeur / 2, $yQr + $cote + 40, 'Ou rendez-vous sur ' . preg_replace('#^https?://#', '', rtrim($adresse, '/')), 'texte', 12, '#00772e', 'C');

        $pdf->trait('#dbe4de', 0.4);
        $pdf->ligne($marge, $hauteur - 22, $largeur - $marge, $hauteur - 22);
        $pied = trim(implode('   ', array_filter([$site['retrait_lieu'] ?? '', $site['retrait_horaires'] !== '' ? str_replace("\n", ', ', $site['retrait_horaires']) : ''])));
        $pdf->texte($marge, $hauteur - 14, $pied !== '' ? $pied : $site['service'] . ', ' . $site['etablissement'], 'texte', 10, '#627569');

        return $pdf->sortie();
    }
}
