/* Comportements communs au site public. */
(function () {
  "use strict";

  // Menu de navigation sur téléphone.
  var bouton = document.querySelector("[data-menu]");
  var nav = document.getElementById("navigation");
  if (bouton && nav) {
    bouton.addEventListener("click", function () {
      var ouvert = bouton.getAttribute("aria-expanded") === "true";
      bouton.setAttribute("aria-expanded", String(!ouvert));
      nav.setAttribute("data-ouvert", String(!ouvert));
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && bouton.getAttribute("aria-expanded") === "true") {
        bouton.setAttribute("aria-expanded", "false");
        nav.setAttribute("data-ouvert", "false");
        bouton.focus();
      }
    });
  }
})();
