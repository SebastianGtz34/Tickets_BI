$(function () {
    /* ===== Sidebar toggle (estilo SB Admin 2) ===== */
    function aplicarToggle() {
        var toggled = $('.sidebar').hasClass('toggled');
        try { localStorage.setItem('sidebarToggled', toggled); } catch (e) {}
    }

    // Toggle interno del sidebar (botón circular al pie)
    $(document).on('click', '#sidebarToggle', function (e) {
        e.preventDefault();
        $('.sidebar').toggleClass('toggled');
        aplicarToggle();
    });

    // Toggle desde topbar (solo visible en mobile)
    $(document).on('click', '#sidebarToggleTop', function (e) {
        e.preventDefault();
        $('.sidebar').toggleClass('toggled');
        aplicarToggle();
    });

    // Auto-cerrar en mobile después de navegar
    if ($(window).width() < 768) {
        $('.sidebar').addClass('toggled');
        $(document).on('click', '.sidebar .nav-link', function () {
            if ($(window).width() < 768) $('.sidebar').addClass('toggled');
        });
    } else if (localStorage.getItem('sidebarToggled') === 'true') {
        $('.sidebar').addClass('toggled');
    }

    /* ===== Theme toggle (MESS) ===== */
    function applyTheme(theme) {
        var $icon = $('#themeToggle i');
        if (theme === 'dark') {
            document.body.classList.add('theme-dark');
            $icon.removeClass('fa-moon').addClass('fa-sun');
        } else {
            document.body.classList.remove('theme-dark');
            $icon.removeClass('fa-sun').addClass('fa-moon');
        }
        try { localStorage.setItem('mess-theme', theme); } catch (e) {}
        document.dispatchEvent(new CustomEvent('mess:themechange', { detail: { theme: theme } }));
    }

    applyTheme(document.body.classList.contains('theme-dark') ? 'dark' : 'light');

    $('#themeToggle').on('click', function () {
        var next = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
        applyTheme(next);
    });
});

/* ===== Helpers globales: tokens MESS desde CSS ===== */
function messColor(token) {
    var v = getComputedStyle(document.body).getPropertyValue('--' + token);
    return (v || '').trim();
}

function messRgba(token, alpha) {
    var hex = messColor(token);
    if (!hex || hex.charAt(0) !== '#') return hex;
    var h = hex.replace('#', '');
    if (h.length === 3) { h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; }
    var r = parseInt(h.substring(0, 2), 16);
    var g = parseInt(h.substring(2, 4), 16);
    var b = parseInt(h.substring(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
}
