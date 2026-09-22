<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttachDocumentFileRequest;
use App\Http\Requests\Api\StoreDocumentRequest;
use App\Services\DocumentFileService;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    use ApiResponds;

    public function __construct(private readonly \App\Http\Controllers\Api\DocumentController $legacy) {}

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        return $this->fromLegacy($this->legacy->store($request));
    }

    public function show(int $company, int $documentId): JsonResponse
    {
        return $this->fromLegacy($this->legacy->show($company, $documentId));
    }

    public function attachFile(
        AttachDocumentFileRequest $request,
        DocumentFileService $documentFileService,
        int $company,
        int $documentId
    ): JsonResponse {
        return $this->fromLegacy($this->legacy->attachFile($request, $documentFileService, $company, $documentId));
    }
}
