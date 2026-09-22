<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * P7.2 (Segregation of Duties). Opt-in per company (default OFF, see
 * `self_approval_prevented` in WorkflowGovernanceSettingsService) --
 * chosen over a hard, always-on block because most of this app's target
 * SMB customers are a single bookkeeper who both creates and approves
 * their own documents; forcing a hard block would make the app
 * immediately unusable for that majority. Every self-approval event is
 * logged to the activity log regardless of whether the setting is on,
 * so a company can audit its own history before/without ever turning
 * enforcement on.
 */
class SegregationOfDutiesService
{
    public function isSelfApprovalPrevented(): bool
    {
        return (bool) config('amir.self_approval_prevented');
    }

    /**
     * Call this at the exact moment a user attempts to approve a document,
     * before any financial effect happens. Throws only when the company
     * setting is on AND the approver is the same user who created the
     * document being approved.
     */
    public function guardAgainstSelfApproval(Model $document, ?int $creatorId, string $documentTypeLabel): void
    {
        $currentUserId = auth()->id();

        if ($creatorId === null || $currentUserId === null || $creatorId !== $currentUserId) {
            return;
        }

        activity('security')
            ->causedBy(auth()->user())
            ->performedOn($document)
            ->withProperties([
                'is_self_approved' => true,
                'document_type' => $documentTypeLabel,
                'company_id' => getActiveCompany(),
            ])
            ->log('self_approval');

        if ($this->isSelfApprovalPrevented()) {
            throw ValidationException::withMessages([
                'approval' => [__('Per company governance settings, a document cannot be approved by the user who created it.')],
            ]);
        }
    }
}
