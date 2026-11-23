<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Http\Resources\Master\PriceListResource;
use App\Models\PriceList;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PriceList::class, 'price_list');
    }

    public function index(Request $request)
    {
        $prices = PriceList::query()
            ->with('product')
            ->when($request->query('product_id'), fn ($query, $id) => $query->where('product_id', $id))
            ->when($request->query('pricing_type'), fn ($query, $type) => $query->where('pricing_type', $type))
            ->when($request->query('is_active') !== null, fn ($query) => $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            PriceListResource::collection($prices),
            'Price lists retrieved successfully'
        );
    }

    public function show(PriceList $priceList)
    {
        return ApiResponse::success(
            new PriceListResource($priceList->load('product')),
            'Price list retrieved successfully'
        );
    }

    public function store(StorePriceListRequest $request)
    {
        $priceList = PriceList::create($request->validated());

        return ApiResponse::created(
            new PriceListResource($priceList->load('product')),
            'Price list created successfully'
        );
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList)
    {
        $priceList->update($request->validated());

        return ApiResponse::success(
            new PriceListResource($priceList->fresh('product')),
            'Price list updated successfully'
        );
    }

    public function destroy(PriceList $priceList)
    {
        $priceList->delete();

        return ApiResponse::success(null, 'Price list deleted successfully');
    }
}
