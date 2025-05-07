<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'schema_template',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the clients subscribed to this service.
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_services')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Get the databases associated with this service.
     */
    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }
}
