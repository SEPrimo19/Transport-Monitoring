/* AMOEC Track public pages: navbar, scroll reveal, the animated route, the 3D phone and tilting cards.
   Plain JavaScript, no libraries beyond Bootstrap. Everything degrades to a normal static page when this file
   does not run (the page head falls back to the plain version after 2.5 s), and the motion is skipped for
   visitors who ask for reduced motion. The two animation loops run only while their scene is on screen. */
(function () {
    'use strict';

    window.__landingReady = true;    // tells the page head that the script loaded (see layouts/public.blade.php)

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
    var hasIO = 'IntersectionObserver' in window;
    var ua = navigator.userAgent || '';
    var isAndroid = /Android/i.test(ua);
    var isIOS = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var paused = false;              // the visitor pressed Pause (WCAG 2.2.2: moving content must be stoppable)
    var motionHooks = [];

    // ---------- hero download button: straight to the file on an Android phone, to the Download section elsewhere ----------
    var heroBtn = document.getElementById('heroDownload');
    var heroNote = document.getElementById('heroNote');
    var iosNote = document.getElementById('iosNote');
    if (heroBtn && isAndroid) {
        heroBtn.href = heroBtn.getAttribute('data-apk');
        if (heroNote) heroNote.textContent = 'Tap to download now, then open the file to install it.';
    } else if (isIOS) {
        if (heroNote) heroNote.textContent = 'AMOEC Track is Android only for now. A version for iPhone is planned.';
        if (iosNote) iosNote.hidden = false;
    }

    // ---------- pause / play for the animations ----------
    var toggle = document.getElementById('motionToggle');
    if (toggle) {
        if (reduce) {
            toggle.hidden = true;                     // nothing moves, so nothing to pause
        } else {
            toggle.addEventListener('click', function () {
                paused = !paused;
                document.documentElement.classList.toggle('motion-paused', paused);
                toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
                toggle.setAttribute('aria-label', paused ? 'Play the animation' : 'Pause the animation');
                toggle.querySelector('i').className = 'bi ' + (paused ? 'bi-play-fill' : 'bi-pause-fill');
                toggle.querySelector('span').textContent = paused ? 'Play' : 'Pause';
                motionHooks.forEach(function (h) { h(); });
            });
        }
    }

    /** Calls start() while `el` is on screen and stop() when it is not (or always start once when there is no observer). */
    function whileVisible(el, start, stop) {
        if (!el) return;
        if (!hasIO) { start(); return; }
        new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) start(); else stop(); });
        }, { threshold: 0.05 }).observe(el);
    }

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
    if (hasIO && !reduce) {
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

    // ---------- one ride: a passenger waits, the bus comes, the passenger boards, the bus goes on ----------
    var stage = document.getElementById('routeStage');
    var path = document.getElementById('routePath');
    var done = document.getElementById('routeDone');
    var bus = document.getElementById('busMarker');
    var bubble = document.getElementById('etaBubble');
    var etaText = document.getElementById('etaText');
    var waiter = document.getElementById('waiter');
    var stepItems = document.querySelectorAll('#routeSteps li');
    if (stage && path && done && bus && bubble && etaText && waiter) {
        var len = path.getTotalLength();
        var STOP = 0.42;                                   // where along the route the passenger waits
        var A = 6500, B = 1800, C = 6500, D = 1800;        // approach, boarding, ride, arrival (ms)
        var CYCLE = A + B + C + D;

        // put the passenger beside the route at the stop, on the side with more room (up and to the left)
        (function () {
            var p = path.getPointAtLength(len * STOP), q = path.getPointAtLength(len * STOP + 3);
            var dx = q.x - p.x, dy = q.y - p.y, n = Math.sqrt(dx * dx + dy * dy) || 1;
            var nx = -dy / n, ny = dx / n;                 // a normal of the route
            if (ny > 0) { nx = -nx; ny = -ny; }            // pick the one that points up
            waiter.setAttribute('transform', 'translate(' + (p.x + nx * 36) + ' ' + (p.y + ny * 36) + ')');
        })();

        var ease = function (x) { return x < 0.5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2; };
        var setStep = function (n) {
            stepItems.forEach(function (li) { li.classList.toggle('active', Number(li.getAttribute('data-step')) === n); });
        };
        var place = function (t, label) {
            var p = path.getPointAtLength(len * t);
            var a0 = path.getPointAtLength(Math.max(0, len * t - 6)), a1 = path.getPointAtLength(Math.min(len, len * t + 6));
            var heading = Math.atan2(a1.y - a0.y, a1.x - a0.x) * 180 / Math.PI + 90;   // the marker points up at 0 deg
            bus.setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ') rotate(' + heading.toFixed(1) + ')');
            var bx = Math.min(536, Math.max(64, p.x)), by = Math.max(68, p.y - 20);
            bubble.setAttribute('transform', 'translate(' + bx + ' ' + by + ')');
            done.setAttribute('stroke-dasharray', (len * t) + ' ' + len);
            etaText.textContent = label;
        };
        var frameAt = function (el) {
            var t, label, step, show = 1;
            if (el < A) {                                  // the bus comes to the passenger
                var p = el / A;
                t = ease(p) * STOP;
                label = p > 0.97 ? 'Bus is here' : 'ETA ' + Math.max(1, Math.ceil(6 * (1 - p))) + ' min';
                step = p < 0.16 ? 1 : 2;
            } else if (el < A + B) {                       // boarding
                var k = (el - A) / B;
                t = STOP; label = k < 0.5 ? 'Boarding' : 'Boarded'; step = 3; show = k < 0.5 ? 1 : 0;
            } else if (el < A + B + C) {                   // the ride to the destination
                var r = (el - A - B) / C;
                t = STOP + ease(r) * (1 - STOP);
                label = r > 0.97 ? 'Arriving' : 'Calbayog in ' + Math.max(1, Math.ceil(8 * (1 - r))) + ' min';
                step = 3; show = 0;
            } else {                                       // arrived
                t = 1; label = 'Arrived'; step = 4; show = 0;
            }
            place(t, label);
            waiter.style.opacity = show;
            setStep(step);
        };
        if (reduce) {
            frameAt(A * 0.55);                             // one still picture, no loop
        } else {
            var raf = 0, clock = 0, last = null, onScreen = false;
            var frame = function (ts) {
                if (paused) { raf = 0; last = null; return; }          // stop the loop while paused
                if (last === null) last = ts;
                clock += ts - last; last = ts;
                frameAt(clock % CYCLE);
                raf = requestAnimationFrame(frame);
            };
            var kick = function () { if (onScreen && !paused && !raf) { last = null; raf = requestAnimationFrame(frame); } };
            motionHooks.push(kick);
            whileVisible(stage,
                function () { onScreen = true; kick(); },
                function () { onScreen = false; if (raf) { cancelAnimationFrame(raf); raf = 0; last = null; } });
            frameAt(0);                                    // a first frame at once, before the loop starts
        }
    }

    // ---------- the 3D phone: follows the pointer, can be dragged all the way round ----------
    var scene = document.getElementById('phoneScene');
    var phone = document.getElementById('phone');
    var phoneImg = document.getElementById('phoneImg');
    var tabs = document.querySelectorAll('#phoneTabs .screen-tab');

    // screen picker (works with or without the 3D motion). One function shows a screen, so the tabs,
    // the previous / next buttons and the automatic slideshow all behave the same way.
    var current = 0;
    var showScreen = function (i) {
        if (!tabs.length) return;
        current = (i + tabs.length) % tabs.length;
        var b = tabs[current];
        tabs.forEach(function (x) { x.classList.remove('active'); x.setAttribute('aria-pressed', 'false'); });
        b.classList.add('active');
        b.setAttribute('aria-pressed', 'true');
        var count = document.getElementById('screenCount');
        if (count) count.textContent = (current + 1) + ' / ' + tabs.length;
        if (!phoneImg) return;
        phoneImg.classList.add('swap');
        setTimeout(function () {
            phoneImg.src = b.getAttribute('data-src');
            phoneImg.alt = b.getAttribute('data-alt') || '';
            phoneImg.classList.remove('swap');
        }, 160);
        if (scene && !reduce) spin += 360;           // a full spin shows the change
    };

    // Automatic slideshow: moves on every 5.5 s while the section is on screen, the tab is visible and the
    // visitor is not pointing at or dragging the phone, the picker or the buttons. It never starts for visitors who
    // asked for reduced motion, and the pause button (WCAG 2.2.2) stops it for good; any manual choice restarts the clock.
    var SLIDE_MS = 5500;
    var slideTimer = 0, slideOn = !reduce, slideVisible = false, slideHeld = false;
    var playBtn = document.getElementById('screenPlay');
    var slideTick = function () {
        slideTimer = 0;
        if (slideOn && slideVisible && !slideHeld && !paused && !document.hidden) showScreen(current + 1);
        slideStart();
    };
    var slideStart = function () {
        if (slideTimer) clearTimeout(slideTimer);
        slideTimer = slideOn ? setTimeout(slideTick, SLIDE_MS) : 0;
    };
    var syncPlayBtn = function () {
        if (!playBtn) return;
        playBtn.setAttribute('aria-pressed', slideOn ? 'true' : 'false');
        playBtn.setAttribute('aria-label', slideOn ? 'Pause the automatic slideshow' : 'Play the automatic slideshow');
        var ic = playBtn.querySelector('i');
        if (ic) ic.className = 'bi ' + (slideOn ? 'bi-pause-fill' : 'bi-play-fill');
    };

    tabs.forEach(function (b, i) {
        b.addEventListener('click', function () { showScreen(i); slideStart(); });
    });
    var prevBtn = document.getElementById('screenPrev');
    var nextBtn = document.getElementById('screenNext');
    if (prevBtn) prevBtn.addEventListener('click', function () { showScreen(current - 1); slideStart(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { showScreen(current + 1); slideStart(); });
    if (playBtn) playBtn.addEventListener('click', function () { slideOn = !slideOn; syncPlayBtn(); slideStart(); });
    syncPlayBtn();

    var slideArea = document.getElementById('screens');
    if (slideArea && tabs.length) {
        whileVisible(slideArea, function () { slideVisible = true; }, function () { slideVisible = false; });
        ['phoneScene', 'phoneTabs', 'screenNav'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('pointerenter', function () { slideHeld = true; });
            el.addEventListener('pointerleave', function () { slideHeld = false; slideStart(); });
            el.addEventListener('focusin', function () { slideHeld = true; });
            el.addEventListener('focusout', function () { slideHeld = false; slideStart(); });
        });
        slideStart();
    }

    var spin = 0;
    if (scene && phone && !reduce) {
        var rx = 0, ry = 0, trx = 0, tryy = 0, dragging = false, startX = 0, baseSpin = 0, hover = false, t0 = performance.now();
        var rafP = 0;
        var run = function (now) {
            var idle = (!hover && !dragging && !paused) ? Math.sin((now - t0) / 1600) * 8 : 0;
            var target = tryy + spin + idle;
            rx += (trx - rx) * 0.12;
            ry += (target - ry) * 0.12;
            phone.style.transform = 'rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg)';
            rafP = requestAnimationFrame(run);
        };
        whileVisible(scene,
            function () { if (!rafP) rafP = requestAnimationFrame(run); },
            function () { if (rafP) { cancelAnimationFrame(rafP); rafP = 0; } });

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

    // ---------- click the checksum to copy it (the hash itself is never rewritten) ----------
    var sum = document.getElementById('checksum');
    var note = document.getElementById('copyNote');
    if (sum && navigator.clipboard) {
        var timer = 0;
        sum.addEventListener('click', function () {
            var say = function (msg) {
                if (!note) return;
                note.textContent = msg;
                clearTimeout(timer);
                timer = setTimeout(function () { note.textContent = ''; }, 2200);
            };
            navigator.clipboard.writeText(sum.getAttribute('data-hash')).then(
                function () { say('Checksum copied'); },
                function () { say('Could not copy. Select the checksum and copy it by hand.'); }   // blocked or page not focused
            );
        });
    }
})();
