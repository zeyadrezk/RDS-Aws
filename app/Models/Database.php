<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Database extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'service_id',
        'name',
        'instance_identifier',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'status',
        'rds_instance_id',
        'engine',
        'engine_version',
        'instance_class',
        'storage_type',
        'allocated_storage',
        'encrypted',
        'provisioning_status',
        'error_message',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'port' => 'integer',
        'allocated_storage' => 'integer',
        'encrypted' => 'boolean',
    ];

    /**
     * Get the client that owns the database.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the service associated with the database.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
