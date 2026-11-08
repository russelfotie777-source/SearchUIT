<?php

declare(strict_types=1);

namespace App\Import;

use DateTimeImmutable;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Lit la première feuille d'un classeur Excel (.xlsx) sans bibliothèque externe.
 * Un .xlsx est une archive zip de fichiers XML : on lit les chaînes partagées,
 * les styles (pour reconnaître les dates) puis la feuille, ligne par ligne.
 */
final class LecteurXlsx
{
    /** Formats de nombre intégrés à Excel qui représentent des dates. */
    private const FORMATS_DATE = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    private ZipArchive $zip;
    /** @var list<string> */
    private array $chaines = [];
    /** @var array<int, bool> index de style → est une date */
    private array $stylesDate = [];
    private bool $calendrier1904 = false;
    private string $feuille;

    public function __construct(string $chemin)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($chemin, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Ce fichier n'est pas un classeur Excel valide (.xlsx).");
        }
        $this->feuille = $this->cheminPremiereFeuille();
        $this->chargerChaines();
        $this->chargerStyles();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /**
     * Lignes de la feuille. Chaque ligne est un tableau [indexColonne => valeur],
     * l'index commençant à 0 pour la colonne A. Les dates sont des DateTimeImmutable.
     *
     * @return \Generator<int, array<int, string|int|float|bool|DateTimeImmutable|null>>
     */
    public function lignes(): \Generator
    {
        $xml = $this->lire($this->feuille);
        $lecteur = new XMLReader();
        $lecteur->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT);

        $ligne = null;
        $numeroLigne = 0;
        $colonneSuivante = 0;

        // read() descend dans les enfants, next() saute au nœud frère suivant :
        // après une cellule lue en entier, on avance avec next() sans relire.
        $encore = $lecteur->read();
        while ($encore) {
            $element = $lecteur->nodeType === XMLReader::ELEMENT;

            if ($element && $lecteur->localName === 'row') {
                $numeroLigne = (int) ($lecteur->getAttribute('r') ?? $numeroLigne + 1);
                $colonneSuivante = 0;
                if ($lecteur->isEmptyElement) {
                    yield $numeroLigne => [];
                    $ligne = null;
                } else {
                    $ligne = [];
                }
            } elseif ($lecteur->nodeType === XMLReader::END_ELEMENT && $lecteur->localName === 'row') {
                if ($ligne !== null) {
                    yield $numeroLigne => $ligne;
                }
                $ligne = null;
            } elseif ($element && $ligne !== null && $lecteur->localName === 'c') {
                $reference = $lecteur->getAttribute('r');
                $colonne = $reference !== null ? self::indexColonne($reference) : $colonneSuivante;
                $colonneSuivante = $colonne + 1;

                $type = $lecteur->getAttribute('t') ?? 'n';
                $style = (int) ($lecteur->getAttribute('s') ?? 0);
                $noeud = $lecteur->expand();
                if ($noeud !== false) {
                    $valeur = $this->valeurCellule($noeud, $type, $style);
                    if ($valeur !== null && $valeur !== '') {
                        $ligne[$colonne] = $valeur;
                    }
                }
                $encore = $lecteur->next();
                continue;
            }

            $encore = $lecteur->read();
        }

        $lecteur->close();
    }

    private function valeurCellule(\DOMNode $cellule, string $type, int $style): string|int|float|bool|DateTimeImmutable|null
    {
        $brut = null;
        $texteEnLigne = '';
        foreach ($cellule->childNodes as $enfant) {
            if ($enfant->localName === 'v') {
                $brut = $enfant->textContent;
            } elseif ($enfant->localName === 'is') {
                $texteEnLigne = $this->texteRiche($enfant);
            }
        }

        switch ($type) {
            case 's':
                return $this->chaines[(int) $brut] ?? null;
            case 'inlineStr':
                return $texteEnLigne;
            case 'str':
                return $brut;
            case 'b':
                return $brut === '1';
            case 'e':
                return null;
        }

        if ($brut === null || $brut === '' || !is_numeric($brut)) {
            return $brut;
        }

        if (!empty($this->stylesDate[$style])) {
            return $this->dateExcel((float) $brut);
        }

        $nombre = (float) $brut;
        return floor($nombre) === $nombre && abs($nombre) < PHP_INT_MAX ? (int) $nombre : $nombre;
    }

    /**
     * Convertit un numéro de série Excel en date.
     */
    private function dateExcel(float $serie): DateTimeImmutable
    {
        $origine = $this->calendrier1904 ? '1904-01-01' : '1899-12-30';
        $jours = (int) floor($serie);
        $secondes = (int) round(($serie - $jours) * 86400);
        return (new DateTimeImmutable($origine))
            ->modify(sprintf('%+d days', $jours))
            ->modify(sprintf('%+d seconds', $secondes));
    }

    /**
     * « B12 » → 1 ; « AA3 » → 26.
     */
    public static function indexColonne(string $reference): int
    {
        $lettres = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $reference));
        $index = 0;
        for ($i = 0, $n = strlen($lettres); $i < $n; $i++) {
            $index = $index * 26 + (ord($lettres[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private function cheminPremiereFeuille(): string
    {
        $classeur = $this->lire('xl/workbook.xml');
        $relations = $this->lire('xl/_rels/workbook.xml.rels');

        $this->calendrier1904 = (bool) preg_match('/<(?:\w+:)?workbookPr[^>]*date1904="(?:1|true)"/', $classeur);

        if (!preg_match('/<(?:\w+:)?sheet\b[^>]*\br:id="([^"]+)"/', $classeur, $m)
            && !preg_match('/<(?:\w+:)?sheet\b[^>]*\bid="([^"]+)"/', $classeur, $m)) {
            throw new RuntimeException('Le classeur ne contient aucune feuille.');
        }
        $id = preg_quote($m[1], '/');

        if (!preg_match('/<Relationship\b[^>]*\bId="' . $id . '"[^>]*\bTarget="([^"]+)"/', $relations, $r)
            && !preg_match('/<Relationship\b[^>]*\bTarget="([^"]+)"[^>]*\bId="' . $id . '"/', $relations, $r)) {
            throw new RuntimeException('Impossible de localiser la première feuille du classeur.');
        }

        $cible = $r[1];
        return str_starts_with($cible, '/') ? ltrim($cible, '/') : 'xl/' . $cible;
    }

    private function chargerChaines(): void
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }
        $lecteur = new XMLReader();
        $lecteur->XML($this->lire('xl/sharedStrings.xml'), 'UTF-8', LIBXML_NONET | LIBXML_COMPACT);
        $encore = $lecteur->read();
        while ($encore) {
            if ($lecteur->nodeType === XMLReader::ELEMENT && $lecteur->localName === 'si') {
                $noeud = $lecteur->expand();
                $this->chaines[] = $noeud === false ? '' : $this->texteRiche($noeud);
                $encore = $lecteur->next();
                continue;
            }
            $encore = $lecteur->read();
        }
        $lecteur->close();
    }

    /**
     * Texte d'un élément <si> ou <is>, y compris le texte mis en forme (<r><t>),
     * sans les indications phonétiques (<rPh>).
     */
    private function texteRiche(\DOMNode $noeud): string
    {
        $texte = '';
        foreach ($noeud->childNodes as $enfant) {
            if ($enfant->localName === 't') {
                $texte .= $enfant->textContent;
            } elseif ($enfant->localName === 'r') {
                foreach ($enfant->childNodes as $morceau) {
                    if ($morceau->localName === 't') {
                        $texte .= $morceau->textContent;
                    }
                }
            }
        }
        return $texte;
    }

    private function chargerStyles(): void
    {
        if ($this->zip->locateName('xl/styles.xml') === false) {
            return;
        }
        $xml = $this->lire('xl/styles.xml');

        $formatsPerso = [];
        if (preg_match_all('/<(?:\w+:)?numFmt\b[^>]*numFmtId="(\d+)"[^>]*formatCode="([^"]*)"/', $xml, $m, PREG_SET_ORDER)) {
            foreach ($m as $format) {
                $formatsPerso[(int) $format[1]] = html_entity_decode($format[2], ENT_QUOTES | ENT_XML1);
            }
        }

        if (!preg_match('/<(?:\w+:)?cellXfs\b[^>]*>(.*?)<\/(?:\w+:)?cellXfs>/s', $xml, $bloc)) {
            return;
        }
        preg_match_all('/<(?:\w+:)?xf\b([^>]*)\/?>/', $bloc[1], $xfs);
        foreach ($xfs[1] as $index => $attributs) {
            $id = preg_match('/numFmtId="(\d+)"/', $attributs, $f) ? (int) $f[1] : 0;
            $this->stylesDate[$index] = in_array($id, self::FORMATS_DATE, true)
                || (isset($formatsPerso[$id]) && self::formatEstDate($formatsPerso[$id]));
        }
    }

    public static function formatEstDate(string $format): bool
    {
        // On retire le texte entre guillemets, les couleurs et conditions [..] et les échappements.
        $nettoye = (string) preg_replace(['/"[^"]*"/', '/\[[^\]]*\]/', '/\\\\./'], '', $format);
        if ($nettoye === '' || stripos($nettoye, 'general') !== false) {
            return false;
        }
        return (bool) preg_match('/[dmyhs]/i', $nettoye) && !preg_match('/^[#0.,%\s]+$/', $nettoye);
    }

    private function lire(string $entree): string
    {
        $contenu = $this->zip->getFromName($entree);
        if ($contenu === false) {
            throw new RuntimeException("Classeur incomplet : « {$entree} » est introuvable.");
        }
        return $contenu;
    }
}
