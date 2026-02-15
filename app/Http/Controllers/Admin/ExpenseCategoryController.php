<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage.expense_categories');
    }

    public function index(): JsonResponse
    {
        $categories = ExpenseCategory::orderBy('name')->get();
        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'description' => 'nullable|string',
        ]);

        $category = ExpenseCategory::create($validated);

        return response()->json([
            'message' => 'Expense category created successfully',
            'category' => $category
        ], 201);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        return response()->json($expenseCategory);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name,' . $expenseCategory->id,
            'description' => 'nullable|string',
        ]);

        $expenseCategory->update($validated);

        return response()->json([
            'message' => 'Expense category updated successfully',
            'category' => $expenseCategory
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with associated expenses'
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json([
            'message' => 'Expense category deleted successfully'
        ]);
    }
}
