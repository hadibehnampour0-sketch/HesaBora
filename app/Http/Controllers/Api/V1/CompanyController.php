<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use ApiResponds;

    public function __construct(private readonly \App\Http\Controllers\Api\CompanyController $legacy) {}

    public function index(Request $request): JsonResponse
    {
        return $this->fromLegacy($this->legacy->index($request));
    }
}
