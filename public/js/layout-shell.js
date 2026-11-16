/* Navigation state is exposed to keyboard users and browser agents. */
(function () {
    'use strict';
    var wrapper = document.getElementById('layout-wrapper');
    var toggle = document.querySelector('.topbar-toggle');
    var userBtn = document.querySelector('.topbar-user-toggle');
    var userDrop = document.querySelector('.topbar-dropdown');
    function mobile() { return window.innerWidth < 992; }
    function syncSidebar() {
        if (!wrapper || !toggle) return;
        toggle.setAttribute('aria-expanded', String(mobile()
            ? wrapper.classList.contains('sidebar-mobile-open')
            : !wrapper.classList.contains('sidebar-collapsed')));
    }
    function closeAccount(returnFocus) {
        if (!userBtn || !userDrop) return;
        userDrop.classList.remove('open');
        userBtn.setAttribute('aria-expanded', 'false');
        if (returnFocus) userBtn.focus();
    }
    if (toggle && wrapper) {
        // Browser privacy settings may block storage; navigation must still work.
        try {
            if (!mobile() && localStorage.getItem('sidebar-collapsed') === '1') {
                wrapper.classList.add('sidebar-collapsed');
            }
        } catch (error) { /* In-memory state remains available. */ }
        toggle.addEventListener('click', function () {
            wrapper.classList.toggle(mobile() ? 'sidebar-mobile-open' : 'sidebar-collapsed');
            if (!mobile()) {
                try {
                    localStorage.setItem('sidebar-collapsed', wrapper.classList.contains('sidebar-collapsed') ? '1' : '0');
                } catch (error) { /* Persistence is optional. */ }
            }
            syncSidebar();
        });
        window.addEventListener('resize', function () {
            if (!mobile()) wrapper.classList.remove('sidebar-mobile-open');
            syncSidebar();
        });
        syncSidebar();
    }
    if (userBtn && userDrop) {
        userBtn.addEventListener('click', function () {
            var open = userDrop.classList.toggle('open');
            userBtn.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.topbar-user')) closeAccount(false);
        });
        document.addEventListener('focusin', function (event) {
            if (!event.target.closest('.topbar-user')) closeAccount(false);
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (userDrop && userDrop.classList.contains('open')) closeAccount(true);
        if (wrapper && wrapper.classList.contains('sidebar-mobile-open')) {
            wrapper.classList.remove('sidebar-mobile-open');
            syncSidebar();
            if (toggle) toggle.focus();
        }
    });
    document.addEventListener('click', function (event) {
        if (wrapper && event.target === wrapper) {
            wrapper.classList.remove('sidebar-mobile-open');
            syncSidebar();
        }
    });
    document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
        link.setAttribute('title', link.textContent.trim());
        if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
    });
    document.querySelectorAll('button[name="decision"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var notes = button.form.querySelector('[name="review_notes"]');
            if (notes) notes.required = button.value === 'reject';
        });
    });
})();
