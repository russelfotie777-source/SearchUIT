/*
 * Espace fichiers : glisser-déposer depuis l'ordinateur, file d'importation
 * avec progression, dossiers, publication, déplacement et corbeille.
 * S'appuie sur window.Admin (admin.js).
 */
(function () {
  "use strict";

  var Admin = window.Admin;
  var drive = document.querySelector("[data-drive]");
  var corbeilleOuverte = !drive && document.querySelector(".corbeille, [data-action='vider-corbeille']");

  // ---------------------------------------------------------------- Outils

  function ligneDe(element) {
    return element.closest("[data-fichier-id], [data-dossier-id]");
  }

  function cibleDe(ligne) {
    return ligne.hasAttribute("data-fichier-id")
      ? { type: "fichiers", id: ligne.getAttribute("data-fichier-id"), nom: ligne.getAttribute("data-nom") }
      : { type: "dossiers", id: ligne.getAttribute("data-dossier-id"), nom: ligne.getAttribute("data-nom") };
  }

  function agir(cible, action, donnees) {
    return Admin.appeler("admin/" + cible.type + "/" + cible.id + "/" + action, donnees || {});
  }

  function signalerErreur(erreur) {
    Admin.toast(erreur.message, { erreur: true });
  }

  // ------------------------------------------------------------- Corbeille

  if (corbeilleOuverte) {
    document.addEventListener("click", function (evenement) {
      var bouton = evenement.target.closest("[data-action]");
      if (!bouton) {
        return;
      }
      var action = bouton.getAttribute("data-action");

      if (action === "vider-corbeille") {
        Admin.dialogue({
          titre: "Vider la corbeille ?",
          texte: "Tous les éléments seront supprimés définitivement. Cette action ne peut pas être annulée.",
          confirmer: "Vider la corbeille",
          danger: true,
        }).then(function (ok) {
          if (ok) {
            Admin.appeler("admin/corbeille/vider").then(function () {
              window.location.reload();
            }, signalerErreur);
          }
        });
        return;
      }

      var ligne = ligneDe(bouton);
      if (!ligne) {
        return;
      }
      var cible = cibleDe(ligne);
      if (action === "restaurer-fichier" || action === "restaurer-dossier") {
        agir(cible, "restaurer").then(function (reponse) {
          ligne.remove();
          Admin.toast(reponse.message);
          if (!document.querySelector(".corbeille__ligne")) {
            window.location.reload();
          }
        }, signalerErreur);
      } else if (action === "detruire-fichier" || action === "detruire-dossier") {
        Admin.dialogue({
          titre: "Supprimer définitivement ?",
          texte: "« " + cible.nom + " » sera effacé du serveur. Cette action ne peut pas être annulée.",
          confirmer: "Supprimer définitivement",
          danger: true,
        }).then(function (ok) {
          if (ok) {
            agir(cible, "detruire").then(function () {
              ligne.remove();
              if (!document.querySelector(".corbeille__ligne")) {
                window.location.reload();
              }
            }, signalerErreur);
          }
        });
      }
    });
    return;
  }

  if (!drive) {
    return;
  }

  var contenu = drive.querySelector("[data-drive-contenu]");
  var selecteur = drive.querySelector("[data-selecteur]");
  var dossierCourant = drive.getAttribute("data-dossier");
  var nomDossierCourant = drive.getAttribute("data-nom-dossier");
  var tailleMax = parseInt(drive.getAttribute("data-taille-max"), 10);
  var extensions = drive.getAttribute("data-extensions").split(",");
  var recherche = drive.getAttribute("data-recherche");
  var arborescence = JSON.parse(drive.querySelector("[data-arborescence]").textContent);

  // ------------------------------------------------- Rechargement du contenu

  var rechargementPrevu = null;

  function recharger() {
    clearTimeout(rechargementPrevu);
    rechargementPrevu = setTimeout(function () {
      var parametres = new URLSearchParams({ fragment: "1" });
      if (dossierCourant) {
        parametres.set("dossier", dossierCourant);
      }
      if (recherche) {
        parametres.set("recherche", recherche);
      }
      fetch(Admin.adresse("admin/fichiers?" + parametres.toString()), {
        headers: { "X-Requested-With": "fetch" },
        credentials: "same-origin",
      })
        .then(function (reponse) {
          if (reponse.status === 401 || reponse.redirected) {
            window.location.reload();
          }
          return reponse.text();
        })
        .then(function (html) {
          contenu.innerHTML = html;
          appliquerVue();
        });
    }, 120);
  }

  // ------------------------------------------------------ Liste ou grille

  var vue = "liste";
  try {
    vue = localStorage.getItem("drive-vue") || "liste";
  } catch (e) {
    /* stockage indisponible : affichage en liste */
  }

  function appliquerVue() {
    drive.classList.toggle("drive--grille", vue === "grille");
    Array.prototype.forEach.call(drive.querySelectorAll("[data-vue]"), function (bouton) {
      bouton.setAttribute("aria-pressed", String(bouton.getAttribute("data-vue") === vue));
    });
  }
  appliquerVue();

  // ------------------------------------------------------------- Actions

  function choisirDossier(titre, exclure) {
    var conteneur = document.createElement("div");
    conteneur.className = "choix-dossier";
    var options = [{ id: "", nom: "Fichiers", niveau: -1 }].concat(arborescence);
    var exclus = exclure || [];
    var premier = true;

    options.forEach(function (option) {
      if (exclus.indexOf(String(option.id)) !== -1) {
        return;
      }
      var etiquette = document.createElement("label");
      etiquette.className = "choix-dossier__option";
      etiquette.style.paddingLeft = 12 + (option.niveau + 1) * 18 + "px";
      var radio = document.createElement("input");
      radio.type = "radio";
      radio.name = "destination";
      radio.value = option.id;
      if (premier) {
        radio.checked = true;
        premier = false;
      }
      etiquette.appendChild(radio);
      etiquette.appendChild(document.createTextNode(option.nom));
      conteneur.appendChild(etiquette);
    });

    return Admin.dialogue({
      titre: titre,
      contenu: conteneur,
      confirmer: "Déplacer ici",
      valeur: function () {
        var choisi = conteneur.querySelector("input:checked");
        return choisi ? choisi.value : null;
      },
    });
  }

  /** Dossier et ses descendants, qu'on ne peut pas choisir comme destination. */
  function descendants(id) {
    var resultat = [String(id)];
    var index = arborescence.findIndex(function (d) {
      return String(d.id) === String(id);
    });
    if (index === -1) {
      return resultat;
    }
    var niveau = arborescence[index].niveau;
    for (var i = index + 1; i < arborescence.length && arborescence[i].niveau > niveau; i++) {
      resultat.push(String(arborescence[i].id));
    }
    return resultat;
  }

  function supprimer(cible) {
    agir(cible, "supprimer").then(function (reponse) {
      recharger();
      Admin.toast(reponse.message, {
        action: {
          libelle: "Annuler",
          faire: function () {
            agir(cible, "restaurer").then(function () {
              recharger();
              Admin.toast("Restauré.");
            }, signalerErreur);
          },
        },
      });
    }, signalerErreur);
  }

  function nouveauDossier() {
    Admin.dialogue({
      titre: "Nouveau dossier",
      champ: { libelle: "Nom du dossier", valeur: "Dossier sans titre" },
      confirmer: "Créer",
    }).then(function (nom) {
      if (nom) {
        Admin.appeler("admin/dossiers", { nom: nom, parent_id: dossierCourant || null }).then(function (reponse) {
          Admin.toast(reponse.message);
          recharger();
        }, signalerErreur);
      }
    });
  }

  document.addEventListener("click", function (evenement) {
    var bouton = evenement.target.closest("[data-action], [data-vue]");
    if (!bouton) {
      return;
    }

    if (bouton.hasAttribute("data-vue")) {
      vue = bouton.getAttribute("data-vue");
      try {
        localStorage.setItem("drive-vue", vue);
      } catch (e) {
        /* sans stockage, le choix vaut pour cette page */
      }
      appliquerVue();
      return;
    }

    var action = bouton.getAttribute("data-action");
    if (action === "televerser") {
      evenement.preventDefault();
      selecteur.click();
      return;
    }
    if (action === "nouveau-dossier") {
      evenement.preventDefault();
      nouveauDossier();
      return;
    }

    var ligne = ligneDe(bouton);
    if (!ligne) {
      return;
    }
    var cible = cibleDe(ligne);

    switch (action) {
      case "basculer-visibilite":
        var publier = ligne.getAttribute("data-public") !== "1";
        agir(cible, publier ? "publier" : "masquer").then(function (reponse) {
          Admin.toast(reponse.message);
          recharger();
        }, signalerErreur);
        break;

      case "renommer-fichier":
      case "renommer-dossier":
        Admin.dialogue({
          titre: "Renommer",
          champ: { libelle: "Nouveau nom", valeur: cible.nom },
          confirmer: "Renommer",
        }).then(function (nom) {
          if (nom && nom !== cible.nom) {
            agir(cible, "renommer", { nom: nom }).then(function (reponse) {
              Admin.toast(reponse.message);
              recharger();
            }, signalerErreur);
          }
        });
        break;

      case "deplacer-fichier":
      case "deplacer-dossier":
        var exclus = cible.type === "dossiers" ? descendants(cible.id) : [];
        exclus.push(String(dossierCourant || ""));
        choisirDossier("Déplacer « " + cible.nom + " »", exclus).then(function (destination) {
          if (destination !== null) {
            agir(cible, "deplacer", { dossier_id: destination || null }).then(function (reponse) {
              Admin.toast(reponse.message);
              recharger();
            }, signalerErreur);
          }
        });
        break;

      case "supprimer-fichier":
      case "supprimer-dossier":
        supprimer(cible);
        break;
    }
  });

  // Clic droit sur un fichier ou un dossier : ouvre son menu, comme dans Drive.
  drive.addEventListener("contextmenu", function (evenement) {
    var ligne = ligneDe(evenement.target);
    var menu = ligne && ligne.querySelector("details.menu");
    if (!menu) {
      return;
    }
    evenement.preventDefault();
    Array.prototype.forEach.call(document.querySelectorAll("details.menu[open]"), function (autre) {
      autre.removeAttribute("open");
    });
    menu.setAttribute("open", "");
    var premier = menu.querySelector(".menu__item");
    if (premier) {
      premier.focus();
    }
  });

  // Lien « Nouveau » de la barre latérale ouvert depuis une autre page.
  var actionDemandee = new URLSearchParams(window.location.search).get("action");
  if (actionDemandee === "nouveau-dossier") {
    nouveauDossier();
  } else if (actionDemandee === "televerser") {
    Admin.toast("Glissez vos fichiers dans cette page, ou cliquez sur « Importer des fichiers ».");
  }
  if (actionDemandee) {
    var propre = new URL(window.location.href);
    propre.searchParams.delete("action");
    history.replaceState(null, "", propre);
  }

  // ------------------------------------------------------- Importation

  var panneau = document.querySelector("[data-televersements]");
  var listePanneau = panneau.querySelector("[data-televersements-liste]");
  var titrePanneau = panneau.querySelector("[data-televersements-titre]");
  var file = [];
  var enCours = false;
  var termines = 0;
  var echecs = 0;

  panneau.querySelector("[data-televersements-fermer]").addEventListener("click", function () {
    if (enCours) {
      Admin.toast("Attendez la fin de l'importation pour fermer ce panneau.");
      return;
    }
    panneau.hidden = true;
    listePanneau.innerHTML = "";
    termines = 0;
    echecs = 0;
  });

  panneau.querySelector("[data-televersements-replier]").addEventListener("click", function () {
    var replie = panneau.classList.toggle("est-replie");
    this.setAttribute("aria-expanded", String(!replie));
    this.setAttribute("aria-label", replie ? "Agrandir" : "Réduire");
  });

  function majTitre() {
    var restants = file.length + (enCours ? 1 : 0);
    if (restants > 0) {
      titrePanneau.textContent = "Importation de " + restants + " fichier" + (restants > 1 ? "s" : "") + "…";
    } else {
      titrePanneau.textContent =
        termines + " fichier" + (termines > 1 ? "s importés" : " importé") + (echecs ? ", " + echecs + " refusé" + (echecs > 1 ? "s" : "") : "");
    }
  }

  function ligneImport(fichier) {
    var ligne = document.createElement("li");
    ligne.className = "televersement";
    var nom = document.createElement("span");
    nom.className = "televersement__nom";
    nom.textContent = fichier.name;
    var etat = document.createElement("span");
    etat.className = "televersement__etat";
    etat.textContent = "En attente";
    var barre = document.createElement("span");
    barre.className = "televersement__barre";
    barre.appendChild(document.createElement("span"));
    ligne.appendChild(nom);
    ligne.appendChild(etat);
    ligne.appendChild(barre);
    listePanneau.appendChild(ligne);
    return { ligne: ligne, etat: etat, barre: barre.firstChild };
  }

  function terminer(element, message, statut) {
    element.ligne.classList.add(statut === "erreur" ? "est-en-erreur" : "est-termine");
    element.etat.textContent = message;
  }

  function verifier(fichier) {
    var extension = fichier.name.split(".").pop().toLowerCase();
    if (fichier.name.indexOf(".") === -1 || extensions.indexOf(extension) === -1) {
      return "Type non accepté";
    }
    if (fichier.size > tailleMax) {
      return "Trop lourd (" + Math.round(tailleMax / 1048576) + " Mo max.)";
    }
    if (fichier.size === 0) {
      return "Fichier vide";
    }
    return null;
  }

  function televerserTout(fichiers, dossier) {
    if (!fichiers.length) {
      return;
    }
    panneau.hidden = false;
    panneau.classList.remove("est-replie");
    Array.prototype.forEach.call(fichiers, function (fichier) {
      var element = ligneImport(fichier);
      var probleme = verifier(fichier);
      if (probleme) {
        echecs++;
        terminer(element, probleme, "erreur");
        return;
      }
      file.push({ fichier: fichier, element: element, dossier: dossier });
    });
    majTitre();
    suivant();
  }

  /** Les fichiers partent un par un : un hébergement mutualisé supporte mal les envois simultanés. */
  function suivant() {
    if (enCours || !file.length) {
      majTitre();
      return;
    }
    enCours = true;
    var travail = file.shift();
    majTitre();
    envoyer(travail).then(function () {
      enCours = false;
      suivant();
    });
  }

  function envoyer(travail) {
    return new Promise(function (resoudre) {
      var donnees = new FormData();
      donnees.append("fichier", travail.fichier);
      donnees.append("dossier_id", travail.dossier || "");

      var requete = new XMLHttpRequest();
      requete.open("POST", Admin.adresse("admin/fichiers/televerser"));
      requete.setRequestHeader("X-CSRF-Token", Admin.jeton);
      requete.setRequestHeader("X-Requested-With", "fetch");
      requete.setRequestHeader("Accept", "application/json");
      travail.element.etat.textContent = "0 %";

      requete.upload.addEventListener("progress", function (evenement) {
        if (evenement.lengthComputable) {
          var pourcentage = Math.round((evenement.loaded / evenement.total) * 100);
          travail.element.barre.style.width = pourcentage + "%";
          travail.element.etat.textContent = pourcentage < 100 ? pourcentage + " %" : "Vérification…";
        }
      });

      requete.addEventListener("load", function () {
        var reponse = {};
        try {
          reponse = JSON.parse(requete.responseText);
        } catch (e) {
          reponse = { erreur: requete.status === 413 ? "Trop lourd pour le serveur" : "Réponse inattendue du serveur" };
        }
        if (requete.status === 401) {
          window.location.reload();
          return;
        }
        if (requete.status >= 200 && requete.status < 300 && reponse.ok) {
          termines++;
          terminer(travail.element, reponse.doublon ? "Déjà présent" : "Importé", "ok");
          document.dispatchEvent(new CustomEvent("drive:importe", { detail: { reponse: reponse, nom: travail.fichier.name } }));
          recharger();
        } else {
          echecs++;
          terminer(travail.element, reponse.erreur || "Échec de l'envoi", "erreur");
          travail.element.ligne.title = reponse.erreur || "";
        }
        resoudre();
      });

      requete.addEventListener("error", function () {
        echecs++;
        terminer(travail.element, "Connexion interrompue", "erreur");
        resoudre();
      });

      requete.send(donnees);
    });
  }

  selecteur.addEventListener("change", function () {
    televerserTout(Array.prototype.slice.call(selecteur.files), dossierCourant);
    selecteur.value = "";
  });

  // ------------------------------------------------------ Glisser-déposer

  var voile = document.querySelector("[data-depot-voile]");
  var cibleVoile = voile.querySelector("[data-depot-cible]");
  var profondeur = 0;
  var elementTire = null;

  function contientFichiers(evenement) {
    var types = evenement.dataTransfer && evenement.dataTransfer.types;
    return types && Array.prototype.indexOf.call(types, "Files") !== -1;
  }

  function cibleSous(element) {
    return element && element.closest ? element.closest("[data-cible-depot]") : null;
  }

  function marquerCible(cible) {
    Array.prototype.forEach.call(document.querySelectorAll(".est-cible"), function (autre) {
      if (autre !== cible) {
        autre.classList.remove("est-cible");
      }
    });
    if (cible) {
      cible.classList.add("est-cible");
    }
  }

  // Fichiers venus de l'ordinateur.
  window.addEventListener("dragenter", function (evenement) {
    if (!contientFichiers(evenement)) {
      return;
    }
    evenement.preventDefault();
    profondeur++;
    voile.hidden = false;
  });

  window.addEventListener("dragover", function (evenement) {
    if (!contientFichiers(evenement) && !elementTire) {
      return;
    }
    evenement.preventDefault();
    var cible = cibleSous(evenement.target);
    marquerCible(cible);
    if (contientFichiers(evenement)) {
      evenement.dataTransfer.dropEffect = "copy";
      cibleVoile.textContent = cible ? cible.getAttribute("data-nom") || cible.textContent.trim() : nomDossierCourant;
    } else {
      evenement.dataTransfer.dropEffect = cible ? "move" : "none";
    }
  });

  window.addEventListener("dragleave", function (evenement) {
    if (!contientFichiers(evenement)) {
      return;
    }
    profondeur = Math.max(0, profondeur - 1);
    if (profondeur === 0) {
      voile.hidden = true;
      marquerCible(null);
    }
  });

  window.addEventListener("drop", function (evenement) {
    var cible = cibleSous(evenement.target);
    marquerCible(null);

    if (contientFichiers(evenement)) {
      evenement.preventDefault();
      profondeur = 0;
      voile.hidden = true;
      var destination = cible ? cible.getAttribute("data-cible-depot") : dossierCourant;
      televerserTout(Array.prototype.slice.call(evenement.dataTransfer.files), destination);
      return;
    }

    // Fichier ou dossier déplacé à la souris vers un dossier ou le fil d'Ariane.
    if (elementTire && cible) {
      evenement.preventDefault();
      var source = cibleDe(elementTire);
      var vers = cible.getAttribute("data-cible-depot");
      if (source.type === "dossiers" && descendants(source.id).indexOf(String(vers)) !== -1) {
        return;
      }
      agir(source, "deplacer", { dossier_id: vers || null }).then(function (reponse) {
        Admin.toast(reponse.message);
        recharger();
      }, signalerErreur);
    }
  });

  drive.addEventListener("dragstart", function (evenement) {
    var ligne = ligneDe(evenement.target);
    if (!ligne) {
      return;
    }
    elementTire = ligne;
    ligne.classList.add("est-tire");
    evenement.dataTransfer.effectAllowed = "move";
    evenement.dataTransfer.setData("text/plain", ligne.getAttribute("data-nom"));
  });

  drive.addEventListener("dragend", function () {
    if (elementTire) {
      elementTire.classList.remove("est-tire");
    }
    elementTire = null;
    marquerCible(null);
  });
})();
