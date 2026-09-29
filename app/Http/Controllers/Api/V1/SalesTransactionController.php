<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesTransactionRequest;
use App\Http\Requests\UpdateSalesTransactionRequest;
use App\Http\Resources\Sales\SalesTransactionItemResource;
use App\Http\Resources\Sales\SalesTransactionResource;
use App\Models\SalesTransaction;
use App\Services\Sales\SalesTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SalesTransactionController extends Controller
{
    public function __construct(private readonly SalesTransactionService $transactionService)
    {
        $this->authorizeResource(SalesTransaction::class, 'transaction');
    }

    public function index(Request $request)
    {
        $transactions = $this->transactionService->paginate(
            $request->only(['company_id', 'user_id', 'status', 'from', 'to', 'per_page']),
            $request->user()
        );

        return ApiResponse::paginated(
            SalesTransactionResource::collection($transactions),
            'Transactions retrieved successfully'
        );
    }

    public function show(SalesTransaction $transaction)
    {
        return ApiResponse::success(
            new SalesTransactionResource(
                $transaction->load(['opportunity', 'company', 'user', 'items.product'])
            ),
            'Transaction retrieved successfully'
        );
    }

    public function store(StoreSalesTransactionRequest $request)
    {
        $transaction = $this->transactionService->create($request->validated(), $request->user());

        return ApiResponse::created(
            new SalesTransactionResource(
                $transaction->load(['opportunity', 'company', 'user', 'items.product'])
            ),
            'Transaction created successfully'
        );
    }

    public function update(UpdateSalesTransactionRequest $request, SalesTransaction $transaction)
    {
        $transaction = $this->transactionService->update($transaction, $request->validated(), $request->user());

        return ApiResponse::success(
            new SalesTransactionResource(
                $transaction->load(['opportunity', 'company', 'user', 'items.product'])
            ),
            'Transaction updated successfully'
        );
    }

    public function destroy(SalesTransaction $transaction)
    {
        $this->transactionService->destroy($transaction);

        return ApiResponse::success(null, 'Transaction deleted successfully');
    }

    public function items(SalesTransaction $transaction)
    {
        $this->authorize('view', $transaction);

        return ApiResponse::success(
            SalesTransactionItemResource::collection($transaction->items()->with('product')->get()),
            'Transaction items retrieved successfully'
        );
    }
}
