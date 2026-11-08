/* Connexion : afficher ou masquer le mot de passe. */
(function () {
  "use strict";

  var bouton = document.querySelector("[data-voir-mdp]");
  var champ = document.getElementById("mot_de_passe");
  if (!bouton || !champ) {
    return;
  }
  bouton.addEventListener("click", function () {
    var visible = champ.type === "text";
    champ.type = visible ? "password" : "text";
    bouton.setAttribute("aria-pressed", String(!visible));
    bouton.setAttribute("aria-label", visible ? "Afficher le mot de passe" : "Masquer le mot de passe");
    champ.focus();
  });
})();
