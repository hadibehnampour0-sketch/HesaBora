<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression coverage for the "approved documents are not immutable" risk
 * identified in the read-only architecture audit: DocumentController::update
 * and ::destroy had no approved_at guard at all, so a plain, manually-created,
 * approved accounting document could be freely edited or hard-deleted
 * through the standard "Documents" UI. This only guards the manual-document
 * CRUD screen; it deliberately does not touch DocumentService's internal
 * update/delete methods, which PaymentService/ChequeService/InvoiceService
 * rely on as their own controlled correction mechanism.
 */
class DocumentImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Subject $subjectA;

    private Subject $subjectB;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'documents.update']),
            Permission::firstOrCreate(['name' => 'documents.destroy']),
            Permission::firstOrCreate(['name' => 'documents.create']),
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);

        $this->subjectA = Subject::factory()->create(['company_id' => $this->company->id]);
        $this->subjectB = Subject::factory()->create(['company_id' => $this->company->id]);
    }

    private function manualDocument(bool $approved): Document
    {
        $document = DocumentService::createDocument($this->user, [
            'title' => 'Manual entry',
            'date' => now()->toDateString(),
        ], [
            ['subject_id' => $this->subjectA->id, 'value' => -100, 'desc' => 'Debit'],
            ['subject_id' => $this->subjectB->id, 'value' => 100, 'desc' => 'Credit'],
        ]);

        if ($approved) {
            DocumentService::changeDocumentStatus($document, $this->user, 'approved');
        }

        return $document->fresh();
    }

    private function updatePayload(Document $document): array
    {
        return [
            'title' => 'Edited title',
            'date' => now()->toDateString(),
            'transactions' => $document->transactions->map(fn ($t) => [
                'transaction_id' => $t->id,
                'subject_id' => $t->subject_id,
                'debit' => $t->value < 0 ? abs($t->value) : 0,
                'credit' => $t->value > 0 ? $t->value : 0,
                'desc' => $t->desc,
            ])->values()->all(),
        ];
    }

    public function test_an_approved_manual_document_cannot_be_updated(): void
    {
        $document = $this->manualDocument(approved: true);

        $response = $this->put(route('documents.update', $document), $this->updatePayload($document));

        $response->assertSessionHasErrors([
            'document' => __('This document is approved and cannot be edited or deleted. Unapprove it first.'),
        ]);
        $this->assertSame('Manual entry', $document->fresh()->title);
    }

    public function test_an_approved_manual_document_cannot_be_deleted(): void
    {
        $document = $this->manualDocument(approved: true);

        $response = $this->delete(route('documents.destroy', $document));

        $response->assertSessionHasErrors([
            'document' => __('This document is approved and cannot be edited or deleted. Unapprove it first.'),
        ]);
        $this->assertNotNull(Document::find($document->id));
    }

    public function test_an_unapproved_manual_document_can_still_be_updated_and_deleted(): void
    {
        $document = $this->manualDocument(approved: false);

        $updateResponse = $this->put(route('documents.update', $document), $this->updatePayload($document));
        $updateResponse->assertRedirect(route('documents.index'));
        $this->assertSame('Edited title', $document->fresh()->title);

        $deleteResponse = $this->delete(route('documents.destroy', $document));
        $deleteResponse->assertRedirect(route('documents.index'));
        $this->assertNull(Document::find($document->id));
    }

    public function test_unapproving_an_approved_manual_document_makes_it_editable_again(): void
    {
        $document = $this->manualDocument(approved: true);

        DocumentService::changeDocumentStatus($document, $this->user, 'unapproved');

        $response = $this->put(route('documents.update', $document), $this->updatePayload($document->fresh()));
        $response->assertRedirect(route('documents.index'));
        $this->assertSame('Edited title', $document->fresh()->title);
    }

    public function test_an_approved_document_linked_to_an_invoice_cannot_be_edited_or_deleted_directly(): void
    {
        $document = $this->manualDocument(approved: true);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);
        $warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'number' => 1,
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'warehouse_id' => $warehouse->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 100,
            'document_id' => $document->id,
        ]);
        DocumentService::syncDocumentable($document, $invoice);

        $updateResponse = $this->put(route('documents.update', $document), $this->updatePayload($document->fresh()));
        $updateResponse->assertSessionHasErrors('document');

        $deleteResponse = $this->delete(route('documents.destroy', $document));
        $deleteResponse->assertSessionHasErrors('document');

        $this->assertNotNull(Document::find($document->id));
    }
}
