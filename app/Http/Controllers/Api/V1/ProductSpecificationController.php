<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductSpecificationRequest;
use App\Http\Requests\UpdateProductSpecificationRequest;
use App\Http\Resources\Master\ProductSpecificationResource;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProductSpecificationController extends Controller
{
    public function index(Request $request, Product $product)
    {
        $this->authorize('viewAny', ProductSpecification::class);

        $specifications = $product->specifications()
            ->when($request->query('search'), fn ($query, $search) => $query->where('specification_name', 'like', "%{$search}%"))
            ->orderBy('specification_name')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated(
            ProductSpecificationResource::collection($specifications),
            'Product specifications retrieved successfully'
        );
    }

    public function store(StoreProductSpecificationRequest $request, Product $product)
    {
        $this->authorize('create', ProductSpecification::class);

        $specification = $product->specifications()->create($request->validated());

        return ApiResponse::created(
            new ProductSpecificationResource($specification),
            'Product specification created successfully'
        );
    }

    public function update(UpdateProductSpecificationRequest $request, ProductSpecification $specification)
    {
        $this->authorize('update', $specification);

        $specification->update($request->validated());

        return ApiResponse::success(
            new ProductSpecificationResource($specification->fresh()),
            'Product specification updated successfully'
        );
    }

    public function destroy(ProductSpecification $specification)
    {
        $this->authorize('delete', $specification);

        $specification->delete();

        return ApiResponse::success(null, 'Product specification deleted successfully');
    }
}
