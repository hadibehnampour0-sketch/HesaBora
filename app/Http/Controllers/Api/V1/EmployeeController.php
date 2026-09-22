<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use ApiResponds;

    public function __construct(private readonly \App\Http\Controllers\Api\EmployeeController $legacy) {}

    public function index(Request $request): JsonResponse
    {
        return $this->fromLegacy($this->legacy->index($request));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        return $this->fromLegacy($this->legacy->store($request));
    }
}
