<?php

namespace App\Services;

use App\Models\TaxEvidence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * P9.6 (Evidence Vault). Uses the `local` disk (storage/app/, never
 * web-exposed) rather than `public` (DocumentFileService's own disk),
 * since tax evidence should never be reachable by a guessable direct URL
 * -- every read goes through TaxEvidenceController's own
 * company-ownership check first.
 */
class TaxEvidenceService
{
    private const DISK = 'local';

    private const BASE_PATH = 'tax-evidence';

    public function store(Model $evidenceable, UploadedFile $file, string $documentType, string $title, int $companyId, int $userId): TaxEvidence
    {
        $fileHash = hash_file('sha256', $file->getRealPath());
        $directory = self::BASE_PATH.'/company_'.$companyId;
        $storedPath = $file->store($directory, self::DISK);

        return TaxEvidence::create([
            'company_id' => $companyId,
            'evidenceable_type' => $evidenceable->getMorphClass(),
            'evidenceable_id' => $evidenceable->getKey(),
            'document_type' => $documentType,
            'title' => $title,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'file_hash' => $fileHash,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * True when the file still exists and its current content hashes to
     * exactly what was recorded at upload time -- false if the file was
     * removed from disk OR modified since.
     */
    public function verifyIntegrity(TaxEvidence $evidence): bool
    {
        if (! Storage::disk(self::DISK)->exists($evidence->file_path)) {
            return false;
        }

        $currentHash = hash_file('sha256', Storage::disk(self::DISK)->path($evidence->file_path));

        return hash_equals($evidence->file_hash, $currentHash);
    }

    public function delete(TaxEvidence $evidence): void
    {
        Storage::disk(self::DISK)->delete($evidence->file_path);
        $evidence->delete();
    }

    public function download(TaxEvidence $evidence)
    {
        return Storage::disk(self::DISK)->download($evidence->file_path, $evidence->file_name);
    }
}
