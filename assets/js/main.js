/* ============================================================
   LumaStay — interações do site
   JavaScript sem dependências. Todo o fluxo crítico funciona
   também sem JavaScript, porque a validação é feita no servidor.
   ============================================================ */

(function () {
    'use strict';

    var doc = document;
    var body = doc.body;

    function onReady(callback) {
        if (doc.readyState === 'loading') {
            doc.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function toNumber(value) {
        var parsed = parseFloat(String(value).replace(',', '.'));
        return isFinite(parsed) ? parsed : 0;
    }

    function formatMoney(value) {
        var amount = isFinite(value) ? value : 0;
        try {
            return new Intl.NumberFormat('pt-PT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' €';
        } catch (error) {
            return amount.toFixed(2).replace('.', ',') + ' €';
        }
    }

    function formatDate(isoValue) {
        if (!isoValue) {
            return '';
        }
        var parts = isoValue.split('-');
        if (parts.length !== 3) {
            return '';
        }
        var date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
        if (isNaN(date.getTime())) {
            return '';
        }
        try {
            return new Intl.DateTimeFormat('pt-PT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                timeZone: 'UTC'
            }).format(date);
        } catch (error) {
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }
    }

    function daysBetween(checkIn, checkOut) {
        if (!checkIn || !checkOut) {
            return 0;
        }
        var start = new Date(checkIn + 'T00:00:00Z');
        var end = new Date(checkOut + 'T00:00:00Z');
        if (isNaN(start.getTime()) || isNaN(end.getTime())) {
            return 0;
        }
        var diff = Math.round((end - start) / 86400000);
        return diff > 0 ? diff : 0;
    }

    function addDays(isoValue, days) {
        var date = new Date(isoValue + 'T00:00:00Z');
        if (isNaN(date.getTime())) {
            return '';
        }
        date.setUTCDate(date.getUTCDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function todayIso() {
        var now = new Date();
        var local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 10);
    }

    /* ------------------------------------------------------------------
       Navegação pública (mobile)
       ------------------------------------------------------------------ */

    function initNavigation() {
        var toggle = doc.querySelector('[data-nav-toggle]');
        var nav = doc.getElementById('primary-nav');
        if (!toggle || !nav) {
            return;
        }

        function setOpen(open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
            nav.classList.toggle('is-open', open);
            body.classList.toggle('nav-open', open);
        }

        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        nav.addEventListener('click', function (event) {
            var target = event.target;
            if (target instanceof Element && target.closest('a')) {
                setOpen(false);
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                setOpen(false);
            }
        });

        doc.addEventListener('click', function (event) {
            if (toggle.getAttribute('aria-expanded') !== 'true') {
                return;
            }
            var target = event.target;
            if (!(target instanceof Node)) {
                return;
            }
            if (!nav.contains(target) && !toggle.contains(target)) {
                setOpen(false);
            }
        });
    }

    /* ------------------------------------------------------------------
       Menu lateral da administração
       ------------------------------------------------------------------ */

    function initAdminMenu() {
        var toggle = doc.querySelector('[data-admin-menu]');
        var shell = doc.querySelector('.admin-shell');
        var overlay = doc.querySelector('[data-admin-overlay]');
        if (!toggle || !shell) {
            return;
        }

        function setOpen(open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            shell.classList.toggle('sidebar-open', open);
            body.classList.toggle('no-scroll', open);
            if (overlay) {
                overlay.hidden = !open;
            }
        }

        toggle.addEventListener('click', function () {
            setOpen(!shell.classList.contains('sidebar-open'));
        });

        if (overlay) {
            overlay.addEventListener('click', function () {
                setOpen(false);
            });
        }

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                setOpen(false);
            }
        });
    }

    /* ------------------------------------------------------------------
       Mensagens
       ------------------------------------------------------------------ */

    function initAlerts() {
        doc.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            var close = target.closest('[data-dismiss-alert]');
            if (!close) {
                return;
            }
            var alert = close.closest('.alert');
            if (!alert) {
                return;
            }
            alert.style.transition = 'opacity .2s ease, transform .2s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            window.setTimeout(function () {
                var stack = alert.parentElement;
                alert.remove();
                if (stack && stack.classList.contains('flash-stack') && stack.children.length === 0) {
                    stack.remove();
                }
            }, 200);
        });
    }

    /* ------------------------------------------------------------------
       Datas: entrada/saída coerentes
       ------------------------------------------------------------------ */

    function initDateConstraints() {
        doc.querySelectorAll('[data-check-in]').forEach(function (checkIn) {
            var form = checkIn.closest('form');
            if (!form) {
                return;
            }
            var checkOut = form.querySelector('[data-check-out]');
            if (!checkOut) {
                return;
            }

            if (!checkIn.min) {
                checkIn.min = todayIso();
            }

            function syncMin() {
                if (checkIn.value) {
                    var nextDay = addDays(checkIn.value, 1);
                    if (nextDay) {
                        checkOut.min = nextDay;
                    }
                    if (checkOut.value && checkOut.value <= checkIn.value) {
                        checkOut.value = nextDay || '';
                    }
                } else {
                    checkOut.min = addDays(todayIso(), 1);
                }
            }

            checkIn.addEventListener('change', syncMin);
            syncMin();
        });
    }

    /* ------------------------------------------------------------------
       Pré-visualização do preço da reserva
       ------------------------------------------------------------------ */

    function initBookingSummary() {
        var form = doc.querySelector('[data-booking-form]');
        if (!form) {
            return;
        }

        var pricePerNight = toNumber(form.getAttribute('data-price'));
        var checkIn = form.querySelector('[data-check-in]');
        var checkOut = form.querySelector('[data-check-out]');
        var guests = form.querySelector('select[name="guests"]');

        var summaryIn = doc.querySelector('[data-summary-check-in]');
        var summaryOut = doc.querySelector('[data-summary-check-out]');
        var summaryNights = doc.querySelector('[data-summary-nights]');
        var summaryNightsInline = doc.querySelector('[data-summary-nights-inline]');
        var summaryGuests = doc.querySelector('[data-summary-guests]');
        var summaryTotal = doc.querySelector('[data-summary-total]');

        function update() {
            var nights = 0;
            if (checkIn && checkOut && checkIn.value && checkOut.value) {
                nights = daysBetween(checkIn.value, checkOut.value);
            }

            if (summaryIn) {
                summaryIn.textContent = checkIn && checkIn.value ? formatDate(checkIn.value) : 'Por escolher';
            }
            if (summaryOut) {
                summaryOut.textContent = checkOut && checkOut.value ? formatDate(checkOut.value) : 'Por escolher';
            }
            if (summaryNights) {
                summaryNights.textContent = nights > 0 ? String(nights) : '—';
            }
            if (summaryNightsInline) {
                summaryNightsInline.textContent = nights > 0 ? String(nights) : '—';
            }
            if (summaryTotal) {
                summaryTotal.textContent = nights > 0 && pricePerNight > 0
                    ? formatMoney(nights * pricePerNight)
                    : '—';
            }
            if (summaryGuests && guests) {
                summaryGuests.textContent = guests.value;
            }
        }

        [checkIn, checkOut, guests].forEach(function (field) {
            if (field) {
                field.addEventListener('change', update);
                field.addEventListener('input', update);
            }
        });

        update();
    }

    /* ------------------------------------------------------------------
       Imagens com fallback local
       ------------------------------------------------------------------ */

    function initImageFallback() {
        function applyFallback(image) {
            var fallback = image.getAttribute('data-fallback');
            if (!fallback || image.getAttribute('data-fallback-applied') === '1') {
                return;
            }
            image.setAttribute('data-fallback-applied', '1');
            image.src = fallback;
            image.classList.add('is-fallback');
        }

        doc.querySelectorAll('img[data-fallback]').forEach(function (image) {
            if (image.complete && image.naturalWidth === 0) {
                applyFallback(image);
                return;
            }
            image.addEventListener('error', function () {
                applyFallback(image);
            });
        });
    }

    /* ------------------------------------------------------------------
       Pesquisa: submissão automática
       ------------------------------------------------------------------ */

    function initAutoSubmit() {
        doc.querySelectorAll('[data-auto-submit]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (field.form) {
                    field.form.submit();
                }
            });
        });
    }

    /* ------------------------------------------------------------------
       Confirmação de ações destrutivas
       ------------------------------------------------------------------ */

    function initConfirmations() {
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            var message = form.getAttribute('data-confirm');
            if (!message) {
                return;
            }
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    }

    /* ------------------------------------------------------------------
       Mostrar/ocultar palavra-passe
       ------------------------------------------------------------------ */

    function initPasswordToggle() {
        doc.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            var wrapper = button.closest('.password-field');
            var input = wrapper ? wrapper.querySelector('input') : null;
            if (!input) {
                return;
            }

            button.addEventListener('click', function () {
                var showing = input.getAttribute('type') === 'text';
                input.setAttribute('type', showing ? 'password' : 'text');
                button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                button.setAttribute('aria-label', showing ? 'Mostrar palavra-passe' : 'Ocultar palavra-passe');
                input.focus();
            });
        });
    }

    /* ------------------------------------------------------------------
       Sombra do cabeçalho ao deslocar
       ------------------------------------------------------------------ */

    function initHeaderScroll() {
        var header = doc.querySelector('[data-site-header]');
        if (!header) {
            return;
        }

        var lastState = false;
        function update() {
            var scrolled = window.scrollY > 8;
            if (scrolled !== lastState) {
                lastState = scrolled;
                header.classList.toggle('is-scrolled', scrolled);
            }
        }

        update();
        window.addEventListener('scroll', update, { passive: true });
    }

    /* ------------------------------------------------------------------
       Pré-visualização da imagem no formulário de alojamento
       ------------------------------------------------------------------ */

    function initImagePreview() {
        var input = doc.querySelector('[data-image-input]');
        var preview = doc.querySelector('[data-image-preview]');
        if (!input || !preview) {
            return;
        }

        var fallback = preview.getAttribute('data-fallback') || '';

        input.addEventListener('change', function () {
            var value = input.value.trim();
            if (value === '') {
                preview.src = fallback;
                return;
            }
            preview.removeAttribute('data-fallback-applied');
            if (/^https?:\/\//i.test(value)) {
                preview.src = value;
            } else if (value.indexOf('assets/') === 0) {
                var base = doc.documentElement.getAttribute('data-base');
                var prefix = base ? base.replace(/\/$/, '') + '/' : '';
                preview.src = prefix + value;
            } else {
                preview.src = fallback;
            }
        });

        preview.addEventListener('error', function () {
            if (fallback && preview.src.indexOf(fallback) === -1) {
                preview.src = fallback;
            }
        });
    }

    onReady(function () {
        initNavigation();
        initAdminMenu();
        initAlerts();
        initDateConstraints();
        initBookingSummary();
        initImageFallback();
        initAutoSubmit();
        initConfirmations();
        initPasswordToggle();
        initHeaderScroll();
        initImagePreview();
    });
})();
