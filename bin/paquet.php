<?php

declare(strict_types=1);

/*
 * Prépare l'archive à envoyer sur l'hébergement :
 *
 *   php bin/paquet.php
 *
 * Crée dist/effets-academiques-AAAAMMJJ-HHMM.zip, sans les tests, sans la
 * configuration locale, sans les fichiers déposés et sans aucune liste
 * d'étudiants. Il suffit ensuite de l'extraire dans public_html via cPanel.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

date_default_timezone_set('Africa/Douala');
$racine = dirname(__DIR__);
$inclus = ['.htaccess', 'app', 'bin/.htaccess', 'bin/importer.php', 'config/.htaccess', 'config/config.example.php', 'database', 'public', 'resources', 'storage', 'README.md', 'docs'];

// Jamais dans l'archive : données personnelles, configuration locale, fichiers déposés.
$exclus = [
    '#^config/config\.php$#',
    '#^storage/(uploads|cache|logs)/(?!\.gitkeep$|\.htaccess$)#',
    '#\.(xlsx|xls|csv)$#i',
    '#(^|/)\.DS_Store$#',
];

$dossierSortie = $racine . '/dist';
if (!is_dir($dossierSortie) && !mkdir($dossierSortie, 0755, true)) {
    fwrite(STDERR, "Impossible de créer dist/\n");
    exit(1);
}
$archive = $dossierSortie . '/effets-academiques-' . date('Ymd-Hi') . '.zip';

$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Impossible de créer {$archive}\n");
    exit(1);
}

$nombre = 0;
$ajouter = static function (string $relatif) use ($zip, $racine, $exclus, &$nombre): void {
    foreach ($exclus as $motif) {
        if (preg_match($motif, $relatif)) {
            return;
        }
    }
    $zip->addFile($racine . '/' . $relatif, $relatif);
    $nombre++;
};

foreach ($inclus as $entree) {
    $chemin = $racine . '/' . $entree;
    if (is_file($chemin)) {
        $ajouter($entree);
        continue;
    }
    if (!is_dir($chemin)) {
        continue;
    }
    $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($chemin, FilesystemIterator::SKIP_DOTS));
    foreach ($iterateur as $fichier) {
        if ($fichier->isFile()) {
            $ajouter(substr($fichier->getPathname(), strlen($racine) + 1));
        }
    }
}

// Les dossiers de stockage doivent exister, même vides, avec leur protection.
foreach (['storage/uploads', 'storage/cache', 'storage/logs'] as $dossier) {
    $zip->addEmptyDir($dossier);
}

$zip->close();
printf("%d fichiers, %s\n%s\n", $nombre, number_format(filesize($archive) / 1048576, 1, ',', ' ') . ' Mo', $archive);
