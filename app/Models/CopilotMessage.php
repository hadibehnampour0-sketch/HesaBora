<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'company_id',
        'role',
        'content',
        'tool_calls',
        'sources',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'sources' => 'array',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $message) {
            $message->company_id ??= getActiveCompany();
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CopilotConversation::class, 'conversation_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
