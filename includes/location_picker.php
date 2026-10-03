<style>
.loc-wrap { position: relative; }
.loc-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 999;
    background: #fff; border: 1px solid #e2e8f0; border-top: none;
    border-radius: 0 0 0.5rem 0.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
    max-height: 220px; overflow-y: auto; display: none;
}
.loc-dropdown.open { display: block; }
.loc-item {
    padding: 0.55rem 0.75rem; cursor: pointer;
    font-size: 0.75rem; color: #334155; line-height: 1.4;
    border-bottom: 1px solid #f1f5f9;
}
.loc-item:last-child { border-bottom: none; }
.loc-item:hover, .loc-item.active { background: #f8fafc; color: #0f172a; }
.loc-item .loc-sub { color: #94a3b8; font-size: 0.7rem; margin-top: 1px; }
.loc-verified { display:inline-flex; align-items:center; gap:4px;
    font-size:0.65rem; color:#16a34a; margin-top:3px; }
</style>

<script>
(function () {
    function debounce(fn, ms) {
        let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }
    function initLocationInput(input) {
        if (input._locInited) return;
        input._locInited = true;

        const wrap     = input.closest('.loc-wrap');
        const latInput = document.getElementById(input.dataset.lat);
        const lngInput = document.getElementById(input.dataset.lng);
        const badge    = wrap.querySelector('.loc-verified');

        const dd = document.createElement('div');
        dd.className = 'loc-dropdown';
        wrap.appendChild(dd);

        function clearCoords() {
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (badge)    badge.classList.add('hidden');
        }

        function setCoords(lat, lng, label) {
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            if (badge)    badge.classList.remove('hidden');
            input.value = label;
            dd.classList.remove('open');
        }

        const search = debounce(async (q) => {
            if (q.length < 2) { dd.classList.remove('open'); return; }
            dd.innerHTML = '<div class="loc-item" style="color:#94a3b8">Searching…</div>';
            dd.classList.add('open');
            try {
                const res  = await fetch('/courier/geocode_search.php?q=' + encodeURIComponent(q));
                const data = await res.json();
                if (!data.length) {
                    dd.innerHTML = '<div class="loc-item" style="color:#94a3b8">No results found</div>';
                    return;
                }
                dd.innerHTML = '';
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'loc-item';
                    const parts = item.label.split(',');
                    const main  = parts[0].trim();
                    const sub   = parts.slice(1).join(',').trim();
                    div.innerHTML = `<div>${main}</div>${sub ? `<div class="loc-sub">${sub}</div>` : ''}`;
                    div.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        setCoords(item.lat, item.lng, item.label);
                    });
                    dd.appendChild(div);
                });
            } catch (e) {
                dd.innerHTML = '<div class="loc-item" style="color:#ef4444">Search failed</div>';
            }
        }, 350);

        input.addEventListener('input', () => { clearCoords(); search(input.value.trim()); });
        input.addEventListener('focus', () => { if (dd.children.length) dd.classList.add('open'); });
        input.addEventListener('blur',  () => setTimeout(() => dd.classList.remove('open'), 200));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') dd.classList.remove('open');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[data-loc]').forEach(initLocationInput);
    });

    window.initLocationInput = initLocationInput;
})();
</script>
