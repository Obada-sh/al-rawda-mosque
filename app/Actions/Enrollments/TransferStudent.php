<?php

namespace App\Actions\Enrollments;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Enrollments\TransferRequest;
use App\Models\Circle;
use App\Models\CircleTransfer;
use App\Models\Enrollment;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a student to another circle of the same course.
 *
 * Only the enrollment's current circle changes: the student's attendance,
 * recitations and points belong to the enrollment, so they all appear
 * under the new circle from now on. The move is written to the transfers
 * log, which is for display only.
 */
final class TransferStudent
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation enrollment.left | circle.other_course | circle.inactive
     *                               | transfer.same_circle | transfer.before_enrollment | transfer.before_previous
     */
    public function handle(Enrollment $enrollment, array $input): CircleTransfer
    {
        $data = TransferRequest::validateData($input);

        return DB::transaction(function () use ($enrollment, $data): CircleTransfer {
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());
            $target = Circle::query()->findOrFail($data['to_circle_id']);
            $transferredOn = Dates::day($data['transferred_on']);

            $this->assertTransferable($enrollment, $target, $transferredOn);

            $transfer = CircleTransfer::query()->create([
                'enrollment_id' => $enrollment->getKey(),
                'from_circle_id' => $enrollment->circle_id,
                'to_circle_id' => $target->getKey(),
                'transferred_on' => $transferredOn,
                'notes' => $data['notes'] ?? null,
            ]);

            $enrollment->fill(['circle_id' => $target->getKey()])->save();

            return $transfer;
        });
    }

    private function assertTransferable(Enrollment $enrollment, Circle $target, string $transferredOn): void
    {
        if ($enrollment->hasLeft()) {
            throw BusinessRuleViolation::because('enrollment.left', 'الطالب خرج من الدورة ولا يمكن نقله.');
        }

        if ((int) $target->course_id !== (int) $enrollment->course_id) {
            throw BusinessRuleViolation::because('circle.other_course', 'الحلقة الجديدة لا تتبع نفس الدورة.');
        }

        if ((int) $target->getKey() === (int) $enrollment->circle_id) {
            throw BusinessRuleViolation::because('transfer.same_circle', 'الطالب موجود في هذه الحلقة أصلاً.');
        }

        if (! $target->is_active) {
            throw BusinessRuleViolation::because('circle.inactive', 'الحلقة الجديدة غير نشطة.');
        }

        if ($transferredOn < $enrollment->enrolled_on->toDateString()) {
            throw BusinessRuleViolation::because('transfer.before_enrollment', 'تاريخ النقل قبل تسجيل الطالب في الدورة.');
        }

        $previous = $enrollment->transfers()->max('transferred_on');

        if ($previous !== null && $transferredOn < substr((string) $previous, 0, 10)) {
            throw BusinessRuleViolation::because('transfer.before_previous', 'تاريخ النقل قبل تاريخ آخر نقل مسجَّل للطالب.');
        }
    }
}
