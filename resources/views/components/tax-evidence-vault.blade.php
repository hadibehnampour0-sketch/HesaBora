@props(['evidences', 'storeUrl'])

<div class="today-panel">
    <div class="today-panel-title">{{ __('Tax evidence') }}</div>
    <p class="mt-1 text-xs text-base-content/60">{{ __('Supporting documents you can show a tax auditor for this record.') }}</p>

    @if ($evidences->isNotEmpty())
        <ul class="mt-3 divide-y divide-base-200 text-sm">
            @foreach ($evidences as $evidence)
                <li class="flex items-center justify-between py-1.5">
                    <span>
                        <span class="font-medium">{{ $evidence->title }}</span>
                        <span class="text-xs text-base-content/50">({{ \App\Enums\TaxEvidenceType::from($evidence->document_type)->label() }})</span>
                    </span>
                    <span class="flex items-center gap-1">
                        @can('tax-evidences.download')
                            <a href="{{ route('tax-evidences.download', $evidence) }}" class="btn btn-ghost btn-xs">{{ __('Download') }}</a>
                        @endcan
                        @can('tax-evidences.destroy')
                            <form method="POST" action="{{ route('tax-evidences.destroy', $evidence) }}" onsubmit="return confirm('{{ __('Delete this evidence?') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-ghost btn-xs text-error">{{ __('Delete') }}</button>
                            </form>
                        @endcan
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @can($storeUrl['permission'] ?? null)
        <form method="POST" action="{{ $storeUrl['url'] }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
            @csrf
            <input type="text" name="title" class="input input-bordered input-sm" placeholder="{{ __('Title') }}" maxlength="255" required>
            <select name="document_type" class="select select-bordered select-sm" required>
                @foreach (\App\Enums\TaxEvidenceType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            <input type="file" name="file" class="file-input file-input-bordered file-input-sm sm:col-span-2" required>
            <button class="btn btn-sm btn-outline sm:col-span-4">{{ __('Attach evidence') }}</button>
        </form>
    @endcan
</div>
