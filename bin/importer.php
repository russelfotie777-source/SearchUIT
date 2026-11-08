<?php

declare(strict_types=1);

/*
 * Importe une liste d'effets depuis la ligne de commande.
 *
 *   php bin/importer.php "Effets_academiques.xlsx"              ajoute la liste
 *   php bin/importer.php "Effets_academiques.xlsx" --remplacer  remplace toute la liste
 *   php bin/importer.php "Effets_academiques.xlsx" --apercu     analyse sans rien enregistrer
 *
 * L'administration fait la même chose par glisser-déposer ; ce script sert
 * au premier chargement et aux tests.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Import\AnalyseurListe;
use App\Import\Importeur;

$arguments = array_slice($argv, 1);
$options = array_filter($arguments, static fn (string $a): bool => str_starts_with($a, '--'));
$fichiers = array_values(array_diff($arguments, $options));

if ($fichiers === [] || in_array('--aide', $options, true)) {
    fwrite(STDERR, "Usage : php bin/importer.php <fichier.xlsx|fichier.csv> [--remplacer] [--apercu]\n");
    exit(1);
}

$chemin = $fichiers[0];
if (!is_file($chemin)) {
    fwrite(STDERR, "Fichier introuvable : {$chemin}\n");
    exit(1);
}

try {
    $debut = microtime(true);
    $analyse = AnalyseurListe::depuisFichier($chemin, pathinfo($chemin, PATHINFO_EXTENSION));
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

printf(
    "Liste reconnue : %d étudiants (en-tête ligne %d, %d lignes ignorées) en %.1f s\n",
    count($analyse['etudiants']),
    $analyse['ligne_entete'],
    $analyse['ignorees'],
    microtime(true) - $debut,
);
printf("Colonnes : %s\n", implode(', ', array_map(
    static fn (string $role, int $index): string => $role . ' = ' . chr(65 + $index),
    array_keys($analyse['colonnes']),
    $analyse['colonnes'],
)));
foreach ($analyse['avertissements'] as $avertissement) {
    echo "Attention : {$avertissement}\n";
}

if (in_array('--apercu', $options, true)) {
    foreach (array_slice($analyse['etudiants'], 0, 5) as $etudiant) {
        printf("  %-40s %-20s %-10s %s\n", $etudiant['nom'], $etudiant['codes'], $etudiant['filiere'], $etudiant['annee']);
    }
    echo "Aperçu seulement : rien n'a été enregistré.\n";
    exit(0);
}

$mode = in_array('--remplacer', $options, true) ? Importeur::REMPLACEMENT : Importeur::AJOUT;
$resultat = (new Importeur())->importer($analyse['etudiants'], $mode, basename($chemin), null, null, $analyse['ignorees']);

printf(
    "Import n° %d (%s) : %d étudiants en ligne, %d doublons ignorés, %d fiches conservées dont %d retirées.\n",
    $resultat['import_id'],
    $mode,
    $resultat['ajoutes'],
    $resultat['doublons'],
    $resultat['conserves'],
    $resultat['retires_conserves'],
);
