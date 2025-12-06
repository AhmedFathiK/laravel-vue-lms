<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Level;
use App\Models\TrashItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrashController extends Controller
{
    /**
     * Display a listing of the trashed items.
     */
    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('view.trash')) {
            abort(403);
        }

        $query = TrashItem::query();

        // Apply filters
        if ($request->has('type')) {
            $query->where('model_type', $request->type);
        }

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'deleted_at');
        $orderBy = $request->get('order_by', 'desc');

        $query->orderBy($sortBy, $orderBy);

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $trashItems = $query->paginate($perPage);

        return response()->json([
            'trash_items' => $trashItems->items(),
            'total_items' => $trashItems->total(),
            'current_page' => $trashItems->currentPage(),
            'per_page' => $trashItems->perPage(),
            'last_page' => $trashItems->lastPage(),
        ]);
    }

    /**
     * Restore a trashed item.
     */
    public function restore(Request $request, $id): JsonResponse
    {
        if (!Gate::allows('restore.trash')) {
            abort(403);
        }

        $trashItem = TrashItem::findOrFail($id);
        $modelClass = $trashItem->model_type;
        $modelId = $trashItem->model_id;

        $model = $modelClass::onlyTrashed()->find($modelId);

        if (!$model) {
            return response()->json(['message' => 'Item not found in trash'], 404);
        }

        $model->restore();

        // The observer will handle deleting the trash item, so we don't do it here.

        return response()->json(['message' => 'Item restored successfully']);
    }

    /**
     * Permanently delete a trashed item.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!Gate::allows('delete.trash')) {
            abort(403);
        }

        $trashItem = TrashItem::findOrFail($id);
        $modelClass = $trashItem->model_type;
        $modelId = $trashItem->model_id;

        // Check if the model exists in the trash
        $model = $modelClass::onlyTrashed()->find($modelId);

        if ($model) {
            // Permanently delete the model
            $model->forceDelete();
        }

        // Delete the trash item record
        $trashItem->delete();

        // Return success response
        return response()->json(['message' => 'Item permanently deleted'], 204);
    }

    /**
     * Empty the trash.
     */
    public function emptyTrash(): JsonResponse
    {
        if (!Gate::allows('delete.trash')) {
            abort(403);
        }

        // Get all trash items
        $trashItems = TrashItem::all();

        foreach ($trashItems as $trashItem) {
            $modelClass = $trashItem->model_type;
            $modelId = $trashItem->model_id;

            // Check if the model exists in the trash
            $model = $modelClass::onlyTrashed()->find($modelId);

            if ($model) {
                // Permanently delete the model
                $model->forceDelete();
            }

            // Delete the trash item record
            $trashItem->delete();
        }

        return response()->json(['message' => 'Trash emptied successfully']);
    }

    /**
     * Get the available model types for filtering.
     */
    public function getModelTypes(): JsonResponse
    {
        if (!Gate::allows('view.trash')) {
            abort(403);
        }

        $modelTypes = TrashItem::select('model_type')
            ->distinct()
            ->get()
            ->map(function ($item) {
                $className = class_basename($item->model_type);
                return [
                    'value' => $item->model_type,
                    'label' => $className,
                ];
            });

        return response()->json($modelTypes);
    }
}
