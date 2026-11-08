<?php
/**
 * Gabarit de l'administration, organisé comme Google Drive :
 * barre latérale (Nouveau, sections), barre de recherche, zone principale.
 *
 * @var string $contenu
 * @var string|null $titre
 * @var string|null $section
 * @var array<string, mixed> $utilisateur
 * @var int $espaceUtilise
 * @var int $nombreCorbeille
 */
use App\Core\Csrf;

$section ??= '';
$rechercheAction ??= url('admin/fichiers');
$rechercheTexte ??= 'Rechercher dans les fichiers';
$rechercheValeur ??= '';
$initiales = '';
foreach (array_slice(preg_split('/\s+/u', trim((string) ($utilisateur['nom'] ?? ''))) ?: [], 0, 2) as $mot) {
    $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
}
$sections = [
    'fichiers' => ['admin/fichiers', 'dossier', 'Fichiers'],
    'etudiants' => ['admin/etudiants', 'etudiants', 'Étudiants'],
    'historique' => ['admin/historique', 'historique', 'Historique'],
    'corbeille' => ['admin/corbeille', 'corbeille', 'Corbeille'],
    'reglages' => ['admin/reglages', 'reglages', 'Réglages'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(Csrf::jeton()) ?>">
    <title><?= e(($titre ?? 'Administration') . ' | Espace scolarité') ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/archivo-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin" data-base="<?= e(url('/')) ?>">
    <a class="lien-evitement" href="#admin-contenu">Aller au contenu</a>

    <div class="admin-coquille">
        <aside class="admin-barre" id="admin-barre" aria-label="Navigation de l'administration">
            <a class="admin-marque" href="<?= e(url('admin')) ?>">
                <img src="<?= e(asset('img/logo-iut-douala.png')) ?>" alt="" width="232" height="225">
                <span>Espace scolarité</span>
            </a>

            <details class="menu admin-nouveau">
                <summary class="admin-nouveau__bouton"><?= icone('plus') ?>Nouveau</summary>
                <div class="menu__panneau" role="menu">
                    <a class="menu__item" role="menuitem" href="<?= e(url('admin/fichiers', ['action' => 'televerser'])) ?>" data-action="televerser">
                        <?= icone('fichier-depot') ?>Importer des fichiers
                    </a>
                    <a class="menu__item" role="menuitem" href="<?= e(url('admin/fichiers', ['action' => 'nouveau-dossier'])) ?>" data-action="nouveau-dossier">
                        <?= icone('dossier-plus') ?>Nouveau dossier
                    </a>
                </div>
            </details>

            <nav class="admin-nav">
                <?php foreach ($sections as $cle => [$chemin, $icone, $libelle]): ?>
                    <a href="<?= e(url($chemin)) ?>"<?= $section === $cle ? ' aria-current="page"' : '' ?>>
                        <?= icone($icone) ?><span><?= e($libelle) ?></span>
                        <?php if ($cle === 'corbeille' && $nombreCorbeille > 0): ?><span class="admin-nav__compte"><?= $nombreCorbeille ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-espace">
                <?= icone('nuage-depot', 'icone icone--petite') ?>
                <span><?= e(taille_lisible($espaceUtilise)) ?> de fichiers déposés</span>
            </div>
        </aside>
        <div class="admin-voile" data-fermer-barre hidden></div>

        <div class="admin-principal">
            <header class="admin-entete">
                <button class="btn btn--discret btn--icone admin-entete__menu" type="button" aria-controls="admin-barre" aria-expanded="false" data-ouvrir-barre>
                    <?= icone('menu') ?><span class="masque-visuel">Menu</span>
                </button>

                <form class="admin-recherche" role="search" method="get" action="<?= e($rechercheAction) ?>">
                    <label class="masque-visuel" for="admin-recherche"><?= e($rechercheTexte) ?></label>
                    <?= icone('recherche') ?>
                    <input id="admin-recherche" name="recherche" type="search" value="<?= e($rechercheValeur) ?>" placeholder="<?= e($rechercheTexte) ?>" autocomplete="off">
                </form>

                <a class="btn btn--discret admin-entete__site" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                    <?= icone('lien-externe', 'icone icone--petite') ?><span>Voir le site</span>
                </a>

                <details class="menu menu--droite admin-compte">
                    <summary class="admin-compte__avatar" aria-label="Mon compte"><?= e($initiales ?: '?') ?></summary>
                    <div class="menu__panneau" role="menu">
                        <div class="admin-compte__identite">
                            <strong><?= e($utilisateur['nom'] ?? '') ?></strong>
                            <span><?= e($utilisateur['identifiant'] ?? '') ?></span>
                        </div>
                        <a class="menu__item" role="menuitem" href="<?= e(url('admin/reglages#compte')) ?>"><?= icone('cle') ?>Changer de mot de passe</a>
                        <form method="post" action="<?= e(url('admin/deconnexion')) ?>">
                            <?= csrf_champ() ?>
                            <button class="menu__item" role="menuitem" type="submit"><?= icone('deconnexion') ?>Se déconnecter</button>
                        </form>
                    </div>
                </details>
            </header>

            <main class="admin-contenu" id="admin-contenu" tabindex="-1">
                <?= $contenu ?>
            </main>
        </div>
    </div>

    <div class="toasts" aria-live="polite" aria-atomic="false" data-toasts>
        <?php foreach ($messages ?? [] as $message): ?>
            <div class="toast<?= $message['type'] === 'erreur' ? ' toast--erreur' : '' ?>" role="status" data-toast-initial><?= e($message['message']) ?></div>
        <?php endforeach; ?>
    </div>

    <script src="<?= e(asset('js/admin.js')) ?>"></script>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= e(asset($script)) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
