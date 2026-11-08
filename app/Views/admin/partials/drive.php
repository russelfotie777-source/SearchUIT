<?php
/**
 * Contenu d'un dossier : fil d'Ariane, sous-dossiers et fichiers.
 * Rendu une première fois avec la page, puis rechargé seul après chaque action.
 *
 * @var int|null $dossierId
 * @var list<array<string, mixed>> $chemin
 * @var string $recherche
 * @var list<array<string, mixed>> $dossiers
 * @var list<array<string, mixed>> $fichiers
 */
use App\Repositories\Fichiers;
use App\Storage\Stockage;

$tousLesDossiers = Fichiers::tousLesDossiers();
?>
<div class="admin-titre">
    <?php if ($recherche !== ''): ?>
        <h1>Résultats pour « <?= e($recherche) ?> »</h1>
    <?php else: ?>
        <nav class="fil-drive" aria-label="Emplacement">
            <ol>
                <li>
                    <?php if ($chemin === []): ?>
                        <h1 aria-current="page">Fichiers</h1>
                    <?php else: ?>
                        <a href="<?= e(url('admin/fichiers')) ?>" data-cible-depot="">Fichiers</a>
                    <?php endif; ?>
                </li>
                <?php foreach ($chemin as $i => $etape): ?>
                    <li>
                        <?= icone('chevron-droite', 'icone icone--petite') ?>
                        <?php if ($i === count($chemin) - 1): ?>
                            <h1 aria-current="page"><?= e($etape['nom']) ?></h1>
                        <?php else: ?>
                            <a href="<?= e(url('admin/fichiers', ['dossier' => $etape['id']])) ?>" data-cible-depot="<?= (int) $etape['id'] ?>"><?= e($etape['nom']) ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <div class="admin-titre__actions">
        <button class="btn btn--secondaire" type="button" data-action="nouveau-dossier"><?= icone('dossier-plus', 'icone icone--petite') ?><span>Nouveau dossier</span></button>
        <button class="btn btn--primaire" type="button" data-action="televerser"><?= icone('televerser', 'icone icone--petite') ?><span>Importer des fichiers</span></button>
        <div class="vue-bascule" role="group" aria-label="Affichage">
            <button class="vue-bascule__bouton" type="button" data-vue="liste" aria-pressed="true" aria-label="Afficher en liste"><?= icone('liste', 'icone icone--petite') ?></button>
            <button class="vue-bascule__bouton" type="button" data-vue="grille" aria-pressed="false" aria-label="Afficher en grille"><?= icone('grille', 'icone icone--petite') ?></button>
        </div>
    </div>
</div>

<?php if ($dossiers === [] && $fichiers === []): ?>
    <?php if ($recherche !== ''): ?>
        <div class="admin-vide">
            <?= icone('recherche') ?>
            <h2>Aucun fichier ni dossier ne porte ce nom</h2>
            <p>Vérifiez l'orthographe, ou cherchez une partie du nom seulement.</p>
            <a class="btn btn--secondaire" href="<?= e(url('admin/fichiers')) ?>">Revenir aux fichiers</a>
        </div>
    <?php else: ?>
        <button class="drive-vide" type="button" data-action="televerser">
            <?= icone('nuage-depot') ?>
            <strong>Glissez vos fichiers ici</strong>
            <span>Listes d'étudiants en Excel ou CSV, procès-verbaux et communiqués en PDF. Ce que vous déposez est visible sur le site, sauf si vous le masquez.</span>
            <span class="btn btn--primaire">Choisir des fichiers</span>
        </button>
    <?php endif; ?>
<?php else: ?>
    <?php if ($dossiers !== []): ?>
        <h2 class="drive__section">Dossiers</h2>
        <ul class="drive__dossiers">
            <?php foreach ($dossiers as $dossier): ?>
                <li class="dossier-carte" data-dossier-id="<?= (int) $dossier['id'] ?>" data-nom="<?= e($dossier['nom']) ?>" data-cible-depot="<?= (int) $dossier['id'] ?>" draggable="true">
                    <a class="dossier-carte__lien" href="<?= e(url('admin/fichiers', ['dossier' => $dossier['id']])) ?>">
                        <?= icone('dossier') ?>
                        <span class="dossier-carte__nom"><?= e($dossier['nom']) ?></span>
                        <?php if ($recherche === ''): ?>
                            <span class="dossier-carte__compte"><?= (int) $dossier['elements'] === 0 ? 'Vide' : e(pluriel((int) $dossier['elements'], 'élément')) ?></span>
                        <?php endif; ?>
                    </a>
                    <details class="menu menu--droite">
                        <summary class="btn btn--discret btn--icone" aria-label="Actions pour <?= e($dossier['nom']) ?>"><?= icone('menu-points') ?></summary>
                        <div class="menu__panneau" role="menu">
                            <a class="menu__item" role="menuitem" href="<?= e(url('admin/fichiers', ['dossier' => $dossier['id']])) ?>"><?= icone('dossier') ?>Ouvrir</a>
                            <button class="menu__item" role="menuitem" type="button" data-action="renommer-dossier"><?= icone('modifier') ?>Renommer</button>
                            <button class="menu__item" role="menuitem" type="button" data-action="deplacer-dossier"><?= icone('deplacer') ?>Déplacer</button>
                            <div class="menu__separateur"></div>
                            <button class="menu__item menu__item--danger" role="menuitem" type="button" data-action="supprimer-dossier"><?= icone('corbeille') ?>Placer dans la corbeille</button>
                        </div>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($fichiers !== []): ?>
        <h2 class="drive__section">Fichiers</h2>
        <div class="drive__fichiers" role="table" aria-label="Fichiers">
            <div class="drive__entete" role="row">
                <span role="columnheader">Nom</span>
                <span role="columnheader">Sur le site</span>
                <span role="columnheader">Ajouté</span>
                <span role="columnheader">Taille</span>
                <span role="columnheader"><span class="masque-visuel">Actions</span></span>
            </div>
            <?php foreach ($fichiers as $fichier): ?>
                <?php
                $extension = (string) $fichier['extension'];
                $public = (int) $fichier['public'] === 1;
                $ouvrir = url('admin/fichiers/' . $fichier['id'] . '/ouvrir');
                $emplacement = $recherche !== ''
                    ? implode(' / ', array_column(Fichiers::cheminDossiers($fichier['dossier_id'] === null ? null : (int) $fichier['dossier_id'], $tousLesDossiers), 'nom')) ?: 'Fichiers'
                    : null;
                ?>
                <div class="fichier" role="row" data-fichier-id="<?= (int) $fichier['id'] ?>" data-nom="<?= e($fichier['nom']) ?>" data-public="<?= $public ? '1' : '0' ?>" draggable="true">
                    <span class="fichier__nom" role="cell">
                        <span class="type-fichier type-fichier--<?= e(Stockage::famille($extension)) ?>" aria-hidden="true">
                            <?= icone(Stockage::famille($extension) === 'tableur' ? 'fichier-tableur' : 'fichier-texte') ?>
                        </span>
                        <span class="fichier__texte">
                            <a href="<?= e($ouvrir) ?>" target="_blank" rel="noopener"><?= e($fichier['nom']) ?></a>
                            <?php if ($emplacement !== null): ?><small><?= icone('dossier', 'icone icone--petite') ?><?= e($emplacement) ?></small><?php endif; ?>
                        </span>
                    </span>
                    <span role="cell">
                        <button class="visibilite<?= $public ? ' est-publique' : '' ?>" type="button" data-action="basculer-visibilite" aria-pressed="<?= $public ? 'true' : 'false' ?>"
                                title="<?= $public ? 'Cliquez pour masquer ce fichier du site' : 'Cliquez pour publier ce fichier sur le site' ?>">
                            <?= icone($public ? 'visible' : 'masque', 'icone icone--petite') ?>
                            <span><?= $public ? 'Visible' : 'Masqué' ?></span>
                        </button>
                    </span>
                    <span class="fichier__date" role="cell"><?= e(date_relative((string) $fichier['cree_le'])) ?></span>
                    <span class="fichier__taille" role="cell"><?= e(taille_lisible((int) $fichier['taille'])) ?></span>
                    <span class="fichier__actions" role="cell">
                        <details class="menu menu--droite">
                            <summary class="btn btn--discret btn--icone" aria-label="Actions pour <?= e($fichier['nom']) ?>"><?= icone('menu-points') ?></summary>
                            <div class="menu__panneau" role="menu">
                                <a class="menu__item" role="menuitem" href="<?= e($ouvrir) ?>" target="_blank" rel="noopener"><?= icone('lien-externe') ?>Ouvrir</a>
                                <a class="menu__item" role="menuitem" href="<?= e($ouvrir . '?telecharger=1') ?>"><?= icone('telecharger') ?>Télécharger</a>
                                <button class="menu__item" role="menuitem" type="button" data-action="renommer-fichier"><?= icone('modifier') ?>Renommer</button>
                                <button class="menu__item" role="menuitem" type="button" data-action="deplacer-fichier"><?= icone('deplacer') ?>Déplacer</button>
                                <button class="menu__item" role="menuitem" type="button" data-action="basculer-visibilite"><?= icone($public ? 'masque' : 'visible') ?><?= $public ? 'Masquer du site' : 'Publier sur le site' ?></button>
                                <div class="menu__separateur"></div>
                                <button class="menu__item menu__item--danger" role="menuitem" type="button" data-action="supprimer-fichier"><?= icone('corbeille') ?>Placer dans la corbeille</button>
                            </div>
                        </details>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
