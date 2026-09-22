<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttendanceLogsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceLogController extends Controller
{
    use ApiResponds;

    public function __construct(private readonly \App\Http\Controllers\Api\AttendanceLogController $legacy) {}

    public function index(Request $request): JsonResponse
    {
        return $this->fromLegacy($this->legacy->index($request));
    }

    public function store(StoreAttendanceLogsRequest $request): JsonResponse
    {
        return $this->fromLegacy($this->legacy->store($request));
    }
}
