<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\Master\CompanyResource;
use App\Models\Company;
use App\Services\Sales\CompanyService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companyService)
    {
        $this->authorizeResource(Company::class, 'company');
    }

    public function index(Request $request)
    {
        $companies = $this->companyService->paginate($request->only(['search', 'status', 'per_page']));

        return ApiResponse::paginated(CompanyResource::collection($companies), 'Companies retrieved successfully');
    }

    public function show(Company $company)
    {
        return ApiResponse::success(
            new CompanyResource($company->loadCount(['opportunities', 'salesTransactions'])),
            'Company retrieved successfully'
        );
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = $this->companyService->create($request->validated());

        return ApiResponse::created(new CompanyResource($company), 'Company created successfully');
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company = $this->companyService->update($company, $request->validated());

        return ApiResponse::success(new CompanyResource($company), 'Company updated successfully');
    }

    public function destroy(Company $company)
    {
        $this->companyService->delete($company);

        return ApiResponse::success(null, 'Company deleted successfully');
    }
}
