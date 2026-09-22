<x-app-layout :title="__('Tax Readiness')">
    <x-show-message-bags />

    @php
        $statusMeta = [
            'optimal' => ['label' => __('Optimal'), 'badge' => 'badge-success', 'stroke' => '#10b981'],
            'ready' => ['label' => __('Ready'), 'badge' => 'badge-info', 'stroke' => '#3b82f6'],
            'warning' => ['label' => __('Warning'), 'badge' => 'badge-warning', 'stroke' => '#f59e0b'],
            'critical' => ['label' => __('Critical'), 'badge' => 'badge-error', 'stroke' => '#f43f5e'],
        ];
        $metricLabels = [
            'profile_readiness' => __('Tax profile & Moadian credentials'),
            'moadian_compliance' => __('Moadian submission compliance'),
            'evidence_vault_coverage' => __('Evidence vault coverage'),
            'tax_rule_alignment' => __('VAT rule configuration'),
        ];
        $score = $latest->score ?? 0;
        $status = $latest->status ?? 'critical';
        $meta = $statusMeta[$status];
        $radius = 54;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference * (1 - $score / 100);
    @endphp

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-4">
                <span class="card-title">{{ __('Tax Readiness') }}</span>

                <form method="POST" action="{{ route('tax-readiness.recalculate') }}">
                    @csrf
                    <button class="btn btn-primary btn-sm" type="submit">{{ __('Recalculate score') }}</button>
                </form>
            </div>

            @if (! $latest)
                <div class="alert mt-4">{{ __('No score has been calculated yet. Click "Recalculate score" to run the first check.') }}</div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                    <div class="flex flex-col items-center justify-center gap-3">
                        <svg viewBox="0 0 120 120" class="w-44 h-44 -rotate-90">
                            <circle cx="60" cy="60" r="{{ $radius }}" stroke="#e2e8f0" stroke-width="10" fill="transparent" />
                            <circle cx="60" cy="60" r="{{ $radius }}" stroke="{{ $meta['stroke'] }}" stroke-width="10"
                                stroke-linecap="round" fill="transparent"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" />
                            <text x="60" y="60" transform="rotate(90 60 60)" text-anchor="middle" dominant-baseline="middle"
                                class="fill-current" style="font-size: 26px; font-weight: 700;">{{ $score }}</text>
                        </svg>
                        <span class="badge {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                        <span class="text-xs opacity-60">{{ $latest->calculated_at->diffForHumans() }}</span>
                    </div>

                    <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($latest->metrics_breakdown as $key => $metric)
                            @php $ratio = $metric['max'] > 0 ? $metric['score'] / $metric['max'] : 0; @endphp
                            <div class="rounded-xl border border-base-300 p-4">
                                <div class="flex items-center justify-between text-sm font-semibold mb-2">
                                    <span>{{ $metricLabels[$key] ?? $key }}</span>
                                    <span class="opacity-60">{{ $metric['score'] }}/{{ $metric['max'] }}</span>
                                </div>
                                <progress class="progress {{ $ratio >= 0.8 ? 'progress-success' : ($ratio >= 0.5 ? 'progress-warning' : 'progress-error') }} w-full"
                                    value="{{ $metric['score'] }}" max="{{ $metric['max'] }}"></progress>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="font-semibold mb-3">{{ __('Recommendations') }} ({{ count($latest->recommendations) }})</h3>
                    @if (count($latest->recommendations) === 0)
                        <div class="alert alert-success">{{ __('No issues found -- every check passed.') }}</div>
                    @else
                        <ul class="space-y-2">
                            @foreach ($latest->recommendations as $recommendation)
                                <li class="rounded-lg bg-base-200 px-4 py-2 text-sm">{{ $recommendation }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($history->count() > 1)
                    @php
                        $maxScore = 100;
                        $chartWidth = 600;
                        $chartHeight = 120;
                        $step = $chartWidth / max(1, $history->count() - 1);
                        $points = $history->values()->map(fn ($log, $i) => ($i * $step).','.($chartHeight - ($log->score / $maxScore) * $chartHeight))->implode(' ');
                    @endphp
                    <div class="mt-6">
                        <h3 class="font-semibold mb-3">{{ __('Score trend') }}</h3>
                        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="w-full h-32 border border-base-300 rounded-xl bg-base-100">
                            <polyline fill="none" stroke="#3b82f6" stroke-width="2" points="{{ $points }}" />
                            @foreach ($history->values() as $i => $log)
                                <circle cx="{{ $i * $step }}" cy="{{ $chartHeight - ($log->score / $maxScore) * $chartHeight }}" r="3"
                                    fill="{{ $statusMeta[$log->status]['stroke'] }}">
                                    <title>{{ $log->calculated_at->toDateString() }}: {{ $log->score }}</title>
                                </circle>
                            @endforeach
                        </svg>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
