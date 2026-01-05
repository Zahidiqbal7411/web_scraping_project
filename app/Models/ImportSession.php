<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportSession extends Model
{
    protected $fillable = [
        'saved_search_id',
        'base_url',
        'status',
        'total_jobs',
        'completed_jobs',
        'failed_jobs',
        'total_properties',
        'imported_properties',
        'skipped_properties',
        'split_count',
        'max_depth_reached',
        'started_at',
        'completed_at',
        'error_message',
        'split_details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'split_details' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the saved search associated with this import session
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * Start the import session
     */
    public function start(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
        return $this;
    }

    /**
     * Increment completed jobs count
     */
    public function incrementCompleted(int $propertiesImported = 0, int $propertiesSkipped = 0): self
    {
        $this->increment('completed_jobs');
        $this->increment('imported_properties', $propertiesImported);
        $this->increment('skipped_properties', $propertiesSkipped);
        
        // Check if all jobs are done
        $this->refresh();
        if ($this->completed_jobs + $this->failed_jobs >= $this->total_jobs && $this->total_jobs > 0) {
            $this->markCompleted();
        }
        
        return $this;
    }

    /**
     * Increment failed jobs count
     */
    public function incrementFailed(string $errorMessage = null): self
    {
        $this->increment('failed_jobs');
        
        if ($errorMessage) {
            $existingErrors = $this->error_message ?? '';
            $this->update([
                'error_message' => $existingErrors . ($existingErrors ? "\n" : '') . $errorMessage
            ]);
        }
        
        // Check if all jobs are done
        $this->refresh();
        if ($this->completed_jobs + $this->failed_jobs >= $this->total_jobs && $this->total_jobs > 0) {
            // Mark as failed if majority failed, otherwise completed
            if ($this->failed_jobs > $this->completed_jobs) {
                $this->markFailed('Majority of import jobs failed');
            } else {
                $this->markCompleted();
            }
        }
        
        return $this;
    }

    /**
     * Mark as completed
     */
    public function markCompleted(): self
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
    public function markFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
        return $this;
    }

    /**
     * Cancel the import session
     */
    public function cancel(): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
        return $this;
    }

    /**
     * Add jobs to the session
     */
    public function addJobs(int $count): self
    {
        $this->increment('total_jobs', $count);
        return $this;
    }

    /**
     * Update split statistics
     */
    public function updateSplitStats(int $splitCount, int $maxDepth, array $details = null): self
    {
        $this->update([
            'split_count' => $splitCount,
            'max_depth_reached' => $maxDepth,
            'split_details' => $details,
        ]);
        return $this;
    }

    /**
     * Set estimated total properties
     */
    public function setTotalProperties(int $count): self
    {
        $this->update(['total_properties' => $count]);
        return $this;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_jobs === 0) {
            return 0;
        }
        return round(($this->completed_jobs + $this->failed_jobs) / $this->total_jobs * 100, 1);
    }

    /**
     * Get elapsed time in seconds
     */
    public function getElapsedSeconds(): ?int
    {
        if (!$this->started_at) {
            return null;
        }
        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get estimated time remaining in seconds
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        $elapsed = $this->getElapsedSeconds();
        $progress = $this->getProgressPercentage();
        
        if (!$elapsed || $progress <= 0) {
            return null;
        }
        
        $totalEstimated = ($elapsed / $progress) * 100;
        return max(0, (int) ($totalEstimated - $elapsed));
    }

    /**
     * Check if import is still running
     */
    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Check if import is complete (success or failure)
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }

    /**
     * Get status summary for frontend
     */
    public function getStatusSummary(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'is_running' => $this->isRunning(),
            'is_finished' => $this->isFinished(),
            'progress_percentage' => $this->getProgressPercentage(),
            'total_jobs' => $this->total_jobs,
            'completed_jobs' => $this->completed_jobs,
            'failed_jobs' => $this->failed_jobs,
            'total_properties' => $this->total_properties,
            'imported_properties' => $this->imported_properties,
            'skipped_properties' => $this->skipped_properties,
            'split_count' => $this->split_count,
            'elapsed_seconds' => $this->getElapsedSeconds(),
            'estimated_remaining' => $this->getEstimatedTimeRemaining(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'error_message' => $this->error_message,
        ];
    }
}
