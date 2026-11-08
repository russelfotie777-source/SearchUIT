<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Qr\CodeQr;
use RuntimeException;

/**
 * Générateur PDF minimal : pages A4, texte en polices TrueType intégrées,
 * rectangles, traits, images JPEG, rotation, transparence et QR codes.
 *
 * Les coordonnées sont en millimètres depuis le coin haut gauche de la page ;
 * pour le texte, y désigne la ligne de base.
 */
final class DocumentPdf
{
    private const POINTS_PAR_MM = 72 / 25.4;

    private float $largeur = 210.0;
    private float $hauteur = 297.0;

    /** @var list<string> contenu de chaque page */
    private array $pages = [];
    /** @var array<string, array{police: PoliceTrueType, id: string}> */
    private array $polices = [];
    /** @var array<string, array{donnees: string, largeur: int, hauteur: int, couleurs: int, id: string}> */
    private array $images = [];
    /** @var array<string, string> opacité → nom de l'état graphique */
    private array $opacites = [];
    /** @var array<string, string> */
    private array $infos = [];

    public function __construct(string $titre = '', string $auteur = '')
    {
        $this->infos = ['Title' => $titre, 'Author' => $auteur, 'Creator' => 'Effets académiques'];
    }

    public function largeurPage(): float
    {
        return $this->largeur;
    }

    public function hauteurPage(): float
    {
        return $this->hauteur;
    }

    public function ajouterPolice(string $nom, string $cheminTtf): void
    {
        $this->polices[$nom] = ['police' => new PoliceTrueType($cheminTtf), 'id' => 'F' . (count($this->polices) + 1)];
    }

    public function nouvellePage(): void
    {
        $this->pages[] = '';
    }

    // ------------------------------------------------------------ Dessin

    /** @param array{0: int, 1: int, 2: int}|string $couleur */
    public function remplissage(array|string $couleur): void
    {
        [$r, $v, $b] = self::rvb($couleur);
        $this->ecrire(sprintf('%.3F %.3F %.3F rg', $r, $v, $b));
    }

    /** @param array{0: int, 1: int, 2: int}|string $couleur */
    public function trait(array|string $couleur, float $epaisseur = 0.2): void
    {
        [$r, $v, $b] = self::rvb($couleur);
        $this->ecrire(sprintf('%.3F %.3F %.3F RG %.3F w', $r, $v, $b, $epaisseur * self::POINTS_PAR_MM));
    }

    /**
     * @param string $style « F » remplir, « S » contour, « B » les deux
     */
    public function rectangle(float $x, float $y, float $l, float $h, string $style = 'F', float $rayon = 0.0): void
    {
        $k = self::POINTS_PAR_MM;
        $operateur = ['F' => 'f', 'S' => 'S', 'B' => 'B'][$style] ?? 'f';

        if ($rayon <= 0) {
            $this->ecrire(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $k, ($this->hauteur - $y - $h) * $k, $l * $k, $h * $k, $operateur));
            return;
        }

        // Coins arrondis par courbes de Bézier (0,5523 ≈ 4/3 × (√2 − 1)).
        $r = min($rayon, $l / 2, $h / 2);
        $c = $r * 0.5523;
        $p = fn (float $px, float $py): string => sprintf('%.2F %.2F', $px * $k, ($this->hauteur - $py) * $k);
        $this->ecrire(implode(' ', [
            $p($x + $r, $y) . ' m',
            $p($x + $l - $r, $y) . ' l',
            $p($x + $l - $r + $c, $y) . ' ' . $p($x + $l, $y + $r - $c) . ' ' . $p($x + $l, $y + $r) . ' c',
            $p($x + $l, $y + $h - $r) . ' l',
            $p($x + $l, $y + $h - $r + $c) . ' ' . $p($x + $l - $r + $c, $y + $h) . ' ' . $p($x + $l - $r, $y + $h) . ' c',
            $p($x + $r, $y + $h) . ' l',
            $p($x + $r - $c, $y + $h) . ' ' . $p($x, $y + $h - $r + $c) . ' ' . $p($x, $y + $h - $r) . ' c',
            $p($x, $y + $r) . ' l',
            $p($x, $y + $r - $c) . ' ' . $p($x + $r - $c, $y) . ' ' . $p($x + $r, $y) . ' c',
            $operateur,
        ]));
    }

    public function ligne(float $x1, float $y1, float $x2, float $y2): void
    {
        $k = self::POINTS_PAR_MM;
        $this->ecrire(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $k, ($this->hauteur - $y1) * $k, $x2 * $k, ($this->hauteur - $y2) * $k));
    }

    /**
     * @param array{0: int, 1: int, 2: int}|string $couleur
     * @param string $alignement « G » gauche, « D » droite, « C » centre
     */
    public function texte(float $x, float $y, string $texte, string $police, float $taille, array|string $couleur = '#0f2a1d', string $alignement = 'G', float $espacement = 0.0): void
    {
        $definition = $this->polices[$police] ?? throw new RuntimeException("Police non déclarée : {$police}");
        $encode = self::versWindows1252($texte);

        $largeur = $this->largeurEncode($encode, $police, $taille) + $espacement * max(0, strlen($encode) - 1);
        if ($alignement === 'D') {
            $x -= $largeur;
        } elseif ($alignement === 'C') {
            $x -= $largeur / 2;
        }

        [$r, $v, $b] = self::rvb($couleur);
        $k = self::POINTS_PAR_MM;
        $this->ecrire(sprintf(
            'BT %.3F %.3F %.3F rg /%s %.2F Tf %.3F Tc %.2F %.2F Td (%s) Tj ET',
            $r, $v, $b,
            $definition['id'],
            $taille,
            $espacement * $k,
            $x * $k,
            ($this->hauteur - $y) * $k,
            self::echapper($encode),
        ));
    }

    /**
     * Largeur d'un texte en millimètres.
     */
    public function largeurTexte(string $texte, string $police, float $taille): float
    {
        return $this->largeurEncode(self::versWindows1252($texte), $police, $taille);
    }

    /**
     * Texte sur plusieurs lignes, coupé aux espaces. Renvoie la position
     * de la ligne de base suivante.
     *
     * @param array{0: int, 1: int, 2: int}|string $couleur
     */
    public function paragraphe(float $x, float $y, float $largeur, string $texte, string $police, float $taille, float $interligne, array|string $couleur = '#0f2a1d'): float
    {
        foreach (preg_split('/\R/u', $texte) ?: [] as $paragraphe) {
            $ligne = '';
            foreach (preg_split('/ +/u', trim($paragraphe)) ?: [] as $mot) {
                $essai = $ligne === '' ? $mot : $ligne . ' ' . $mot;
                if ($ligne !== '' && $this->largeurTexte($essai, $police, $taille) > $largeur) {
                    $this->texte($x, $y, $ligne, $police, $taille, $couleur);
                    $y += $interligne;
                    $ligne = $mot;
                } else {
                    $ligne = $essai;
                }
            }
            if ($ligne !== '') {
                $this->texte($x, $y, $ligne, $police, $taille, $couleur);
                $y += $interligne;
            }
        }
        return $y;
    }

    public function imageJpeg(string $chemin, float $x, float $y, float $l, float $h): void
    {
        if (!isset($this->images[$chemin])) {
            $infos = @getimagesize($chemin);
            if ($infos === false || ($infos[2] ?? 0) !== IMAGETYPE_JPEG) {
                throw new RuntimeException("Image JPEG attendue : {$chemin}");
            }
            $this->images[$chemin] = [
                'donnees' => (string) file_get_contents($chemin),
                'largeur' => $infos[0],
                'hauteur' => $infos[1],
                'couleurs' => (int) ($infos['channels'] ?? 3),
                'id' => 'Im' . (count($this->images) + 1),
            ];
        }
        $k = self::POINTS_PAR_MM;
        $this->ecrire(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q',
            $l * $k, $h * $k, $x * $k, ($this->hauteur - $y - $h) * $k, $this->images[$chemin]['id'],
        ));
    }

    /**
     * Dessine un QR code carré de côté $cote, marge blanche comprise.
     */
    public function qr(CodeQr $qr, float $x, float $y, float $cote, string $couleur = '#0f2a1d', int $marge = 2): void
    {
        $modules = $qr->taille() + 2 * $marge;
        $pas = $cote / $modules;
        $this->remplissage('#ffffff');
        $this->rectangle($x, $y, $cote, $cote);
        $this->remplissage($couleur);
        $k = self::POINTS_PAR_MM;
        $traces = '';
        for ($ligne = 0; $ligne < $qr->taille(); $ligne++) {
            for ($colonne = 0; $colonne < $qr->taille(); $colonne++) {
                if ($qr->estNoir($colonne, $ligne)) {
                    // Léger débord pour éviter les filets blancs entre modules à l'écran.
                    $traces .= sprintf(
                        '%.3F %.3F %.3F %.3F re ',
                        ($x + ($colonne + $marge) * $pas) * $k,
                        ($this->hauteur - $y - ($ligne + $marge + 1) * $pas) * $k,
                        $pas * $k + 0.05,
                        $pas * $k + 0.05,
                    );
                }
            }
        }
        $this->ecrire($traces . 'f');
    }

    /**
     * Applique une rotation (en degrés, sens horaire) autour d'un point
     * aux dessins faits dans $dessin.
     */
    public function pivoter(float $angle, float $cx, float $cy, callable $dessin): void
    {
        $k = self::POINTS_PAR_MM;
        $radians = deg2rad(-$angle);
        $cos = cos($radians);
        $sin = sin($radians);
        $px = $cx * $k;
        $py = ($this->hauteur - $cy) * $k;
        $this->ecrire(sprintf(
            'q %.5F %.5F %.5F %.5F %.3F %.3F cm',
            $cos, $sin, -$sin, $cos,
            $px - $px * $cos + $py * $sin,
            $py - $px * $sin - $py * $cos,
        ));
        $dessin($this);
        $this->ecrire('Q');
    }

    public function avecOpacite(float $opacite, callable $dessin): void
    {
        $cle = sprintf('%.2F', $opacite);
        $this->opacites[$cle] ??= 'GS' . (count($this->opacites) + 1);
        $this->ecrire('q /' . $this->opacites[$cle] . ' gs');
        $dessin($this);
        $this->ecrire('Q');
    }

    // ------------------------------------------------------------ Sortie

    public function sortie(): string
    {
        if ($this->pages === []) {
            $this->nouvellePage();
        }

        $objets = [];
        $reserver = static function () use (&$objets): int {
            $objets[] = '';
            return count($objets);
        };

        $catalogue = $reserver();
        $racinePages = $reserver();

        $ressourcesPolices = [];
        foreach ($this->polices as $definition) {
            $police = $definition['police'];
            $fichier = $reserver();
            $compresse = (string) gzcompress($police->donnees, 9);
            $objets[$fichier - 1] = sprintf("<< /Length %d /Length1 %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($compresse), strlen($police->donnees), $compresse);

            $descripteur = $reserver();
            $objets[$descripteur - 1] = sprintf(
                '<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%d %d %d %d] /ItalicAngle %.2F /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>',
                $police->nomPostScript, ...[...$police->boite, $police->angleItalique, $police->ascendante, $police->descendante, $police->hauteurCapitales, $fichier],
            );

            $objetPolice = $reserver();
            $objets[$objetPolice - 1] = sprintf(
                '<< /Type /Font /Subtype /TrueType /BaseFont /%s /FirstChar 32 /LastChar 255 /Widths [%s] /Encoding /WinAnsiEncoding /FontDescriptor %d 0 R >>',
                $police->nomPostScript,
                implode(' ', $police->chasses),
                $descripteur,
            );
            $ressourcesPolices[] = '/' . $definition['id'] . ' ' . $objetPolice . ' 0 R';
        }

        $ressourcesImages = [];
        foreach ($this->images as $image) {
            $objet = $reserver();
            $espace = $image['couleurs'] === 1 ? '/DeviceGray' : ($image['couleurs'] === 4 ? '/DeviceCMYK' : '/DeviceRGB');
            $objets[$objet - 1] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $image['largeur'], $image['hauteur'], $espace, strlen($image['donnees']), $image['donnees'],
            );
            $ressourcesImages[] = '/' . $image['id'] . ' ' . $objet . ' 0 R';
        }

        $ressourcesEtats = [];
        foreach ($this->opacites as $valeur => $nom) {
            $objet = $reserver();
            $objets[$objet - 1] = sprintf('<< /Type /ExtGState /ca %s /CA %s >>', $valeur, $valeur);
            $ressourcesEtats[] = '/' . $nom . ' ' . $objet . ' 0 R';
        }

        $ressources = $reserver();
        $objets[$ressources - 1] = '<< /ProcSet [/PDF /Text /ImageC]'
            . ($ressourcesPolices ? ' /Font << ' . implode(' ', $ressourcesPolices) . ' >>' : '')
            . ($ressourcesImages ? ' /XObject << ' . implode(' ', $ressourcesImages) . ' >>' : '')
            . ($ressourcesEtats ? ' /ExtGState << ' . implode(' ', $ressourcesEtats) . ' >>' : '')
            . ' >>';

        $enfants = [];
        $k = self::POINTS_PAR_MM;
        foreach ($this->pages as $contenu) {
            $flux = $reserver();
            $compresse = (string) gzcompress($contenu, 9);
            $objets[$flux - 1] = sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($compresse), $compresse);

            $page = $reserver();
            $objets[$page - 1] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %d 0 R /Contents %d 0 R >>',
                $racinePages, $this->largeur * $k, $this->hauteur * $k, $ressources, $flux,
            );
            $enfants[] = $page . ' 0 R';
        }

        $objets[$racinePages - 1] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $enfants), count($enfants));
        $objets[$catalogue - 1] = sprintf('<< /Type /Catalog /Pages %d 0 R /Lang (fr-FR) >>', $racinePages);

        $infos = $reserver();
        $date = 'D:' . date('YmdHis') . str_replace(':', "'", date('P')) . "'";
        $champs = ['/CreationDate (' . $date . ')', '/Producer (Effets academiques)'];
        foreach ($this->infos as $cle => $valeur) {
            if ($valeur !== '') {
                $champs[] = '/' . $cle . ' <FEFF' . strtoupper(bin2hex((string) mb_convert_encoding($valeur, 'UTF-16BE', 'UTF-8'))) . '>';
            }
        }
        $objets[$infos - 1] = '<< ' . implode(' ', $champs) . ' >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $positions = [];
        foreach ($objets as $index => $objet) {
            $positions[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $objet . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objets) + 1) . "\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }
        $pdf .= sprintf("trailer\n<< /Size %d /Root %d 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF\n", count($objets) + 1, $catalogue, $infos, $xref);

        return $pdf;
    }

    // ------------------------------------------------------------ Outils

    private function ecrire(string $operation): void
    {
        if ($this->pages === []) {
            $this->nouvellePage();
        }
        $this->pages[count($this->pages) - 1] .= $operation . "\n";
    }

    private function largeurEncode(string $encode, string $police, float $taille): float
    {
        return $this->polices[$police]['police']->largeur($encode) * $taille / 1000 / self::POINTS_PAR_MM;
    }

    public static function versWindows1252(string $texte): string
    {
        // Espaces fines et insécables vers l'insécable classique, présente en Windows-1252.
        $texte = str_replace(["\u{202F}", "\u{2009}", "\u{2007}"], "\u{00A0}", $texte);
        return (string) mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
    }

    private static function echapper(string $texte): string
    {
        return strtr($texte, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => '\\n']);
    }

    /**
     * @param array{0: int, 1: int, 2: int}|string $couleur « #0f2a1d » ou [15, 42, 29]
     * @return array{0: float, 1: float, 2: float}
     */
    private static function rvb(array|string $couleur): array
    {
        if (is_string($couleur)) {
            $hex = ltrim($couleur, '#');
            $couleur = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }
        return [$couleur[0] / 255, $couleur[1] / 255, $couleur[2] / 255];
    }
}
