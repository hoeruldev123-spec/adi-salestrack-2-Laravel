<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\Master\ProductResource;
use App\Models\Product;
use App\Services\Sales\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request)
    {
        $products = $this->productService->paginate($request->only([
            'search', 'type', 'principal_id', 'status', 'per_page',
        ]));

        return ApiResponse::paginated(ProductResource::collection($products), 'Products retrieved successfully');
    }

    public function show(Product $product)
    {
        return ApiResponse::success(
            new ProductResource($product->load('principal')),
            'Product retrieved successfully'
        );
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create($request->validated());

        return ApiResponse::created(
            new ProductResource($product->load('principal')),
            'Product created successfully'
        );
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->productService->update($product, $request->validated());

        return ApiResponse::success(new ProductResource($product), 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return ApiResponse::success(null, 'Product deleted successfully');
    }
}
