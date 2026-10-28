<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoryFlow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query()->withCount('movements');

        if ($request->filled('flow')) {
            $query->where('flow', $request->input('flow'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return CategoryResource::collection($query->orderBy('name')->get());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        $this->auditService->log(
            event: 'category.created',
            model: $category,
            old: null,
            new: $category->toArray(),
            userId: $request->user()->id
        );

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $category->loadCount('movements');

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $old = $category->toArray();
        $category->update($request->validated());

        $this->auditService->log(
            event: 'category.updated',
            model: $category,
            old: $old,
            new: $category->toArray(),
            userId: $request->user()->id
        );

        return new CategoryResource($category);
    }
}
