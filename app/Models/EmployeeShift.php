<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'employee_id',
        'clock_in',
        'clock_out',
        'breaks',
        'total_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'breaks' => 'array',
            'total_hours' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getDurationInMinutes(): int
    {
        if (!$this->clock_out) {
            return 0;
        }
        $breakMinutes = 0;
        if ($this->breaks) {
            foreach ($this->breaks as $break) {
                if (isset($break['start']) && isset($break['end'])) {
                    $breakMinutes += (strtotime($break['end']) - strtotime($break['start'])) / 60;
                }
            }
        }
        return $this->clock_in->diffInMinutes($this->clock_out) - $breakMinutes;
    }
}
}
