/* AMOEC Track public pages: navbar, scroll reveal, the animated route, the 3D phone and tilting cards.
   Plain JavaScript, no libraries beyond Bootstrap. Everything degrades to a normal static page when
   this file does not run, and the motion is skipped for visitors who ask for reduced motion. */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;

    // ---------- navbar: solid once the page is scrolled or the mobile menu is open ----------
    var nav = document.getElementById('siteNav');
    function navState() {
        if (!nav) return;
        nav.classList.toggle('scrolled', window.scrollY > 24);
    }
    window.addEventListener('scroll', navState, { passive: true });
    navState();
    var menu = document.getElementById('navMenu');
    if (menu && nav) {
        menu.addEventListener('show.bs.collapse', function () { nav.classList.add('show-bg'); });
        menu.addEventListener('hidden.bs.collapse', function () { nav.classList.remove('show-bg'); });
        // tapping a link closes the mobile menu
        menu.querySelectorAll('a.nav-link').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.bootstrap && menu.classList.contains('show')) window.bootstrap.Collapse.getOrCreateInstance(menu).hide();
            });
        });
    }

    // ---------- reveal on scroll ----------
    var items = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && !reduce) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        items.forEach(function (el, i) {
            el.style.transitionDelay = (i % 3) * 80 + 'ms';
            // what is already on screen at load fades in at once; the rest waits for the scroll
            if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
                requestAnimationFrame(function () { el.classList.add('in'); });
            } else {
                io.observe(el);
            }
        });
    } else {
        items.forEach(function (el) { el.classList.add('in'); });
    }

    // ---------- the bus driving along the route ----------
    var path = document.getElementById('routePath');
    var done = document.getElementById('routeDone');
    var bus = document.getElementById('busMarker');
    var bubble = document.getElementById('etaBubble');
    var etaText = document.getElementById('etaText');
    if (path && done && bus && bubble && etaText) {
        var len = path.getTotalLength();
        var PERIOD = 11000, PAUSE = 1400;
        var place = function (t) {
            var p = path.getPointAtLength(len * t);
            var q = path.getPointAtLength(Math.min(len, len * t + 2));
            var ang = Math.atan2(q.y - p.y, q.x - p.x) * 180 / Math.PI * 0.45; // lean a little with the road
            bus.setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ') rotate(' + ang + ')');
            var bx = Math.min(548, Math.max(56, p.x)), by = Math.max(64, p.y);
            bubble.setAttribute('transform', 'translate(' + bx + ' ' + by + ')');
            done.setAttribute('stroke-dasharray', (len * t) + ' ' + len);
            etaText.textContent = t > 0.985 ? 'Arrived' : 'ETA ' + Math.max(1, Math.ceil(12 * (1 - t))) + ' min';
        };
        if (reduce) {
            place(0.45);
        } else {
            var start = null;
            var ease = function (x) { return x < 0.5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2; };
            var frame = function (ts) {
                if (start === null) start = ts;
                var el = (ts - start) % (PERIOD + PAUSE);
                place(el >= PERIOD ? 1 : ease(el / PERIOD) * 0.995 + (el / PERIOD) * 0.005);
                requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        }
    }

    // ---------- the 3D phone: follows the pointer, can be dragged all the way round ----------
    var scene = document.getElementById('phoneScene');
    var phone = document.getElementById('phone');
    var phoneImg = document.getElementById('phoneImg');
    if (scene && phone) {
        var rx = 0, ry = 0, trx = 0, tryy = 0, spin = 0, dragging = false, startX = 0, baseSpin = 0, hover = false, t0 = performance.now();
        var run = function (now) {
            var idle = (!hover && !dragging && !reduce) ? Math.sin((now - t0) / 1600) * 8 : 0;
            if (!dragging && !hover) { trx = 0; }
            var target = tryy + spin + idle;
            rx += (trx - rx) * 0.12;
            ry += (target - ry) * 0.12;
            phone.style.transform = 'rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg)';
            requestAnimationFrame(run);
        };
        requestAnimationFrame(run);

        scene.addEventListener('pointermove', function (e) {
            var r = scene.getBoundingClientRect();
            var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
            if (dragging) {
                spin = baseSpin + (e.clientX - startX) * 0.7;
            } else if (fine) {
                hover = true;
                tryy = x * 36;
            }
            trx = -y * 18;
        });
        scene.addEventListener('pointerleave', function () { hover = false; tryy = 0; if (!dragging) trx = 0; });
        scene.addEventListener('pointerdown', function (e) {
            dragging = true; startX = e.clientX; baseSpin = spin; tryy = 0;
            scene.classList.add('dragging');
            try { scene.setPointerCapture(e.pointerId); } catch (err) { /* older browsers */ }
        });
        var release = function () {
            if (!dragging) return;
            dragging = false; scene.classList.remove('dragging');
            spin = Math.round(spin / 360) * 360;      // settle on the front (or after a full turn, the front again)
        };
        scene.addEventListener('pointerup', release);
        scene.addEventListener('pointercancel', release);

        // screen picker
        var tabs = document.querySelectorAll('#phoneTabs .screen-tab');
        tabs.forEach(function (b) {
            b.addEventListener('click', function () {
                tabs.forEach(function (x) { x.classList.remove('active'); });
                b.classList.add('active');
                if (!phoneImg) return;
                phoneImg.classList.add('swap');
                setTimeout(function () {
                    phoneImg.src = b.getAttribute('data-src');
                    phoneImg.alt = b.getAttribute('data-alt') || '';
                    phoneImg.classList.remove('swap');
                }, 160);
                if (!reduce) spin += 360;                // a full spin shows the change
            });
        });
    }

    // ---------- tilt on hover for the browser frames ----------
    if (fine && !reduce) {
        document.querySelectorAll('[data-tilt]').forEach(function (el) {
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
                el.style.transform = 'perspective(900px) rotateY(' + (x * 9).toFixed(2) + 'deg) rotateX(' + (-y * 7).toFixed(2) + 'deg) scale(1.02)';
            });
            el.addEventListener('pointerleave', function () { el.style.transform = ''; });
        });
    }

    // ---------- click the checksum to copy it ----------
    var sum = document.getElementById('checksum');
    if (sum && navigator.clipboard) {
        sum.style.cursor = 'copy';
        sum.title = 'Click to copy';
        sum.addEventListener('click', function () {
            navigator.clipboard.writeText(sum.textContent.trim()).then(function () {
                var old = sum.textContent; sum.textContent = 'Copied'; setTimeout(function () { sum.textContent = old; }, 1200);
            });
        });
    }
})();
