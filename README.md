# Effets académiques, IUT de Douala

Site de consultation des effets académiques en attente au service de la scolarité : relevés de notes, attestations de réussite, certificats de scolarité.

Un étudiant scanne le QR code affiché à l'IUT ou ouvre le site, tape son nom, voit en direct si des documents l'attendent et télécharge sa fiche d'état en PDF. La scolarité dépose ses listes Excel, CSV ou PDF dans un espace organisé comme Google Drive, sans manipulation technique.

## Ce que fait le site

**Pour les étudiants**

- Recherche par nom pendant la saisie : mots dans n'importe quel ordre, sans accents, noms proches proposés en cas de faute de frappe. Recherche par matricule si la liste en contient.
- Fiche d'état de chaque étudiant, avec un tampon « Disponible » ou « Retiré », téléchargeable en PDF A4 avec QR code de vérification.
- Liste complète filtrable par filière, année, document et statut.
- Page Documents : procès-verbaux, listes officielles et communiqués publiés par la scolarité.

**Pour la scolarité** (`/admin`)

- Espace fichiers façon Google Drive : dossiers, glisser-déposer depuis l'ordinateur, publication ou masquage d'un clic, corbeille de 30 jours.
- Une liste Excel ou CSV déposée est reconnue seule : aperçu, puis remplacement de la liste en ligne ou ajout à la suite. Les fiches déjà marquées comme retirées le restent.
- Remise des documents en un clic (« Marquer comme retiré »), correction ou ajout d'un étudiant, annulation d'une publication.
- Historique de toutes les actions, comptes séparés pour chaque agent.
- Réglages : lieu et horaires de retrait, libellés des codes, affiche A4 avec QR code à imprimer.

## Choix techniques

PHP 8.1 ou plus et MySQL, **sans aucune dépendance** : ni Composer, ni Node, ni étape de compilation. Le projet se dépose tel quel sur un hébergement mutualisé.

- Lecture des classeurs `.xlsx` avec `ZipArchive` et `XMLReader`, lecture des CSV avec détection du séparateur et de l'encodage Windows-1252.
- Générateur PDF et encodeur QR code écrits pour le projet. L'encodeur est vérifié module par module contre une implémentation de référence.
- CSS écrit à la main autour de quelques variables (couleurs du logo, police Archivo auto-hébergée), JavaScript sans framework.

## Mise en ligne sur iFastNet (cPanel)

1. **Préparer l'archive** sur votre ordinateur :
   ```bash
   php bin/paquet.php
   ```
   Le fichier `dist/effets-academiques-….zip` ne contient ni les tests, ni la configuration locale, ni aucune liste d'étudiants.
2. **Base de données** : cPanel, *MySQL Databases*. Créez une base, un utilisateur, puis ajoutez l'utilisateur à la base avec *All Privileges*. Notez le nom de la base, l'utilisateur, le mot de passe et l'hôte affiché par cPanel.
3. **Version de PHP** : cPanel, *Select PHP Version*. Choisissez 8.2 ou plus récent et vérifiez que les extensions `pdo_mysql`, `zip`, `mbstring` et `fileinfo` sont cochées. Dans *Options*, réglez `upload_max_filesize` et `post_max_size` à 50M si vous déposez de gros fichiers.
4. **Fichiers** : cPanel, *File Manager*, dossier `public_html` (ou le dossier du sous-domaine). Envoyez l'archive puis *Extract*. Affichez les fichiers cachés pour vérifier que `.htaccess` est bien présent à la racine.
5. **Droits** : les dossiers `config/` et `storage/` (avec ses sous-dossiers) doivent être modifiables, en 755.
6. **Installation** : ouvrez `https://votre-domaine/installation`. La page vérifie l'hébergement, demande les accès MySQL, crée les tables puis le compte de la scolarité.
7. **Première liste** : connectez-vous sur `/admin`, ouvrez le dossier *Listes des effets disponibles* et glissez-y le fichier Excel. Choisissez *Publier la liste*.
8. **Réglages** : renseignez le lieu et les horaires de retrait, puis imprimez l'affiche QR code.
9. **HTTPS** : activez le certificat gratuit (AutoSSL ou Let's Encrypt) dans cPanel, puis indiquez l'adresse en `https://` dans *Réglages*, champ *Adresse du site* : c'est elle qu'ouvrent le QR code et les fiches PDF.

Le site fonctionne à la racine d'un domaine comme dans un sous-dossier. Le `.htaccess` de la racine envoie toutes les requêtes vers `public/` et refuse l'accès au code, à la configuration et aux fichiers déposés.

## Développement local

Avec XAMPP, placez le projet dans `htdocs/projetiut` puis ouvrez `http://localhost/projetiut/`. Sans Apache :

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Commandes utiles :

```bash
php tests/run.php                                  # tests, sans outil à installer
php bin/importer.php liste.xlsx --apercu           # analyse une liste sans rien enregistrer
php bin/importer.php liste.xlsx --remplacer        # publie une liste en ligne de commande
```

## Format des listes

L'en-tête peut se trouver sous un titre et une légende : il est cherché dans les 60 premières lignes. Les colonnes reconnues, dans n'importe quel ordre :

| Colonne | Intitulés acceptés |
|---|---|
| Nom (obligatoire) | Nom, Noms et prénoms, Étudiant |
| Effets | Effets, Effets disponibles, Documents, Pièces |
| Filière | Filière, Spécialité, Département, Parcours, Option |
| Année | Année, Année académique, Promotion, Session |
| Niveau | Niveau, Cycle, Diplôme, Classe, ou une colonne sans titre remplie de « DUT 1 », « Licence »… |
| Matricule | Matricule |
| N° | N°, Numéro |

Les effets s'écrivent comme sur les listes papier : `Sem2 + Sem3`, `S4 + ATT`, `ATT-DUT`, `ATT-Lic`, `CS`, `Niveau III`, `Relevé BTS`… Les variantes (`Sem 2`, `SeM2`, `Sem 2 et Sem 1`, `Sem6 (?)`) sont reconnues. Un code inconnu est conservé tel quel ; la scolarité peut préciser son sens dans les réglages.

Les anciens fichiers `.xls` doivent être enregistrés au format `.xlsx` depuis Excel avant d'être publiés comme liste. Un PDF déposé est publié comme document consultable.

## Organisation du code

```
app/
  Core/          routeur, requêtes, réponses, vues, session, CSRF, authentification
  Domain/        règles métier : noms, effets, années, filières, recherche
  Import/        lecture des classeurs et CSV, reconnaissance et import des listes
  Pdf/           générateur PDF, fiche d'état, affiche
  Qr/            encodeur de QR code
  Repositories/  accès aux données
  Controllers/   pages publiques et administration
  Views/         gabarits PHP
public/          seul dossier servi : index.php, CSS, JS, polices, images
database/        schéma SQL
storage/         fichiers déposés, cache, journaux (jamais servis directement)
tests/           tests unitaires
```

## Sécurité et données personnelles

- Les listes d'étudiants ne sont jamais versionnées : le dépôt ne contient aucun nom réel, les tests utilisent des noms inventés.
- Fichiers déposés rangés sous un nom aléatoire hors de la partie publique, contenu vérifié contre l'extension, servis par PHP après contrôle de leur publication.
- Requêtes préparées partout, jeton CSRF sur chaque action, politique de sécurité du contenu stricte, mots de passe hachés, huit essais de connexion au plus par quart d'heure.
- Les fiches individuelles ne sont pas indexées par les moteurs de recherche.

## Licences des ressources

- Police [Archivo](https://github.com/Omnibus-Type/Archivo), SIL Open Font License 1.1 (`resources/fonts/OFL.txt`).
- Icônes [Lucide](https://lucide.dev), licence ISC (`public/assets/img/LICENCE-icones.txt`).
- Logo de l'IUT de Douala, propriété de l'établissement.
