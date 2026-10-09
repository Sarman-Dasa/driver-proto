<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Actions\Invoice\CancelInvoiceAction;
use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\DeleteInvoiceAction;
use App\Actions\Invoice\RestoreInvoiceAction;
use App\Actions\Invoice\SendInvoiceAction;
use App\Actions\Invoice\SendInvoiceEmailAction;
use App\Actions\Invoice\UpdateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Api\V1\Invoice\SendInvoiceEmailRequest;
use App\Http\Requests\Api\V1\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceDeliveryResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    /**
     * List all invoices for a given company.
     */
    public function index(Request $request, Company $company): JsonResponse
    {
        $this->authorize('viewAny', [Invoice::class, $company]);

        $query = $company->invoices()->with(['customer', 'items.product']);

        // Status Filter
        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && $status !== '') {
            if ($status === 'archived' || $status === 'trashed') {
                $query->onlyTrashed();
            } else {
                $query->whereNull('deleted_at');
                $validStatus = InvoiceStatus::tryFrom($status);
                if ($validStatus) {
                    $query->where('status', $validStatus->value);
                }
            }
        } else {
            $query->whereNull('deleted_at');
        }

        // Customer Filter
        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->query('customer_id'));
        }

        // Search Filter
        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        // Date Range Filter
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        if ($fromDate || $toDate) {
            $query->dateRange($fromDate ? (string) $fromDate : null, $toDate ? (string) $toDate : null);
        }

        // Safe Sorting Whitelist
        $allowedSorts = [
            'invoice_number',
            'issue_date',
            'due_date',
            'total_amount',
            'balance_due',
            'status',
            'created_at',
            'updated_at',
        ];
        $sort = (string) $request->query('sort', '-created_at');
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sortField = substr($sort, 1);
        } else {
            $sortField = $sort;
        }

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Bounded Pagination
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $paginator = $query->paginate($perPage);

        return ApiResponse::success(
            $paginator->through(fn (Invoice $invoice): InvoiceResource => new InvoiceResource($invoice)),
            'Invoices retrieved successfully.',
        );
    }

    /**
     * Create an invoice as a draft.
     */
    public function store(
        CreateInvoiceRequest $request,
        Company $company,
        CreateInvoiceAction $action,
    ): JsonResponse {
        $this->authorize('create', [Invoice::class, $company]);

        try {
            $invoice = $action->execute($company, $request->validated(), $request->user());

            return ApiResponse::success(
                new InvoiceResource($invoice),
                'Invoice created successfully.',
                201,
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), [], 403);
        }
    }

    /**
     * View a specific invoice.
     */
    public function show(Company $company, Invoice $invoice): JsonResponse
    {
        // Enforce company boundary
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('view', [$invoice, $company]);

        $invoice->load(['customer', 'items.product', 'deliveries', 'payments']);

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully.',
        );
    }

    /**
     * Update an existing draft invoice.
     */
    public function update(
        UpdateInvoiceRequest $request,
        Company $company,
        Invoice $invoice,
        UpdateInvoiceAction $action,
    ): JsonResponse {
        // Enforce company boundary
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('update', [$invoice, $company]);

        $invoice = $action->execute($company, $invoice, $request->validated(), $request->user());

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice updated successfully.',
        );
    }

    /**
     * Soft delete a draft invoice.
     */
    public function destroy(
        Company $company,
        Invoice $invoice,
        DeleteInvoiceAction $action,
    ): JsonResponse {
        // Enforce company boundary
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('delete', [$invoice, $company]);

        $action->execute($company, $invoice);

        return ApiResponse::success(null, 'Invoice deleted successfully.');
    }

    /**
     * Restore a soft-deleted draft invoice.
     */
    public function restore(
        Company $company,
        int|string $invoiceId,
        RestoreInvoiceAction $action,
    ): JsonResponse {
        /** @var Invoice $invoice */
        $invoice = Invoice::onlyTrashed()
            ->where('company_id', $company->id)
            ->where('id', (int) $invoiceId)
            ->firstOrFail();

        $this->authorize('restore', [$invoice, $company]);

        $action->execute($company, $invoice);

        return ApiResponse::success(
            new InvoiceResource($invoice->load(['customer', 'items.product'])),
            'Invoice restored successfully.',
        );
    }

    /**
     * Transition invoice from draft to sent.
     */
    public function send(
        Request $request,
        Company $company,
        Invoice $invoice,
        SendInvoiceAction $action,
    ): JsonResponse {
        // Enforce company boundary
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('send', [$invoice, $company]);

        $invoice = $action->execute($company, $invoice, $request->user());

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice sent successfully.',
        );
    }

    /**
     * Cancel an invoice.
     */
    public function cancel(
        Request $request,
        Company $company,
        Invoice $invoice,
        CancelInvoiceAction $action,
    ): JsonResponse {
        // Enforce company boundary
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('cancel', [$invoice, $company]);

        $invoice = $action->execute($company, $invoice, $request->user());

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice cancelled successfully.',
        );
    }

    /**
     * Download or stream the invoice PDF.
     */
    public function pdf(
        Request $request,
        Company $company,
        Invoice $invoice,
        InvoicePdfService $pdfService,
    ): Response|JsonResponse {
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('view', [$invoice, $company]);

        $attachment = $request->boolean('download', true);

        return $pdfService->downloadResponse($invoice, $attachment);
    }

    /**
     * Send the invoice via email with attached PDF.
     */
    public function email(
        SendInvoiceEmailRequest $request,
        Company $company,
        Invoice $invoice,
        SendInvoiceEmailAction $action,
    ): JsonResponse {
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('send', [$invoice, $company]);

        $delivery = $action->execute($company, $invoice, $request->validated(), $request->user());

        return ApiResponse::success(
            new InvoiceDeliveryResource($delivery),
            'Invoice email queued for delivery.',
        );
    }

    /**
     * Get delivery history for an invoice.
     */
    public function deliveries(
        Company $company,
        Invoice $invoice,
    ): JsonResponse {
        if ((int) $invoice->company_id !== (int) $company->id) {
            return ApiResponse::error('Invoice not found.', [], 404);
        }

        $this->authorize('view', [$invoice, $company]);

        $deliveries = $invoice->deliveries()->latest()->get();

        return ApiResponse::success(
            InvoiceDeliveryResource::collection($deliveries),
            'Invoice deliveries retrieved successfully.',
        );
    }
}
