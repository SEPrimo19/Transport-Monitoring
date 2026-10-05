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
                function paint() {
                    var v = input.value || '';
                    list.querySelectorAll('li[data-rule]').forEach(function (li) {
                        var t = tests[li.dataset.rule];
                        if (!t) return;                       // 'pwned' is server-side only
                        var ok = v !== '' && t(v);
                        li.classList.toggle('ok', ok);
                        li.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
                    });
                }
                input.addEventListener('input', paint);
                paint();
            });
        </script>
    @endpush
@endonce
