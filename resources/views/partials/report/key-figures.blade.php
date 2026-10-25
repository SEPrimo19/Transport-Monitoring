{{-- Headline numbers at the top of a report. Pass $keyFigures: a list of ['label' => string, 'value' => string]. A value that was not measured is passed as a dash by the caller, never as 0. --}}
<div class="figures">
    @foreach ($keyFigures as $figure)
        <div class="figure">
            <div class="v">{{ $figure['value'] }}</div>
            <div class="l">{{ $figure['label'] }}</div>
        </div>
    @endforeach
</div>
