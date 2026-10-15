<?php

namespace App\Services\Points;

use App\Enums\PointSource;
use App\Models\ActivityAttendance;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Recitation;
use Carbon\CarbonInterface;

/**
 * The only writer of the points ledger.
 *
 * Automatic points (attendance, recitation, activity) are tied one-to-one
 * to the row that earned them, so correcting or deleting that row
 * corrects the points.
 */
final class PointsLedger
{
    /**
     * Awards the course's attendance points for a new attendance.
     */
    public function awardForAttendance(Attendance $attendance): ?PointTransaction
    {
        $enrollment = $attendance->enrollment;
        $points = $enrollment->course->points_per_attendance;

        if ($points <= 0) {
            return null;
        }

        return PointTransaction::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'source' => PointSource::Attendance,
            'attendance_id' => $attendance->getKey(),
            'points' => $points,
            'occurred_on' => $attendance->session->session_date->toDateString(),
        ]);
    }

    /**
     * Makes the points of a recitation match its new pages:
     * new pages × the rate stored on the recitation.
     */
    public function syncForRecitation(Recitation $recitation): void
    {
        $points = $recitation->new_pages_count * $recitation->points_per_page;

        $existing = PointTransaction::query()
            ->where('recitation_id', $recitation->getKey())
            ->first();

        if ($points <= 0) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'enrollment_id' => $recitation->enrollment_id,
            'source' => PointSource::Recitation,
            'recitation_id' => $recitation->getKey(),
            'points' => $points,
            'occurred_on' => $recitation->recited_on->toDateString(),
        ];

        if ($existing === null) {
            PointTransaction::query()->create($attributes);

            return;
        }

        $existing->fill($attributes);

        if ($existing->isDirty()) {
            $existing->save();
        }
    }

    /**
     * Makes the points of a student who attended an activity match the
     * activity: what it gives, its date and its name. An activity that
     * gives no points leaves no line in the ledger.
     */
    public function syncForActivityAttendance(ActivityAttendance $attendance): void
    {
        $activity = $attendance->activity;

        $existing = PointTransaction::query()
            ->where('activity_attendance_id', $attendance->getKey())
            ->first();

        if ($activity->points <= 0) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'enrollment_id' => $attendance->enrollment_id,
            'source' => PointSource::Activity,
            'activity_attendance_id' => $attendance->getKey(),
            'points' => $activity->points,
            // The ledger says which activity without having to look it up.
            'reason' => $activity->title,
            'occurred_on' => $activity->activity_date->toDateString(),
        ];

        if ($existing === null) {
            PointTransaction::query()->create($attributes);

            return;
        }

        $existing->fill($attributes);

        if ($existing->isDirty()) {
            $existing->save();
        }
    }

    /**
     * Adds a manual award (positive) or penalty (negative).
     */
    public function addManual(Enrollment $enrollment, int $points, string $reason, CarbonInterface $occurredOn): PointTransaction
    {
        return PointTransaction::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'source' => PointSource::Manual,
            'points' => $points,
            'reason' => $reason,
            'occurred_on' => $occurredOn->toDateString(),
        ]);
    }
}
