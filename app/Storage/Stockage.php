<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

/**
 * Fichiers déposés par la scolarité, rangés dans storage/uploads sous un nom
 * aléatoire. Ils ne sont jamais servis directement : tout passe par PHP,
 * qui vérifie qu'un fichier est bien publié avant de l'envoyer.
 */
final class Stockage
{
    /** Extensions acceptées et types MIME correspondants. */
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /** Type envoyé au navigateur, indépendamment de ce que déclarait le fichier. */
    public const TYPES_SERVIS = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'csv' => 'text/csv; charset=utf-8',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /** Fichiers que le navigateur peut afficher sans les télécharger. */
    public const APERCU = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public static function dossier(): string
    {
        return RACINE . '/storage/uploads';
    }

    public static function chemin(string $nomStockage): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $nomStockage)) {
            throw new RuntimeException('Nom de stockage invalide.');
        }
        return self::dossier() . '/' . $nomStockage;
    }

    public static function existe(string $nomStockage): bool
    {
        try {
            return is_file(self::chemin($nomStockage));
        } catch (RuntimeException) {
            return false;
        }
    }

    public static function extension(string $nomFichier): string
    {
        return strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
    }

    public static function accepte(string $extension): bool
    {
        return isset(self::TYPES[$extension]);
    }

    /**
     * Vérifie que le contenu correspond à l'extension annoncée
     * (un .pdf qui n'en est pas un est refusé).
     */
    public static function contenuValide(string $chemin, string $extension): bool
    {
        if (!self::accepte($extension)) {
            return false;
        }
        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        if (in_array($type, self::TYPES[$extension], true)) {
            return true;
        }
        // Un CSV peut être détecté comme du texte de différentes façons selon le serveur.
        return $extension === 'csv' && str_starts_with($type, 'text/');
    }

    /**
     * Range un fichier téléversé et renvoie son nom de stockage.
     */
    public static function ranger(string $cheminTemporaire, string $extension, bool $televerse = true): string
    {
        $dossier = self::dossier();
        if (!is_dir($dossier) && !@mkdir($dossier, 0755, true)) {
            throw new RuntimeException("Le dossier storage/uploads n'existe pas et ne peut pas être créé.");
        }
        $nom = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $dossier . '/' . $nom;
        $deplace = $televerse ? @move_uploaded_file($cheminTemporaire, $destination) : @copy($cheminTemporaire, $destination);
        if (!$deplace) {
            throw new RuntimeException("Le fichier n'a pas pu être enregistré sur le serveur.");
        }
        @chmod($destination, 0644);
        return $nom;
    }

    public static function supprimer(string $nomStockage): void
    {
        if (self::existe($nomStockage)) {
            @unlink(self::chemin($nomStockage));
        }
    }

    /**
     * Libellé du type pour l'affichage : « PDF », « Excel », « Word »…
     */
    public static function libelleType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'PDF',
            'xlsx', 'xls' => 'Excel',
            'csv' => 'CSV',
            'ods' => 'Classeur',
            'docx', 'doc' => 'Word',
            'odt' => 'Texte',
            'pptx' => 'PowerPoint',
            'jpg', 'jpeg', 'png', 'webp' => 'Image',
            default => strtoupper($extension),
        };
    }

    /**
     * Famille du fichier, pour l'icône et sa couleur.
     */
    public static function famille(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'pdf',
            'xlsx', 'xls', 'csv', 'ods' => 'tableur',
            'docx', 'doc', 'odt' => 'texte',
            'pptx' => 'presentation',
            'jpg', 'jpeg', 'png', 'webp' => 'image',
            default => 'autre',
        };
    }
}
