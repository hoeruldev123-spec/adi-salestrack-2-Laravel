<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Http\Resources\Master\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ProductCategory::class, 'product_category');
    }

    public function index(Request $request)
    {
        $categories = ProductCategory::query()
            ->withCount('products')
            ->when($request->query('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            ProductCategoryResource::collection($categories),
            'Product categories retrieved successfully'
        );
    }

    public function show(ProductCategory $productCategory)
    {
        return ApiResponse::success(
            new ProductCategoryResource($productCategory->loadCount('products')),
            'Product category retrieved successfully'
        );
    }

    public function store(StoreProductCategoryRequest $request)
    {
        $category = ProductCategory::create($request->validated());

        return ApiResponse::created(
            new ProductCategoryResource($category),
            'Product category created successfully'
        );
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validated());

        return ApiResponse::success(
            new ProductCategoryResource($productCategory->fresh()->loadCount('products')),
            'Product category updated successfully'
        );
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();

        return ApiResponse::success(null, 'Product category deleted successfully');
    }
}
