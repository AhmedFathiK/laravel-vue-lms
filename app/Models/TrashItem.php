<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrashItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_type',
        'model_id',
        'name',
        'deleted_at',
        'additional_data',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'additional_data' => 'array',
    ];

    /**
     * Get the parent trashable model.
     */
    public function trashable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include trash items of a given type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('model_type', $type);
    }

    /**
     * Get the model class from the model type.
     */
    public function getModelClass(): string
    {
        return $this->model_type;
    }

    /**
     * Get the model instance.
     */
    public function getModel()
    {
        $modelClass = $this->getModelClass();

        return $modelClass::withTrashed()->find($this->model_id);
    }
}
