<?php
/**
 * @var array<string, mixed> $etudiant
 * @var list<array{code: string, court: string, complet: string, note: ?string}> $effets
 * @var list<array<string, mixed>> $homonymes
 * @var array<string, string> $site
 */
use App\Domain\NomEtudiant;

$retire = $etudiant['statut'] === 'retire';
$retireLe = $retire && !empty($etudiant['retire_le']) ? date_fr((string) $etudiant['retire_le']) : null;
$annotation = NomEtudiant::annotationLisible($etudiant['annotation'] ?? null);
$adressePdf = url('fiche/' . $etudiant['reference'] . '/pdf');
?>
<div class="conteneur fiche-page">
    <nav class="fil" aria-label="Fil d'Ariane">
        <a href="<?= e(url('/')) ?>" data-retour><?= icone('retour', 'icone icone--petite') ?>Retour à la recherche</a>
    </nav>

    <div class="fiche-page__grille">
        <article class="fiche<?= $retire ? ' est-retire' : '' ?>" aria-labelledby="nom-etudiant">
            <header class="fiche__entete">
                <img src="<?= e(asset('img/logo-iut-douala.png')) ?>" alt="" width="232" height="225">
                <div>
                    <strong><?= e($site['etablissement']) ?></strong>
                    <span><?= e($site['service']) ?></span>
                </div>
                <p class="fiche__reference">
                    <span>Référence</span>
                    <strong><?= e($etudiant['reference']) ?></strong>
                </p>
            </header>

            <div class="fiche__identite">
                <p class="fiche__type">Fiche d'état des effets académiques</p>
                <h1 class="fiche__nom" id="nom-etudiant"><?= e($etudiant['nom']) ?></h1>
                <p class="fiche__resume">
                    <?php if ($retire): ?>
                        Ces documents ont été retirés<?= $retireLe ? ' le ' . e($retireLe) : '' ?>.
                    <?php elseif ($effets === []): ?>
                        Votre nom figure sur la liste, sans précision sur les documents.
                    <?php else: ?>
                        <?= count($effets) > 1 ? 'Ces documents vous attendent' : 'Ce document vous attend' ?> au <?= e(mb_strtolower($site['service'])) ?>.
                    <?php endif; ?>
                </p>

                <div class="tampon<?= $retire ? ' tampon--retire' : '' ?>" aria-hidden="true">
                    <span class="tampon__mot"><?= $retire ? 'Retiré' : 'Disponible' ?></span>
                    <span class="tampon__ligne"><?= $retire && !empty($etudiant['retire_le']) ? 'le ' . e(date('d/m/Y', strtotime((string) $etudiant['retire_le']))) : 'à la scolarité' ?></span>
                </div>
            </div>

            <dl class="fiche__infos">
                <div><dt>Filière</dt><dd><?= e($etudiant['filiere'] ?: '—') ?></dd></div>
                <div><dt>Année académique</dt><dd><?= e($etudiant['annee'] ?: '—') ?></dd></div>
                <div><dt>Niveau</dt><dd><?= e($etudiant['niveau'] ?: '—') ?></dd></div>
                <div><dt>N° sur la liste</dt><dd><?= $etudiant['numero'] !== null ? e((string) $etudiant['numero']) : '—' ?></dd></div>
            </dl>

            <?php if ($effets !== []): ?>
                <table class="fiche__effets">
                    <caption class="masque-visuel">Documents à votre nom</caption>
                    <thead>
                        <tr>
                            <th scope="col">Document</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($effets as $effet): ?>
                            <tr>
                                <td>
                                    <span class="puce<?= $retire ? ' puce--neutre' : '' ?>"><?= e($effet['court']) ?></span>
                                    <span class="fiche__document">
                                        <?= e($effet['complet'] === $effet['court'] ? 'Document « ' . $effet['court'] . ' », à préciser au guichet' : $effet['complet']) ?>
                                        <?php if (!empty($effet['note'])): ?>
                                            <small><?= e($effet['note'] === '?' ? 'À vérifier au guichet' : ucfirst($effet['note'])) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="statut <?= $retire ? 'statut--retire' : 'statut--disponible' ?>"><?= $retire ? 'Retiré' : 'Disponible' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($annotation !== null): ?>
                <p class="fiche__annotation"><?= icone('info', 'icone icone--petite') ?><?= e($annotation) ?></p>
            <?php endif; ?>
        </article>

        <aside class="fiche-actions">
            <a class="btn btn--primaire btn--grand" href="<?= e($adressePdf) ?>" download>
                <?= icone('telecharger') ?>Télécharger la fiche
            </a>
            <div class="fiche-actions__secondaires">
                <a class="btn btn--secondaire" href="<?= e(url('fiche/' . $etudiant['reference'] . '/pdf', ['apercu' => 1])) ?>" target="_blank" rel="noopener">
                    <?= icone('imprimer') ?>Imprimer
                </a>
                <button class="btn btn--secondaire" type="button" data-partager data-titre="Fiche d'état, <?= e($etudiant['nom']) ?>">
                    <?= icone('lien-externe') ?><span data-partager-libelle>Partager</span>
                </button>
            </div>

            <?php if (!$retire): ?>
                <section class="fiche-actions__bloc" aria-labelledby="titre-retirer">
                    <h2 id="titre-retirer">Retirer vos documents</h2>
                    <?php if ($site['retrait_lieu'] === '' && $site['retrait_horaires'] === '' && $site['retrait_pieces'] === ''): ?>
                        <p>Présentez cette fiche au <?= e(mb_strtolower($site['service'])) ?>.</p>
                    <?php else: ?>
                        <dl class="pratique">
                            <?php if ($site['retrait_lieu'] !== ''): ?>
                                <div><dt><?= icone('lieu', 'icone icone--petite') ?>Lieu</dt><dd><?= e($site['retrait_lieu']) ?></dd></div>
                            <?php endif; ?>
                            <?php if ($site['retrait_horaires'] !== ''): ?>
                                <div><dt><?= icone('horloge', 'icone icone--petite') ?>Horaires</dt><dd><?= nl2br(e($site['retrait_horaires'])) ?></dd></div>
                            <?php endif; ?>
                            <?php if ($site['retrait_pieces'] !== ''): ?>
                                <div><dt><?= icone('fichier-texte', 'icone icone--petite') ?>À présenter</dt><dd><?= e($site['retrait_pieces']) ?></dd></div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <p class="fiche-actions__note">
                Les homonymes sont fréquents&nbsp;: vérifiez la filière et l'année avant de vous déplacer.
            </p>
        </aside>
    </div>

    <?php if ($homonymes !== []): ?>
        <section class="fiche-homonymes" aria-labelledby="titre-homonymes">
            <h2 id="titre-homonymes">Autres lignes au même nom</h2>
            <ol class="resultats__liste">
                <?php foreach ($homonymes as $i => $homonyme): ?>
                    <?= App\Core\View::rendre('partials/resultat', ['etudiant' => $homonyme, 'mots' => [], 'rang' => $i]) ?>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
</div>
