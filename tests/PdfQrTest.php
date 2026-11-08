<?php

declare(strict_types=1);

use App\Pdf\DocumentPdf;
use App\Pdf\PoliceTrueType;
use App\Qr\CodeQr;

$matrice = static function (CodeQr $qr): string {
    $lignes = [];
    for ($y = 0; $y < $qr->taille(); $y++) {
        $ligne = '';
        for ($x = 0; $x < $qr->taille(); $x++) {
            $ligne .= $qr->estNoir($x, $y) ? '1' : '0';
        }
        $lignes[] = $ligne;
    }
    return implode("\n", $lignes);
};

test('produit des QR codes identiques à une implémentation de référence', function () use ($matrice): void {
    // Empreintes calculées avec la bibliothèque Python « qrcode » (mode octet, niveau M, masque imposé).
    $references = [
        ['https://effets.iutdouala.cm/fiche/K7Q2M9', 3, 3, '1f8289193007cec9b7a228f9d402634d'],
        ['http://localhost/projetiut/', 6, 3, '58965145e4217686f08a3397e7de67ee'],
        [str_repeat('w', 200), 1, 10, '1b174a797d8969735e03f272ab7c1a06'],
    ];
    foreach ($references as [$texte, $masque, $version, $empreinte]) {
        $qr = CodeQr::generer($texte, $masque);
        egal($version, $qr->version(), 'version pour ' . strlen($texte) . ' octets');
        egal($empreinte, md5($matrice($qr)), 'matrice pour masque ' . $masque);
    }
});

test('choisit seul un masque et produit un SVG', function (): void {
    $qr = CodeQr::generer('https://effets.iutdouala.cm/');
    vrai($qr->masque() >= 0 && $qr->masque() <= 7);
    egal(29, $qr->taille());
    $svg = $qr->svg(4, '#0f2a1d', 'Adresse du site');
    vrai(str_starts_with($svg, '<svg') && str_contains($svg, 'viewBox="0 0 37 37"'));
    vrai(str_contains($svg, 'aria-label="Adresse du site"'));
});

test('refuse un texte trop long', function (): void {
    leve(InvalidArgumentException::class, static fn () => CodeQr::generer(str_repeat('x', 400)));
});

test('lit les métriques des polices Archivo', function (): void {
    $police = new PoliceTrueType(RACINE . '/resources/fonts/Archivo-Regular.ttf');
    egal('Archivo-Regular', $police->nomPostScript);
    vrai($police->chasses[ord('W')] > $police->chasses[ord('i')], 'W plus large que i');
    vrai($police->chasses[0xE9] > 0, 'é a une chasse');
    vrai($police->ascendante > 0 && $police->descendante < 0);
});

test('génère un PDF bien formé', function (): void {
    $pdf = new DocumentPdf('Essai', 'Tests');
    $pdf->ajouterPolice('texte', RACINE . '/resources/fonts/Archivo-Regular.ttf');
    $pdf->nouvellePage();
    $pdf->texte(20, 30, 'Relevé de notes « S2 » – 8 novembre', 'texte', 12);
    $pdf->rectangle(20, 40, 50, 10, 'F', 2);
    $pdf->qr(CodeQr::generer('essai'), 20, 60, 30);
    $sortie = $pdf->sortie();

    vrai(str_starts_with($sortie, '%PDF-1.4'));
    vrai(str_ends_with($sortie, "%%EOF\n"));
    vrai(str_contains($sortie, '/Subtype /TrueType /BaseFont /Archivo-Regular'));

    // Chaque entrée de la table xref doit pointer sur le début de son objet.
    preg_match('/startxref\n(\d+)/', $sortie, $m);
    $xref = substr($sortie, (int) $m[1]);
    preg_match_all('/^(\d{10}) 00000 n $/m', $xref, $positions);
    foreach ($positions[1] as $i => $position) {
        egal(($i + 1) . ' 0 obj', substr($sortie, (int) $position, strlen(($i + 1) . ' 0 obj')));
    }
});

test('convertit le texte pour les polices du PDF', function (): void {
    egal("8\xA0novembre", DocumentPdf::versWindows1252("8\u{202F}novembre"));
    egal("\xAB \x9C \x80 \xBB", DocumentPdf::versWindows1252('« œ € »'));
});
