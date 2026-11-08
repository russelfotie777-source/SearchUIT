/*
 * Étudiants : remise des documents en un clic, correction, ajout,
 * suppression d'une ligne et annulation d'une liste publiée.
 */
(function () {
  "use strict";

  var Admin = window.Admin;

  function signalerErreur(erreur) {
    Admin.toast(erreur.message, { erreur: true });
  }

  // Le filtre de filière s'applique dès qu'il change.
  var filtre = document.querySelector("[data-filtre-auto]");
  if (filtre) {
    filtre.querySelector("select").addEventListener("change", function () {
      filtre.submit();
    });
  }

  function dateDuJour() {
    var d = new Date();
    return ("0" + d.getDate()).slice(-2) + "/" + ("0" + (d.getMonth() + 1)).slice(-2) + "/" + d.getFullYear();
  }

  /** Met la ligne à jour sans recharger la page. */
  function afficherStatut(ligne, retire) {
    ligne.classList.toggle("est-retire", retire);
    ligne.querySelector("[data-statut]").innerHTML = retire
      ? '<span class="statut statut--retire">Retiré le ' + dateDuJour() + "</span>"
      : '<span class="statut statut--disponible">Disponible</span>';
    Array.prototype.forEach.call(ligne.querySelectorAll(".puce"), function (puce) {
      puce.classList.toggle("puce--neutre", retire);
    });
    var bouton = ligne.querySelector("[data-action='retirer'], [data-action='remettre']");
    bouton.setAttribute("data-action", retire ? "remettre" : "retirer");
    bouton.className = retire ? "btn btn--discret btn--petit" : "btn btn--secondaire btn--petit";
    bouton.textContent = retire ? "Annuler le retrait" : "Marquer comme retiré";
  }

  function changerStatut(ligne, action) {
    var id = ligne.getAttribute("data-etudiant-id");
    return Admin.appeler("admin/etudiants/" + id + "/" + action).then(function (reponse) {
      afficherStatut(ligne, action === "retirer");
      return reponse;
    });
  }

  /** Formulaire d'ajout ou de correction d'un étudiant. */
  function formulaire(valeurs) {
    var champs = [
      ["nom", "Nom et prénoms", "Comme sur la liste, par exemple « ESSOMBA RUTH »", true],
      ["effets", "Documents disponibles", "Codes séparés par +, par exemple « S1 + S2 + ATT-DUT »", true],
      ["filiere", "Filière", "Par exemple « GEII » ou « GI2 FA »", false],
      ["annee", "Année académique", "Par exemple « 2025-2026 »", false],
      ["niveau", "Niveau", "Par exemple « DUT 2 » ou « Licence »", false],
      ["matricule", "Matricule", "Facultatif", false],
    ];
    var conteneur = document.createElement("div");
    conteneur.className = "formulaire-etudiant";
    champs.forEach(function (champ) {
      var bloc = document.createElement("div");
      bloc.className = "champ" + (champ[0] === "nom" || champ[0] === "effets" ? " champ--large" : "");
      var etiquette = document.createElement("label");
      etiquette.htmlFor = "etudiant-" + champ[0];
      etiquette.textContent = champ[1];
      var saisie = document.createElement("input");
      saisie.className = "saisie";
      saisie.id = "etudiant-" + champ[0];
      saisie.name = champ[0];
      saisie.value = (valeurs && valeurs[champ[0]]) || "";
      saisie.placeholder = champ[2];
      saisie.required = champ[3];
      bloc.appendChild(etiquette);
      bloc.appendChild(saisie);
      conteneur.appendChild(bloc);
    });
    return conteneur;
  }

  function ouvrirFormulaire(titre, valeurs, confirmer) {
    var contenu = formulaire(valeurs);
    return Admin.dialogue({
      titre: titre,
      contenu: contenu,
      confirmer: confirmer,
      valeur: function () {
        var donnees = {};
        Array.prototype.forEach.call(contenu.querySelectorAll("input"), function (saisie) {
          donnees[saisie.name] = saisie.value.trim();
        });
        return donnees;
      },
    });
  }

  document.addEventListener("click", function (evenement) {
    var bouton = evenement.target.closest("[data-action]");
    if (!bouton) {
      return;
    }
    var action = bouton.getAttribute("data-action");
    var ligne = bouton.closest("[data-etudiant-id]");

    if (action === "ajouter-etudiant") {
      ouvrirFormulaire("Ajouter un étudiant", null, "Ajouter").then(function (donnees) {
        if (donnees) {
          Admin.appeler("admin/etudiants", donnees).then(function (reponse) {
            Admin.toast(reponse.message);
            setTimeout(function () {
              window.location.reload();
            }, 600);
          }, signalerErreur);
        }
      });
      return;
    }

    if (action === "annuler-import") {
      var publication = bouton.closest("[data-import-id]");
      Admin.dialogue({
        titre: "Annuler cette publication ?",
        texte: "Les étudiants de « " + publication.getAttribute("data-nom") + " » ne seront plus trouvables sur le site. Le fichier reste dans Fichiers et peut être publié de nouveau.",
        confirmer: "Annuler la publication",
        annuler: "Garder",
        danger: true,
      }).then(function (ok) {
        if (ok) {
          Admin.appeler("admin/imports/" + publication.getAttribute("data-import-id") + "/annuler").then(function (reponse) {
            Admin.toast(reponse.message);
            setTimeout(function () {
              window.location.reload();
            }, 800);
          }, signalerErreur);
        }
      });
      return;
    }

    if (!ligne) {
      return;
    }
    var nom = ligne.getAttribute("data-nom");

    if (action === "retirer") {
      bouton.disabled = true;
      changerStatut(ligne, "retirer").then(function (reponse) {
        Admin.toast(reponse.message, {
          action: {
            libelle: "Annuler",
            faire: function () {
              changerStatut(ligne, "remettre").catch(signalerErreur);
            },
          },
        });
      }, signalerErreur).then(function () {
        bouton.disabled = false;
      });
    } else if (action === "remettre") {
      changerStatut(ligne, "remettre").then(function (reponse) {
        Admin.toast(reponse.message);
      }, signalerErreur);
    } else if (action === "modifier-etudiant") {
      ouvrirFormulaire("Corriger « " + nom + " »", JSON.parse(ligne.getAttribute("data-edition")), "Enregistrer").then(function (donnees) {
        if (donnees) {
          Admin.appeler("admin/etudiants/" + ligne.getAttribute("data-etudiant-id") + "/modifier", donnees).then(function (reponse) {
            Admin.toast(reponse.message);
            setTimeout(function () {
              window.location.reload();
            }, 600);
          }, signalerErreur);
        }
      });
    } else if (action === "supprimer-etudiant") {
      Admin.dialogue({
        titre: "Retirer « " + nom + " » de la liste ?",
        texte: "Cette ligne ne sera plus trouvable sur le site. Pour indiquer que les documents ont été remis, utilisez plutôt « Marquer comme retiré ».",
        confirmer: "Retirer de la liste",
        danger: true,
      }).then(function (ok) {
        if (ok) {
          Admin.appeler("admin/etudiants/" + ligne.getAttribute("data-etudiant-id") + "/supprimer").then(function (reponse) {
            ligne.remove();
            Admin.toast(reponse.message);
          }, signalerErreur);
        }
      });
    }
  });
})();
