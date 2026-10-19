<?php

namespace App\Services\Notifications;

use App\Contracts\AdminNotifier;
use App\Filament\Pages\Backups;
use App\Filament\Resources\JuzExams\JuzExamResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\JuzExam;
use App\Models\User;
use App\Support\ArabicNoun;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

/**
 * Delivers admin notifications through the panel.
 *
 * Every notification is stored for the bell at the top of the panel. One
 * that needs attention now (a student just completed a juz) is also shown
 * on screen at once to the admin who is working.
 */
final class FilamentAdminNotifier implements AdminNotifier
{
    public function juzExamPending(JuzExam $exam): void
    {
        $exam->loadMissing('student');

        $this->store(
            $this->examNotification($exam)->actions([$this->examsLink()->markAsRead()]),
        );

        if (Filament::auth()->check()) {
            // Stays on screen until the admin closes it.
            $this->examNotification($exam)
                ->actions([$this->examsLink()])
                ->persistent()
                ->send();
        }
    }

    public function incompleteProfiles(int $count): void
    {
        $this->store(
            Notification::make()
                ->title('ملفات طلاب غير مكتملة')
                ->body(sprintf('عدد الطلاب الذين لم تكتمل ملفاتهم: %d.', $count))
                ->info()
                ->actions([
                    Action::make('students')
                        ->label('عرض الملفات غير المكتملة')
                        ->url(StudentResource::getUrl('index', ['tab' => 'incomplete']))
                        ->markAsRead(),
                ]),
        );
    }

    public function automaticBackupFailed(): void
    {
        $this->store(
            Notification::make()
                ->title('تعذّر أخذ النسخة الاحتياطية التلقائية')
                ->body('لم تُؤخذ نسخة اليوم. افتح صفحة النسخ الاحتياطي وجرّب «نسخة احتياطية الآن» لمعرفة السبب.')
                ->danger()
                ->actions([
                    Action::make('backups')
                        ->label('فتح النسخ الاحتياطي')
                        ->url(Backups::getUrl())
                        ->markAsRead(),
                ]),
        );
    }

    public function driveCopyFailed(string $reason): void
    {
        $this->store(
            Notification::make()
                ->title('تعذّر وضع النسخة المسائية في Google Drive')
                ->body($reason.' سيحاول النظام مرة أخرى بعد نصف ساعة.')
                ->danger()
                ->actions([$this->backupsLink()]),
        );
    }

    public function driveCopiesNotRemoved(array $names, string $folder): void
    {
        $this->store(
            Notification::make()
                ->title('احذف النسخ القديمة من مجلد Google Drive')
                ->body(sprintf(
                    'تعذّر على النظام حذف %s من المجلد %s: %s. احذفها بيدك.',
                    ArabicNoun::backupCopy()->count(count($names)),
                    $folder,
                    implode('، ', $names),
                ))
                ->warning()
                ->actions([$this->backupsLink()]),
        );
    }

    private function backupsLink(): Action
    {
        return Action::make('backups')
            ->label('فتح النسخ الاحتياطي')
            ->url(Backups::getUrl())
            ->markAsRead();
    }

    private function examNotification(JuzExam $exam): Notification
    {
        return Notification::make()
            ->title('سبر مطلوب')
            ->body(sprintf('أتمّ الطالب %s الجزء %d ويحتاج سبراً.', $exam->student->full_name, $exam->juz_no))
            ->warning();
    }

    private function examsLink(): Action
    {
        return Action::make('exams')
            ->label('فتح صفحة السبر')
            ->url(JuzExamResource::getUrl());
    }

    /**
     * Keeps the notification in the bell of every admin account.
     */
    private function store(Notification $notification): void
    {
        $admins = User::query()->get();

        if ($admins->isNotEmpty()) {
            $notification->sendToDatabase($admins);
        }
    }
}
