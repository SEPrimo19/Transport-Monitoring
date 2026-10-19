{{--
    The password policy, stated on screen and ticked off live as it is typed.

    The list is rendered from AdminPasswordPolicy::requirements() rather than
    written out here, so the screen can never advertise a rule the validator has
    stopped enforcing. The live ticks are a convenience only -- the server rule
    is the one that decides, and the breach check in particular can only run
    there, so it stays listed but unticked until submit.

    Usage:  @include('partials.password-requirements', ['field' => 'login_password'])
--}}
@php($pwReqs = \App\Support\AdminPasswordPolicy::requirements())
@php($pwMin = \App\Support\AdminPasswordPolicy::MIN_LENGTH)

<ul class="pw-reqs list-unstyled small text-muted mt-2 mb-0" data-pw-for="{{ $field }}">
    @foreach ($pwReqs as $i => $req)
        <li data-rule="{{ ['len', 'case', 'num', 'sym', 'pwned'][$i] ?? '' }}">
            <i class="bi bi-circle"></i><span>{{ $req }}</span>
        </li>
    @endforeach
</ul>

@once
    @push('head')
        <style>
            .pw-reqs li { display: flex; align-items: center; gap: .4rem; line-height: 1.7; }
            .pw-reqs li i { font-size: .75rem; width: 1rem; flex: none; }
            .pw-reqs li.ok { color: var(--bs-success-text-emphasis, #0a5c36); }
            .pw-reqs li.ok i { color: var(--bs-success, #198754); }
            /* Breach hit. Red, because unlike the other four this one cannot be
               satisfied by editing the password a little -- it has to be a
               different password. */
            .pw-reqs li.bad { color: var(--bs-danger-text-emphasis, #a51c2c); }
            .pw-reqs li.bad i { color: var(--bs-danger, #dc3545); }
            .pw-reqs li.checking i { color: var(--bs-secondary, #6c757d); }
        </style>
    @endpush
    @push('scripts')
        <script>
            // Live ticks. Mirrors Illuminate\Validation\Rules\Password, but the
            // server is still the authority -- this only saves a round trip.
            document.querySelectorAll('.pw-reqs').forEach(function (list) {
                var input = document.getElementById(list.dataset.pwFor);
                if (!input) return;
                var min = {{ $pwMin }};
                var tests = {
                    len:  function (v) { return v.length >= min; },
                    case: function (v) { return /[a-z]/.test(v) && /[A-Z]/.test(v); },
                    num:  function (v) { return /\d/.test(v); },
                    sym:  function (v) { return /[^\p{L}\p{N}]/u.test(v); }
                };
                var pwnedLi = list.querySelector('li[data-rule="pwned"]');
                var pwnedTimer = null;
                var pwnedSeq = 0;

                function setPwned(state) {
                    if (!pwnedLi) return;
                    pwnedLi.classList.toggle('ok', state === 'clear');
                    pwnedLi.classList.toggle('bad', state === 'breached');
                    pwnedLi.classList.toggle('checking', state === 'checking');
                    pwnedLi.querySelector('i').className =
                        state === 'clear' ? 'bi bi-check-circle-fill'
                        : state === 'breached' ? 'bi bi-x-circle-fill'
                        : state === 'checking' ? 'bi bi-hourglass-split'
                        : 'bi bi-circle';
                }

                // Have I Been Pwned, by k-anonymity -- the same method the server rule
                // uses. Only the first five characters of the SHA-1 are sent; that
                // prefix matches hundreds of hashes, so it identifies nothing. The
                // password itself never leaves the browser.
                //
                // This used to be server-only, which left the line permanently unticked
                // and reading as broken: four green ticks and one dead circle, with
                // nothing saying it would be judged later. Checking it here is the only
                // way the list can mean what it appears to mean.
                //
                // FAILS NEUTRAL, never green. If the lookup cannot be made -- offline,
                // blocked, no crypto.subtle on an insecure origin -- the line goes back
                // to a plain circle rather than claiming the password is clean. The
                // server still runs the real check at submit.
                function checkPwned(v) {
                    var seq = ++pwnedSeq;
                    if (!window.crypto || !window.crypto.subtle) { setPwned(null); return; }

                    var bytes = new TextEncoder().encode(v);
                    window.crypto.subtle.digest('SHA-1', bytes).then(function (buf) {
                        var hash = Array.from(new Uint8Array(buf)).map(function (b) {
                            return b.toString(16).padStart(2, '0');
                        }).join('').toUpperCase();

                        return fetch('https://api.pwnedpasswords.com/range/' + hash.slice(0, 5), {
                            // No cookies to a third party, and nothing of ours to send.
                            credentials: 'omit', referrerPolicy: 'no-referrer'
                        }).then(function (res) {
                            if (!res.ok) throw new Error('lookup failed');
                            return res.text();
                        }).then(function (body) {
                            if (seq !== pwnedSeq) return;      // a newer keystroke won
                            var suffix = hash.slice(5);
                            var hit = body.split('\n').some(function (line) {
                                return line.slice(0, 35).toUpperCase() === suffix;
                            });
                            setPwned(hit ? 'breached' : 'clear');
                        });
                    }).catch(function () {
                        if (seq === pwnedSeq) setPwned(null);
                    });
                }

                function paint() {
                    var v = input.value || '';
                    var basicsPass = true;
                    list.querySelectorAll('li[data-rule]').forEach(function (li) {
                        var t = tests[li.dataset.rule];
                        if (!t) return;                       // 'pwned' handled below
                        var ok = v !== '' && t(v);
                        if (!ok) basicsPass = false;
                        li.classList.toggle('ok', ok);
                        li.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
                    });

                    // Debounced, and only once the other four pass: no point spending a
                    // request on every keystroke of a password that is going to be
                    // rejected on length anyway.
                    window.clearTimeout(pwnedTimer);
                    pwnedSeq++;                                // cancel any in-flight result
                    if (!basicsPass) { setPwned(null); return; }
                    setPwned('checking');
                    pwnedTimer = window.setTimeout(function () { checkPwned(v); }, 500);
                }
                input.addEventListener('input', paint);
                paint();
            });
        </script>
    @endpush
@endonce
