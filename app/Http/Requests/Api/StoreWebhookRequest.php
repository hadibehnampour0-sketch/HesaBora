<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'event_types' => ['required', 'array', 'min:1'],
            'event_types.*' => ['required', 'string', Rule::in(['invoice.status_changed', 'cheque.status_changed'])],
        ];
    }
}
