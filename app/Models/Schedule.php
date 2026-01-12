<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'saved_search_id',
        'name',
        'url',
        'status',
        'total_properties',
        'imported_count',
        'current_page',
        'total_pages',
        'import_session_id',
        'url_import_completed',
        'property_import_completed',
        'sold_import_completed',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_IMPORTING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_FAILED = 3;

    /**
     * Status labels for display
     */
    const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_IMPORTING => 'Importing',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_FAILED => 'Failed',
    ];

    /**
     * Status colors for UI
     */
    const STATUS_COLORS = [
        self::STATUS_PENDING => 'yellow',
        self::STATUS_IMPORTING => 'blue',
        self::STATUS_COMPLETED => 'green',
        self::STATUS_FAILED => 'red',
    ];

    /**
     * Get the saved search associated with this schedule
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * Get the import session associated with this schedule
     */
    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    /**
     * Check if schedule is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if schedule is importing
     */
    public function isImporting(): bool
    {
        return $this->status === self::STATUS_IMPORTING;
    }

    /**
     * Check if schedule is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if schedule failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark as importing
     */
    public function markAsImporting(): self
    {
        $this->update([
            'status' => self::STATUS_IMPORTING,
            'started_at' => now(),
            'error_message' => null,
        ]);
        return $this;
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
        return $this;
    }

    /**
     * Reset to pending (for retry)
     */
    public function resetToPending(): self
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'imported_count' => 0,
            'current_page' => 0,
            'total_pages' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
        return $this;
    }

    /**
     * Update progress
     */
    public function updateProgress(int $currentPage, int $importedCount): self
    {
        $this->update([
            'current_page' => $currentPage,
            'imported_count' => $importedCount,
        ]);
        return $this;
    }

    /**
     * Mark URL import as complete
     */
    public function markUrlImportComplete(): self
    {
        $this->update(['url_import_completed' => true]);
        return $this;
    }

    /**
     * Mark property details import as complete
     */
    public function markPropertyImportComplete(): self
    {
        $this->update(['property_import_completed' => true]);
        return $this;
    }

    /**
     * Mark sold data import as complete
     */
    public function markSoldImportComplete(): self
    {
        $this->update(['sold_import_completed' => true]);
        return $this;
    }

    /**
     * Set total pages and properties
     */
    public function setTotals(int $totalProperties, int $totalPages): self
    {
        $this->update([
            'total_properties' => $totalProperties,
            'total_pages' => $totalPages,
        ]);
        return $this;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_pages === 0) {
            return 0;
        }
        return round(($this->current_page / $this->total_pages) * 100, 1);
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Unknown';
    }

    /**
     * Get status color
     */
    public function getStatusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    /**
     * Get elapsed time in human readable format
     */
    public function getElapsedTime(): ?string
    {
        if (!$this->started_at) {
            return null;
        }
        
        $endTime = $this->completed_at ?? now();
        $seconds = $this->started_at->diffInSeconds($endTime);
        
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return "{$minutes}m {$secs}s";
    }
}
