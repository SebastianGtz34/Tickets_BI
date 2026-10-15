/* ─────────────────────────────────────────
   tema_embed.js — Tema claro/oscuro de las vistas embed_*
   Viven en iframes dentro de loginMaster (MessBook), así que siguen SU
   preferencia: localStorage 'mess_theme' (la app completa de Tickets usa
   'mess-theme', ver sidebar.js). Cargar justo después de <body> para
   evitar el destello claro.
   ───────────────────────────────────────── */
(function () {
    function aplicarTema() {
        try {
            document.body.classList.toggle('theme-dark', localStorage.getItem('mess_theme') === 'dark');
        } catch (e) {}
    }
    aplicarTema();
    // El toggle de MessBook escribe en localStorage: el evento llega al iframe y el tema cambia en vivo.
    window.addEventListener('storage', function (e) {
        if (e.key === 'mess_theme') aplicarTema();
    });
})();
