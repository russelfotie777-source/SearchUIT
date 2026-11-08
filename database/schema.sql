-- Schéma de la base SearchIUT (MySQL 5.7+ / MariaDB 10.3+).
-- Exécuté par l'installation ; chaque table n'est créée que si elle manque.

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(120) NOT NULL,
    identifiant VARCHAR(120) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    derniere_connexion DATETIME NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_utilisateurs_identifiant (identifiant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dossiers de l'espace fichiers (façon Google Drive).
CREATE TABLE IF NOT EXISTS dossiers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    nom VARCHAR(160) NOT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    supprime_le DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dossiers_parent (parent_id),
    CONSTRAINT fk_dossiers_parent FOREIGN KEY (parent_id) REFERENCES dossiers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fichiers déposés : PDF, listes Excel ou CSV, procès-verbaux, communiqués…
CREATE TABLE IF NOT EXISTS fichiers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dossier_id INT UNSIGNED NULL,
    nom VARCHAR(255) NOT NULL,
    nom_stockage VARCHAR(80) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    type_mime VARCHAR(120) NOT NULL,
    taille BIGINT UNSIGNED NOT NULL DEFAULT 0,
    empreinte CHAR(64) NOT NULL,
    public TINYINT(1) NOT NULL DEFAULT 1,
    ajoute_par INT UNSIGNED NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NULL,
    supprime_le DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fichiers_stockage (nom_stockage),
    KEY idx_fichiers_dossier (dossier_id),
    KEY idx_fichiers_public (public, supprime_le),
    CONSTRAINT fk_fichiers_dossier FOREIGN KEY (dossier_id) REFERENCES dossiers (id) ON DELETE SET NULL,
    CONSTRAINT fk_fichiers_auteur FOREIGN KEY (ajoute_par) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaque liste importée dans la recherche.
CREATE TABLE IF NOT EXISTS imports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fichier_id INT UNSIGNED NULL,
    nom_source VARCHAR(255) NOT NULL,
    mode ENUM('ajout', 'remplacement') NOT NULL,
    lignes INT UNSIGNED NOT NULL DEFAULT 0,
    ignorees INT UNSIGNED NOT NULL DEFAULT 0,
    fait_par INT UNSIGNED NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    annule_le DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_imports_fichier FOREIGN KEY (fichier_id) REFERENCES fichiers (id) ON DELETE SET NULL,
    CONSTRAINT fk_imports_auteur FOREIGN KEY (fait_par) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par étudiant inscrit sur une liste d'effets en attente.
CREATE TABLE IF NOT EXISTS etudiants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference CHAR(6) NOT NULL,
    import_id INT UNSIGNED NULL,
    numero INT UNSIGNED NULL,
    nom VARCHAR(190) NOT NULL,
    nom_recherche VARCHAR(190) NOT NULL,
    annotation VARCHAR(190) NULL,
    matricule VARCHAR(40) NULL,
    effets VARCHAR(255) NOT NULL DEFAULT '',
    codes VARCHAR(255) NOT NULL DEFAULT '',
    filiere VARCHAR(60) NULL,
    filiere_code VARCHAR(20) NULL,
    annee VARCHAR(60) NULL,
    annee_debut SMALLINT UNSIGNED NULL,
    niveau VARCHAR(40) NULL,
    statut ENUM('disponible', 'retire') NOT NULL DEFAULT 'disponible',
    retire_le DATETIME NULL,
    retire_par INT UNSIGNED NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_etudiants_reference (reference),
    KEY idx_etudiants_nom (nom_recherche),
    KEY idx_etudiants_matricule (matricule),
    KEY idx_etudiants_filiere (filiere_code),
    KEY idx_etudiants_annee (annee_debut),
    KEY idx_etudiants_statut (statut),
    KEY idx_etudiants_import (import_id),
    CONSTRAINT fk_etudiants_import FOREIGN KEY (import_id) REFERENCES imports (id) ON DELETE SET NULL,
    CONSTRAINT fk_etudiants_retrait FOREIGN KEY (retire_par) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages modifiables depuis l'administration.
CREATE TABLE IF NOT EXISTS parametres (
    cle VARCHAR(80) NOT NULL,
    valeur TEXT NULL,
    PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite les essais de mot de passe.
CREATE TABLE IF NOT EXISTS tentatives_connexion (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    identifiant VARCHAR(120) NOT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tentatives (ip, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des actions de l'administration.
CREATE TABLE IF NOT EXISTS journal (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    details VARCHAR(255) NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_journal_date (cree_le),
    CONSTRAINT fk_journal_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
