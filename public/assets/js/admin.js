/*
 * Socle de l'administration : menus, barre latérale sur téléphone,
 * notifications et appels au serveur protégés par le jeton CSRF.
 * Les pages (fichiers, étudiants…) s'appuient sur window.Admin.
 */
(function () {
  "use strict";

  var jeton = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
  var base = document.body.getAttribute("data-base").replace(/\/$/, "");

  function adresse(chemin) {
    return base + "/" + chemin.replace(/^\//, "");
  }

  /** Appel JSON vers l'administration ; rejette avec un message lisible. */
  function appeler(chemin, donnees, methode) {
    return fetch(adresse(chemin), {
      method: methode || "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "fetch",
        "X-CSRF-Token": jeton,
      },
      body: donnees ? JSON.stringify(donnees) : undefined,
      credentials: "same-origin",
    }).then(lireReponse);
  }

  function lireReponse(reponse) {
    return reponse
      .json()
      .catch(function () {
        return {};
      })
      .then(function (corps) {
        if (reponse.status === 401) {
          window.location.reload();
        }
        if (!reponse.ok) {
          throw new Error(corps.erreur || "L'action n'a pas abouti. Réessayez.");
        }
        return corps;
      });
  }

  // --- Notifications en bas de l'écran, comme dans Drive ---------------------

  var zoneToasts = document.querySelector("[data-toasts]");

  function toast(message, options) {
    options = options || {};
    var element = document.createElement("div");
    element.className = "toast" + (options.erreur ? " toast--erreur" : "");
    element.setAttribute("role", options.erreur ? "alert" : "status");

    var texte = document.createElement("span");
    texte.textContent = message;
    element.appendChild(texte);

    if (options.action) {
      var bouton = document.createElement("button");
      bouton.type = "button";
      bouton.className = "toast__action";
      bouton.textContent = options.action.libelle;
      bouton.addEventListener("click", function () {
        options.action.faire();
        fermer();
      });
      element.appendChild(bouton);
    }

    var croix = document.createElement("button");
    croix.type = "button";
    croix.className = "toast__fermer";
    croix.setAttribute("aria-label", "Fermer");
    croix.textContent = "×";
    croix.addEventListener("click", fermer);
    element.appendChild(croix);

    zoneToasts.appendChild(element);
    var minuterie = setTimeout(fermer, options.duree || 6000);

    function fermer() {
      clearTimeout(minuterie);
      element.classList.add("est-sortant");
      setTimeout(function () {
        element.remove();
      }, 200);
    }
    return fermer;
  }

  Array.prototype.forEach.call(document.querySelectorAll("[data-toast-initial]"), function (element) {
    var message = element.textContent;
    var erreur = element.classList.contains("toast--erreur");
    element.remove();
    toast(message, { erreur: erreur });
  });

  // --- Menus déroulants (<details>) : un seul ouvert, fermeture au clic dehors -

  document.addEventListener("click", function (evenement) {
    Array.prototype.forEach.call(document.querySelectorAll("details.menu[open]"), function (menu) {
      if (!menu.contains(evenement.target) || evenement.target.closest(".menu__item")) {
        menu.removeAttribute("open");
      }
    });
  });

  document.addEventListener("keydown", function (evenement) {
    if (evenement.key !== "Escape") {
      return;
    }
    Array.prototype.forEach.call(document.querySelectorAll("details.menu[open]"), function (menu) {
      menu.removeAttribute("open");
      var resume = menu.querySelector("summary");
      if (resume) {
        resume.focus();
      }
    });
    fermerBarre();
  });

  // --- Barre latérale sur téléphone ------------------------------------------

  var barre = document.getElementById("admin-barre");
  var voile = document.querySelector("[data-fermer-barre]");
  var ouvrir = document.querySelector("[data-ouvrir-barre]");

  function fermerBarre() {
    if (!barre.classList.contains("est-ouverte")) {
      return;
    }
    barre.classList.remove("est-ouverte");
    voile.hidden = true;
    ouvrir.setAttribute("aria-expanded", "false");
  }

  ouvrir.addEventListener("click", function () {
    barre.classList.add("est-ouverte");
    voile.hidden = false;
    ouvrir.setAttribute("aria-expanded", "true");
  });
  voile.addEventListener("click", fermerBarre);

  // --- Fenêtres de dialogue ---------------------------------------------------

  /**
   * Ouvre un <dialog> construit à la volée.
   * options : titre, texte, champ ({libelle, valeur}), confirmer, danger, contenu (élément).
   * Renvoie une promesse résolue avec la saisie (ou true), ou null si annulé.
   */
  function dialogue(options) {
    return new Promise(function (resoudre) {
      var boite = document.createElement("dialog");
      boite.className = "dialogue";
      var formulaire = document.createElement("form");
      formulaire.method = "dialog";

      var titre = document.createElement("h2");
      titre.textContent = options.titre;
      formulaire.appendChild(titre);

      if (options.texte) {
        var texte = document.createElement("p");
        texte.className = "dialogue__texte";
        texte.textContent = options.texte;
        formulaire.appendChild(texte);
      }

      var saisie = null;
      if (options.champ) {
        var etiquette = document.createElement("label");
        etiquette.className = "masque-visuel";
        etiquette.textContent = options.champ.libelle;
        etiquette.htmlFor = "dialogue-saisie";
        saisie = document.createElement("input");
        saisie.className = "saisie";
        saisie.id = "dialogue-saisie";
        saisie.value = options.champ.valeur || "";
        saisie.required = true;
        saisie.maxLength = 200;
        formulaire.appendChild(etiquette);
        formulaire.appendChild(saisie);
      }

      if (options.contenu) {
        formulaire.appendChild(options.contenu);
      }

      var actions = document.createElement("div");
      actions.className = "dialogue__actions";
      var annuler = document.createElement("button");
      annuler.type = "button";
      annuler.className = "btn btn--discret";
      annuler.textContent = options.annuler || "Annuler";
      var valider = document.createElement("button");
      valider.type = "submit";
      valider.className = "btn " + (options.danger ? "btn--danger" : "btn--primaire");
      valider.textContent = options.confirmer || "Valider";
      actions.appendChild(annuler);
      actions.appendChild(valider);
      formulaire.appendChild(actions);
      boite.appendChild(formulaire);
      document.body.appendChild(boite);

      var resultat = null;
      annuler.addEventListener("click", function () {
        boite.close();
      });
      formulaire.addEventListener("submit", function (evenement) {
        if (saisie && !saisie.value.trim()) {
          evenement.preventDefault();
          saisie.focus();
          return;
        }
        resultat = saisie ? saisie.value.trim() : options.valeur !== undefined ? options.valeur() : true;
      });
      boite.addEventListener("close", function () {
        boite.remove();
        resoudre(resultat);
      });

      boite.showModal();
      if (saisie) {
        saisie.focus();
        // Pour renommer, on sélectionne le nom sans l'extension.
        var point = saisie.value.lastIndexOf(".");
        saisie.setSelectionRange(0, point > 0 ? point : saisie.value.length);
      } else {
        valider.focus();
      }
    });
  }

  window.Admin = {
    adresse: adresse,
    appeler: appeler,
    lireReponse: lireReponse,
    jeton: jeton,
    toast: toast,
    dialogue: dialogue,
  };
})();
