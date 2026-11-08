<?php
/**
 * @var string $contenu
 * @var string $etape
 */
$etapes = ['base' => 'Base de données', 'compte' => 'Compte de la scolarité'];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Installation | Effets académiques</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon-32.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/installation.css')) ?>">
</head>
<body class="installation">
    <div class="tricolore" aria-hidden="true"><span></span><span></span><span></span></div>
    <main class="installation__colonne">
        <img class="installation__logo" src="<?= e(asset('img/logo-iut-douala.png')) ?>" alt="IUT de Douala" width="232" height="225">
        <h1>Installer le site des effets académiques</h1>

        <ol class="etapes" aria-label="Étapes de l'installation">
            <?php $passee = true; ?>
            <?php foreach ($etapes as $cle => $libelle): ?>
                <?php $courante = $cle === $etape; if ($courante) { $passee = false; } ?>
                <li class="etapes__item<?= $courante ? ' est-courante' : ($passee ? ' est-faite' : '') ?>"<?= $courante ? ' aria-current="step"' : '' ?>>
                    <span class="etapes__pastille"><?= $passee && !$courante ? icone('coche', 'icone icone--petite') : array_search($cle, array_keys($etapes), true) + 1 ?></span>
                    <?= e($libelle) ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="installation__carte">
            <?= $contenu ?>
        </div>
    </main>
</body>
</html>
