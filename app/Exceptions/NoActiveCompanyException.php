<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class NoActiveCompanyException extends RuntimeException
{
    public function __construct(string $message = 'No active company context is set.')
    {
        parent::__construct($message);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => __('No active company context. A valid company must be selected or provided.'),
            ], 422);
        }

        return redirect()->route('companies.index')
            ->with('error', __('Please select or create an active company to continue.'));
    }
}
