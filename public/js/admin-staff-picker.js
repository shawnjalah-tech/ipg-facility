/* Phase 4: server-side staff lookup; the original <select> remains a fallback. */
(function () {
    'use strict';
    function init() {
        const select = document.getElementById('user_id');
        const picker = document.getElementById('admin-staff-picker');
        const input = document.getElementById('staff-search');
        const results = document.getElementById('staff-search-results');
        const feedback = document.getElementById('staff-search-feedback');
        if (!select || !picker || !input || !results || !feedback || !picker.dataset.searchUrl) return;
        const endpoint = picker.dataset.searchUrl;
        const initial = Array.from(select.options).filter(o => o.value).map(o => ({id:String(o.value), label:o.textContent.replace(/\s+/g,' ').trim()}));
        let shown = initial.slice(0, 12);
        let selectedLabel = initial.find(o => o.id === select.value)?.label || '';
        let active = -1, seq = 0, debounce;
        function toggle(open) {
            results.hidden = !open;
            input.setAttribute('aria-expanded', String(open));
            if (!open) input.removeAttribute('aria-activedescendant');
        }
        function pick(person) {
            let option = Array.from(select.options).find(o => o.value === String(person.id));
            if (!option) {
                option = new Option(person.label, String(person.id));
                select.add(option);
            }
            select.value = String(person.id);
            selectedLabel = person.label;
            input.value = person.label;
            input.setCustomValidity('');
            feedback.textContent = 'Selected: ' + person.label;
            toggle(false);
            input.focus({preventScroll:true});
        }
        function render(items) {
            shown = items;
            active = -1;
            results.replaceChildren();
            items.forEach((person, i) => {
                const node = document.createElement('button');
                node.type = 'button';
                node.id = 'staff-result-' + i;
                node.setAttribute('role', 'option');
                node.className = 'staff-picker-option';
                node.setAttribute('aria-selected', 'false');
                node.textContent = person.label;
                node.addEventListener('click', () => pick(person));
                results.appendChild(node);
            });
            if (!items.length) {
                const node = document.createElement('div');
                node.className = 'staff-picker-empty';
                node.textContent = 'No matching staff. Try a different name, staff ID, or email.';
                results.appendChild(node);
            }
            feedback.textContent = items.length + ' matching staff members (up to 12 results).';
        }
        function highlight(i) {
            const elements = Array.from(results.querySelectorAll('[role="option"]'));
            if (!elements.length) return;
            active = (i + elements.length) % elements.length;
            elements.forEach((el,j) => {
                el.classList.toggle('is-active', j === active);
                el.setAttribute('aria-selected', String(j === active));
            });
            input.setAttribute('aria-activedescendant', elements[active].id);
            elements[active].scrollIntoView({block:'nearest'});
        }
        async function search(term, requestId) {
            try {
                const response = await fetch(endpoint + '?q=' + encodeURIComponent(term), {headers:{Accept:'application/json'}, credentials:'same-origin'});
                if (!response.ok) throw new Error('Search is currently unavailable.');
                const data = await response.json();
                if (seq !== requestId) return;
                render((data.results || []).map(p => ({id:String(p.id), label:[p.name, p.employee_id ? '(' + p.employee_id + ')' : '', '—', p.email].filter(Boolean).join(' ')})));
                toggle(true);
            } catch (err) {
                if (seq === requestId) {
                    render([]);
                    feedback.textContent = 'Search could not be completed. Please try again.';
                    toggle(true);
                }
            }
        }
        input.addEventListener('focus', () => { if (select.value && input.value === selectedLabel) return; render(shown); toggle(true); });
        input.addEventListener('input', () => {
            select.value = '';
            selectedLabel = '';
            input.setCustomValidity('');
            clearTimeout(debounce);
            const term = input.value.trim();
            const current = ++seq;
            if (term.length < 2) {
                render(term ? [] : initial.slice(0, 12));
                feedback.textContent = 'Enter at least 2 characters to search all staff.';
                toggle(true);
                return;
            }
            feedback.textContent = 'Searching…';
            debounce = setTimeout(() => search(term, current), 200);
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') { toggle(false); return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (results.hidden) { render(shown); toggle(true); }
                if (shown.length) highlight(active < 0 ? (e.key === 'ArrowDown' ? 0 : shown.length-1) : active + (e.key === 'ArrowDown' ? 1 : -1));
            }
            if (e.key === 'Enter' && !results.hidden) {
                e.preventDefault();
                if (shown.length) pick(shown[active < 0 ? 0 : active]);
            }
        });
        document.addEventListener('pointerdown', e => { if (!picker.contains(e.target)) toggle(false); });
        picker.closest('form')?.addEventListener('submit', e => {
            if (!select.value || input.value !== selectedLabel) {
                e.preventDefault();
                input.setCustomValidity('Select a staff member from the search results.');
                input.reportValidity();
                input.focus();
            }
        });
        picker.hidden = false;
        select.required = false;
        select.style.display = 'none';
        input.required = true;
        if (selectedLabel) {
            input.value = selectedLabel;
            feedback.textContent = 'Selected: ' + selectedLabel;
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
