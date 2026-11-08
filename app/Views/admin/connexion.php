<?php
/**
 * @var string $retour
 * @var string $identifiant
 * @var string|null $erreur
 * @var list<array{type: string, message: string}> $messages
 */
?>
<div class="connexion__carte">
    <img class="connexion__logo" src="<?= e(asset('img/logo-iut-douala.png')) ?>" alt="IUT de Douala" width="232" height="225">
    <h1>Espace scolarité</h1>
    <p class="connexion__intro">Connectez-vous pour publier les listes et les documents.</p>

    <?php foreach ($messages ?? [] as $message): ?>
        <div class="message" role="status"><?= icone('valide') ?><p><?= e($message['message']) ?></p></div>
    <?php endforeach; ?>

    <?php if ($erreur !== null): ?>
        <div class="message message--erreur" role="alert"><?= icone('alerte') ?><p><?= e($erreur) ?></p></div>
    <?php endif; ?>

    <form class="formulaire" method="post" action="<?= e(url('admin/connexion')) ?>">
        <?= csrf_champ() ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
        <div class="champ">
            <label for="identifiant">Identifiant</label>
            <input class="saisie" id="identifiant" name="identifiant" type="text" value="<?= e($identifiant) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required <?= $identifiant === '' ? 'autofocus' : '' ?>>
        </div>
        <div class="champ">
            <label for="mot_de_passe">Mot de passe</label>
            <div class="champ-mot-de-passe">
                <input class="saisie" id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required <?= $identifiant !== '' ? 'autofocus' : '' ?>>
                <button type="button" class="champ-mot-de-passe__voir" aria-label="Afficher le mot de passe" aria-pressed="false" data-voir-mdp><?= icone('visible', 'icone icone--petite') ?></button>
            </div>
        </div>
        <button class="btn btn--primaire btn--grand" type="submit">Se connecter</button>
    </form>

    <a class="connexion__retour" href="<?= e(url('/')) ?>"><?= icone('retour', 'icone icone--petite') ?>Retour au site</a>
</div>
<script>
    document.querySelector("[data-voir-mdp]").addEventListener("click", function () {
        var champ = document.getElementById("mot_de_passe");
        var visible = champ.type === "text";
        champ.type = visible ? "password" : "text";
        this.setAttribute("aria-pressed", String(!visible));
        this.setAttribute("aria-label", visible ? "Afficher le mot de passe" : "Masquer le mot de passe");
    });
</script>
