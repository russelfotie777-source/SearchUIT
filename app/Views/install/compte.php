<?php
/**
 * @var array<string, mixed> $valeurs
 * @var array<string, string> $erreurs
 */
$champ = static function (string $nom, string $libelle, string $type, string $aide, string $auto) use ($valeurs, $erreurs): string {
    $id = 'champ-' . $nom;
    $html = '<div class="champ"><label for="' . $id . '">' . e($libelle) . '</label>';
    $html .= '<input class="saisie" id="' . $id . '" name="' . e($nom) . '" type="' . $type . '" autocomplete="' . $auto . '" required'
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
<div class="message" role="status">
    <?= icone('valide') ?>
    <p>La base de données est prête.</p>
</div>

<h2>Compte de la scolarité</h2>
<p class="installation__intro">Ce compte sert à déposer les listes et les documents. Notez bien l'identifiant et le mot de passe.</p>

<form class="formulaire" method="post" action="<?= e(url('installation/compte')) ?>" novalidate>
    <?= csrf_champ() ?>
    <?= $champ('nom', 'Votre nom', 'text', 'Affiché dans l\'administration et dans l\'historique.', 'name') ?>
    <?= $champ('identifiant', 'Identifiant de connexion', 'text', 'Par exemple « scolarite ».', 'username') ?>
    <?= $champ('mot_de_passe', 'Mot de passe', 'password', 'Au moins 10 caractères.', 'new-password') ?>
    <?= $champ('confirmation', 'Confirmer le mot de passe', 'password', '', 'new-password') ?>
    <button class="btn btn--primaire btn--grand" type="submit">Terminer l'installation</button>
</form>
