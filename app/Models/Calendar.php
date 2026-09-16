<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'timezone', 'working_days', 'start_time', 'end_time'])]
class Calendar extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
        ];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(CalendarHoliday::class);
    }

    public function isWorkingDay(Carbon $date): bool
    {
        $local = $date->clone()->setTimezone($this->timezone);

        if (in_array((int) $local->isoWeekday(), $this->working_days ?? [], true) === false) {
            return false;
        }

        return ! $this->holidays()->whereDate('date', $local->toDateString())->exists();
    }

    /**
     * Add $minutes of business-hours time to $from, skipping non-working
     * days, holidays, and time outside the calendar's daily window.
     */
    public function addBusinessMinutes(Carbon $from, int $minutes): Carbon
    {
        $cursor = $from->clone()->setTimezone($this->timezone);
        $dayStart = $this->start_time instanceof Carbon ? $this->start_time->format('H:i:s') : (string) $this->start_time;
        $dayEnd = $this->end_time instanceof Carbon ? $this->end_time->format('H:i:s') : (string) $this->end_time;

        $remaining = $minutes;

        // Snap into the working window if we start outside it.
        $cursor = $this->snapForward($cursor, $dayStart, $dayEnd);

        while ($remaining > 0) {
            $windowEnd = $cursor->clone()->setTimeFromTimeString($dayEnd);
            $availableToday = $cursor->diffInMinutes($windowEnd, false);

            if ($availableToday >= $remaining) {
                $cursor->addMinutes($remaining);
                $remaining = 0;
            } else {
                $remaining -= max($availableToday, 0);
                $cursor = $this->snapForward($cursor->addDay()->setTimeFromTimeString($dayStart), $dayStart, $dayEnd);
            }
        }

        return $cursor->setTimezone('UTC');
    }

    private function snapForward(Carbon $cursor, string $dayStart, string $dayEnd): Carbon
    {
        while (true) {
            if ($this->isWorkingDay($cursor) === false) {
                $cursor = $cursor->addDay()->setTimeFromTimeString($dayStart);

                continue;
            }

            $windowStart = $cursor->clone()->setTimeFromTimeString($dayStart);
            $windowEnd = $cursor->clone()->setTimeFromTimeString($dayEnd);

            if ($cursor->lt($windowStart)) {
                $cursor = $windowStart;
            } elseif ($cursor->gte($windowEnd)) {
                $cursor = $cursor->addDay()->setTimeFromTimeString($dayStart);

                continue;
            }

            return $cursor;
        }
    }
}
