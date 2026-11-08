<?php /** @var string|null $detail */ ?>
<section class="conteneur">
    <div class="erreur-page">
        <p class="erreur-page__code">Erreur 500</p>
        <h1>Le site rencontre un problème</h1>
        <p>L'erreur a été enregistrée. Réessayez dans quelques instants.</p>
        <a class="btn btn--primaire" href="<?= e(url('/')) ?>">Revenir à l'accueil</a>
        <?php if (!empty($detail)): ?>
            <pre class="erreur-page__detail"><?= e($detail) ?></pre>
        <?php endif; ?>
    </div>
</section>
