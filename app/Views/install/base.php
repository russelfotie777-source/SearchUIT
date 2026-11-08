<?php
/**
 * @var list<array{libelle: string, ok: bool, aide: string}> $verifications
 * @var array<string, mixed> $valeurs
 * @var array<string, string> $erreurs
 */
$bloquant = in_array(false, array_column($verifications, 'ok'), true);
$champ = static function (string $nom, string $libelle, string $type = 'text', string $aide = '', string $auto = 'off') use ($valeurs, $erreurs): string {
    $id = 'champ-' . $nom;
    $html = '<div class="champ"><label for="' . $id . '">' . e($libelle) . '</label>';
    $html .= '<input class="saisie" id="' . $id . '" name="' . e($nom) . '" type="' . $type . '" autocomplete="' . $auto . '"'
        . ($type !== 'password' ? ' value="' . e($valeurs[$nom] ?? '') . '"' : '')
        . (isset($erreurs[$nom]) ? ' aria-invalid="true" aria-describedby="' . $id . '-erreur"' : '') . '>';
    if (isset($erreurs[$nom])) {
        $html .= '<p class="champ__erreur" id="' . $id . '-erreur">' . e($erreurs[$nom]) . '</p>';
    } elseif ($aide !== '') {
        $html .= '<p class="champ__aide">' . e($aide) . '</p>';
    }
    return $html . '</div>';
};
?>
<h2>Vérification de l'hébergement</h2>
<ul class="verifications">
    <?php foreach ($verifications as $v): ?>
        <li class="<?= $v['ok'] ? 'est-ok' : 'est-ko' ?>">
            <?= icone($v['ok'] ? 'valide' : 'alerte') ?>
            <div>
                <span><?= e($v['libelle']) ?></span>
                <?php if (!$v['ok']): ?><small><?= e($v['aide']) ?></small><?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<h2>Connexion à la base de données</h2>
<p class="installation__intro">Créez une base MySQL et son utilisateur dans cPanel, rubrique « MySQL Databases », puis recopiez les informations ici.</p>

<?php if (isset($erreurs['connexion'])): ?>
    <div class="message message--erreur" role="alert">
        <?= icone('alerte') ?>
        <p><?= e($erreurs['connexion']) ?></p>
    </div>
<?php endif; ?>

<form class="formulaire" method="post" action="<?= e(url('installation/base')) ?>" novalidate>
    <?= csrf_champ() ?>
    <div class="formulaire__ligne">
        <?= $champ('host', 'Serveur') ?>
        <?= $champ('port', 'Port', 'number') ?>
    </div>
    <?= $champ('name', 'Nom de la base', 'text', 'Sur iFastNet, il commence souvent par votre identifiant cPanel, par exemple « compte_effets ».') ?>
    <?= $champ('user', 'Utilisateur de la base') ?>
    <?= $champ('pass', 'Mot de passe de la base', 'password', '', 'new-password') ?>
    <?= $champ('url_base', 'Adresse du site (facultatif)', 'url', 'Utilisée dans les QR codes et les fiches PDF. Laissez vide pour la détecter.') ?>

    <button class="btn btn--primaire btn--grand" type="submit"<?= $bloquant ? ' disabled' : '' ?>>Créer les tables</button>
    <?php if ($bloquant): ?>
        <p class="champ__erreur">Corrigez d'abord les points signalés plus haut.</p>
    <?php endif; ?>
</form>
