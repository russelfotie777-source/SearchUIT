<?php

declare(strict_types=1);

/*
 * Lance les tests : php tests/run.php
 * Aucun outil à installer : chaque fichier tests/*Test.php déclare ses cas avec test().
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

/** @var list<array{string, string, callable}> $GLOBALS['__tests'] */
$GLOBALS['__tests'] = [];
$GLOBALS['__fichier'] = '';

function test(string $nom, callable $cas): void
{
    $GLOBALS['__tests'][] = [$GLOBALS['__fichier'], $nom, $cas];
}

final class EchecTest extends Exception
{
}

function egal(mixed $attendu, mixed $obtenu, string $message = ''): void
{
    if ($attendu !== $obtenu) {
        throw new EchecTest(($message !== '' ? $message . "\n" : '')
            . '      attendu : ' . var_export($attendu, true) . "\n"
            . '      obtenu  : ' . var_export($obtenu, true));
    }
}

function vrai(bool $condition, string $message = 'condition fausse'): void
{
    if (!$condition) {
        throw new EchecTest($message);
    }
}

function leve(string $classe, callable $code): void
{
    try {
        $code();
    } catch (Throwable $e) {
        if ($e instanceof $classe) {
            return;
        }
        throw new EchecTest("attendu {$classe}, obtenu " . $e::class . ' : ' . $e->getMessage());
    }
    throw new EchecTest("attendu une exception {$classe}");
}

/**
 * Crée un petit classeur .xlsx à partir de lignes de cellules (texte ou nombre).
 *
 * @param list<list<string|int|float|null>> $lignes
 * @param array<string, int> $dates cellules (« F3 ») à marquer comme dates
 */
function classeur_test(array $lignes, array $dates = []): string
{
    $chemin = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $chaines = [];
    $xmlLignes = '';
    foreach ($lignes as $i => $cellules) {
        $numero = $i + 1;
        $xmlLignes .= '<row r="' . $numero . '">';
        foreach ($cellules as $j => $valeur) {
            if ($valeur === null) {
                continue;
            }
            $ref = chr(65 + $j) . $numero;
            if (is_string($valeur)) {
                $index = array_search($valeur, $chaines, true);
                if ($index === false) {
                    $chaines[] = $valeur;
                    $index = count($chaines) - 1;
                }
                $xmlLignes .= '<c r="' . $ref . '" t="s"><v>' . $index . '</v></c>';
            } else {
                $style = isset($dates[$ref]) ? ' s="1"' : '';
                $xmlLignes .= '<c r="' . $ref . '"' . $style . '><v>' . $valeur . '</v></c>';
            }
        }
        $xmlLignes .= '</row>';
    }

    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Feuil1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="' . $ns . '"><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14"/></cellXfs></styleSheet>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="' . $ns . '">'
        . implode('', array_map(static fn (string $s): string => '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>', $chaines)) . '</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="' . $ns . '"><sheetData>' . $xmlLignes . '</sheetData></worksheet>');
    $zip->close();

    return $chemin;
}

$filtre = $argv[1] ?? '';
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $fichier) {
    $GLOBALS['__fichier'] = basename($fichier, '.php');
    require $fichier;
}

$reussis = 0;
$echecs = [];
foreach ($GLOBALS['__tests'] as [$fichier, $nom, $cas]) {
    if ($filtre !== '' && stripos($fichier . ' ' . $nom, $filtre) === false) {
        continue;
    }
    try {
        $cas();
        $reussis++;
        echo '.';
    } catch (Throwable $e) {
        $echecs[] = "{$fichier} › {$nom}\n      " . ($e instanceof EchecTest ? $e->getMessage() : $e::class . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
        echo 'F';
    }
}

echo "\n\n";
foreach ($echecs as $echec) {
    echo "✗ {$echec}\n\n";
}
printf("%d réussi(s), %d échec(s)\n", $reussis, count($echecs));
exit($echecs === [] ? 0 : 1);
