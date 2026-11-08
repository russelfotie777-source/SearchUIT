<?php
/**
 * @var string $contenu
 * @var string|null $titre
 * @var string|null $description
 * @var string|null $pageActive
 * @var array<string, string>|null $site
 */
$site ??= App\Repositories\Parametres::DEFAUTS;
$pageActive ??= '';
$description ??= 'Vérifiez si un relevé de notes ou une attestation vous attend au service de la scolarité de l\'IUT de Douala, et téléchargez votre fiche d\'état.';
$titreComplet = ($titre ?? '') !== '' && $titre !== 'Vos effets académiques'
    ? $titre . ' | Effets académiques, ' . $site['etablissement']
    : 'Effets académiques | ' . $site['etablissement'];
$liens = [
    'recherche' => ['/', 'Rechercher'],
    'liste' => ['/liste', 'Liste des effets'],
    'documents' => ['/documents', 'Documents'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titreComplet) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="theme-color" content="#ffffff">
    <?php if (!empty($robots)): ?>
        <meta name="robots" content="<?= e($robots) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= e($titreComplet) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= e(App\Core\Url::absolue('assets/img/icone-512.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/archivo-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ($styles ?? [] as $style): ?>
        <link rel="stylesheet" href="<?= e(asset($style)) ?>">
    <?php endforeach; ?>
</head>
<body>
    <a class="lien-evitement" href="#contenu">Aller au contenu</a>

    <div class="tricolore" aria-hidden="true"><span></span><span></span><span></span></div>
    <header class="entete">
        <div class="conteneur entete__barre">
            <a class="marque" href="<?= e(url('/')) ?>">
                <img src="<?= e(asset('img/logo-iut-douala.png')) ?>" alt="<?= e($site['etablissement']) ?>" width="232" height="225">
                <span class="marque__texte">
                    <strong>Effets académiques</strong>
                    <span><?= e($site['service']) ?></span>
                </span>
            </a>

            <button class="navigation__bouton" type="button" aria-expanded="false" aria-controls="navigation" data-menu>
                <?= icone('menu') ?>
                <span class="masque-visuel">Menu</span>
            </button>

            <nav class="navigation" id="navigation" aria-label="Navigation principale">
                <?php foreach ($liens as $cle => [$chemin, $libelle]): ?>
                    <a href="<?= e(url($chemin)) ?>"<?= $pageActive === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main id="contenu" tabindex="-1">
        <?php foreach ($messages ?? [] as $message): ?>
            <div class="conteneur messages-flash">
                <div class="message<?= $message['type'] === 'erreur' ? ' message--erreur' : '' ?>" role="status">
                    <?= icone($message['type'] === 'erreur' ? 'alerte' : 'valide') ?>
                    <p><?= e($message['message']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <?= $contenu ?>
    </main>

    <footer class="pied">
        <div class="conteneur pied__grille">
            <div class="pied__institution">
                <img src="<?= e(asset('img/logo-iut-douala.png')) ?>" alt="" width="232" height="225">
                <p>
                    <?= e($site['service']) ?>, <?= e($site['etablissement']) ?>.
                    <?php if ($site['retrait_lieu'] !== ''): ?>
                        <br><?= e($site['retrait_lieu']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <h2>Consulter</h2>
                <ul>
                    <?php foreach ($liens as [$chemin, $libelle]): ?>
                        <li><a href="<?= e(url($chemin)) ?>"><?= e($libelle) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h2>Contact</h2>
                <ul>
                    <?php if ($site['contact_telephone'] !== ''): ?>
                        <li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $site['contact_telephone'])) ?>"><?= e($site['contact_telephone']) ?></a></li>
                    <?php endif; ?>
                    <?php if ($site['contact_email'] !== ''): ?>
                        <li><a href="mailto:<?= e($site['contact_email']) ?>"><?= e($site['contact_email']) ?></a></li>
                    <?php endif; ?>
                    <li><a href="<?= e(url('admin')) ?>">Espace scolarité</a></li>
                </ul>
            </div>
        </div>
        <div class="conteneur">
            <div class="pied__bas">
                <span>&copy; <?= date('Y') ?> <?= e($site['etablissement']) ?></span>
                <?php if ($site['liste_mise_a_jour'] !== ''): ?>
                    <span>Liste mise à jour le <?= e(date_fr($site['liste_mise_a_jour'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script src="<?= e(asset('js/site.js')) ?>" defer></script>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= e(asset($script)) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>
