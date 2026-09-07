<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\AssetStatusEnum;

class Asset extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'location_id',
        'manufacturer_id',
        'assigned_to_user_id',
        'asset_tag',
        'name',
        'serial_number',
        'model_name',
        'purchase_date',
        'purchase_price',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     * Defines how certain attributes should be cast when retrieved from the database.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'status' => AssetStatusEnum::class, // Assuming you might create an Enum for Asset Status later
    ];

    /**
     * Get the category that owns the asset.
     * Defines a many-to-one relationship: many assets belong to one category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the location that the asset is assigned to.
     * Defines a many-to-one relationship: many assets belong to one location.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the manufacturer of the asset.
     * Defines a many-to-one relationship: many assets belong to one manufacturer.
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Get the user that the asset is assigned to.
     * Defines a many-to-one relationship: many assets can be assigned to one user.
     * Uses 'assigned_to_user_id' as the foreign key and 'users' table.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
