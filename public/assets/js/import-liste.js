/*
 * Quand un fichier Excel ou CSV déposé contient une liste d'étudiants,
 * propose de la publier dans la recherche : aperçu, puis remplacement
 * de la liste en ligne ou ajout.
 */
(function () {
  "use strict";

  var Admin = window.Admin;
  var fileDialogues = Promise.resolve();

  function element(balise, classe, texte) {
    var noeud = document.createElement(balise);
    if (classe) {
      noeud.className = classe;
    }
    if (texte !== undefined) {
      noeud.textContent = texte;
    }
    return noeud;
  }

  function nombre(n) {
    return n.toLocaleString("fr-FR");
  }

  function choix(valeur, titre, description, coche) {
    var etiquette = element("label", "import-liste__choix");
    var radio = element("input");
    radio.type = "radio";
    radio.name = "mode";
    radio.value = valeur;
    radio.checked = coche;
    var texte = element("span");
    texte.appendChild(element("strong", "", titre));
    texte.appendChild(element("span", "", description));
    etiquette.appendChild(radio);
    etiquette.appendChild(texte);
    return etiquette;
  }

  function contenuDialogue(liste) {
    var contenu = element("div", "import-liste");

    contenu.appendChild(
      element(
        "p",
        "import-liste__resume",
        "« " + liste.nom_fichier + " » contient " + nombre(liste.etudiants) + " étudiant" + (liste.etudiants > 1 ? "s" : "") +
          (liste.ignorees ? ", et " + liste.ignorees + " ligne" + (liste.ignorees > 1 ? "s vides ou incomplètes ignorées" : " vide ou incomplète ignorée") : "") + "."
      )
    );
    contenu.appendChild(element("p", "import-liste__colonnes", "Colonnes reconnues : " + liste.colonnes.join(", ") + "."));

    liste.avertissements.forEach(function (avertissement) {
      contenu.appendChild(element("p", "import-liste__avertissement", avertissement));
    });

    var tableau = element("table", "import-liste__apercu");
    var legende = element("caption", "", "Premières lignes");
    tableau.appendChild(legende);
    var entete = element("tr");
    ["Nom", "Effets", "Filière", "Année"].forEach(function (titre) {
      entete.appendChild(element("th", "", titre));
    });
    var thead = element("thead");
    thead.appendChild(entete);
    tableau.appendChild(thead);
    var tbody = element("tbody");
    liste.apercu.forEach(function (ligne) {
      var tr = element("tr");
      [ligne.nom, ligne.effets, ligne.filiere, ligne.annee].forEach(function (valeur) {
        tr.appendChild(element("td", "", valeur || "—"));
      });
      tbody.appendChild(tr);
    });
    tableau.appendChild(tbody);
    var defilement = element("div", "import-liste__defilement");
    defilement.appendChild(tableau);
    contenu.appendChild(defilement);

    if (liste.en_ligne > 0) {
      var groupe = element("fieldset", "import-liste__modes");
      groupe.appendChild(element("legend", "", "Que faire de la liste déjà en ligne ?"));
      groupe.appendChild(
        choix(
          "remplacement",
          "La remplacer par celle-ci",
          "Les " + nombre(liste.en_ligne) + " étudiants en ligne sont remplacés. Les fiches marquées comme retirées le restent.",
          true
        )
      );
      groupe.appendChild(
        choix(
          "ajout",
          "Ajouter cette liste à la suite",
          "Pour une liste complémentaire. Un étudiant déjà présent n'est pas ajouté deux fois.",
          false
        )
      );
      contenu.appendChild(groupe);
    }
    return contenu;
  }

  function publier(liste) {
    fileDialogues = fileDialogues.then(function () {
      var contenu = contenuDialogue(liste);
      return Admin.dialogue({
        titre: "Liste d'étudiants reconnue",
        contenu: contenu,
        confirmer: "Publier la liste",
        annuler: "Plus tard",
        valeur: function () {
          var coche = contenu.querySelector("input[name=mode]:checked");
          return coche ? coche.value : "remplacement";
        },
      }).then(function (mode) {
        if (!mode) {
          Admin.toast("Le fichier est enregistré. Publiez la liste plus tard depuis le menu du fichier.");
          return;
        }
        var fermer = Admin.toast("Publication de la liste en cours…", { duree: 120000 });
        return Admin.appeler("admin/imports", { fichier_id: liste.fichier_id, mode: mode }).then(
          function (reponse) {
            fermer();
            Admin.toast(reponse.message, { duree: 10000 });
          },
          function (erreur) {
            fermer();
            Admin.toast(erreur.message, { erreur: true, duree: 10000 });
          }
        );
      });
    });
  }

  document.addEventListener("drive:importe", function (evenement) {
    var reponse = evenement.detail.reponse;
    if (reponse.liste) {
      publier(reponse.liste);
    }
  });

  document.addEventListener("click", function (evenement) {
    var bouton = evenement.target.closest("[data-action='publier-liste']");
    if (!bouton) {
      return;
    }
    var ligne = bouton.closest("[data-fichier-id]");
    var patience = Admin.toast("Lecture du fichier…", { duree: 60000 });
    Admin.appeler("admin/imports/analyser", { fichier_id: ligne.getAttribute("data-fichier-id") }).then(
      function (reponse) {
        patience();
        publier(reponse.liste);
      },
      function (erreur) {
        patience();
        Admin.toast(erreur.message, { erreur: true, duree: 9000 });
      }
    );
  });
})();
