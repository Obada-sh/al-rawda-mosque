<?php

namespace App\Filament\Support\Presenters;

use App\Services\Backup\DriveCopySettings;
use App\Services\Backup\DriveCopyState;
use App\Support\ArabicNoun;
use App\Support\Dates;
use Illuminate\Support\HtmlString;

/**
 * How the evening copy for Google Drive is shown on the backups page:
 * what it does, how the last attempts went, and in which colour.
 */
final class DriveCopyPresenter
{
    public const EXAMPLE_FOLDER = 'G:\My Drive\نسخ الروضة';

    /**
     * Paths are kept left-to-right inside the Arabic sentence, so their parts stay in order.
     */
    public static function summary(DriveCopySettings $settings, DriveCopyState $state, bool $demo): HtmlString
    {
        if ($demo) {
            return new HtmlString('النسخة التجريبية لا تضع نسخاً في Google Drive: بياناتها مخترعة.');
        }

        if (! $settings->isOn()) {
            return new HtmlString(
                'غير مفعّلة. ثبّت على هذا الحاسوب برنامج Google Drive (Google Drive for desktop) وسجّل الدخول فيه بحساب Google، '
                .'وأنشئ في Drive مجلداً للنسخ، ثم اضغط «إعداد Google Drive» واكتب مكانه، مثل '
                .self::path(self::EXAMPLE_FOLDER)
                .'. عندها يضع النظام كل مساء نسخة في المجلد، فيرفعها البرنامج إلى Drive.',
            );
        }

        $lines = [
            'كل يوم بعد الساعة '.self::path($settings->time).' يأخذ النظام نسخة ويضعها في المجلد '
            .self::path((string) $settings->folder)
            .'، فيرفعها برنامج Google Drive متى اتصل الحاسوب بالإنترنت. إن كان الحاسوب مطفأً في ذلك الوقت وُضعت عند أول استعمال بعده. '
            .'مدة الاحتفاظ بالنسخ: '.e(ArabicNoun::day()->count($settings->keepDays)).'، ثم تُحذف من المجلد ومن هذا الحاسوب.',
            $state->copiedAt === null
                ? 'لم توضع أي نسخة بعد: توضع الأولى خلال دقائق، أو اضغط «ضع نسخة في Google Drive الآن».'
                : 'آخر نسخة وُضعت في '.self::path($state->copiedAt->format(Dates::DISPLAY_FORMAT_WITH_TIME)).'.',
        ];

        if ($state->hasFailed()) {
            $lines[] = '<strong>آخر محاولة لم تنجح ('.self::path($state->failedAt->format(Dates::DISPLAY_FORMAT_WITH_TIME)).'):</strong> '.e((string) $state->failure);
        }

        return new HtmlString(implode('<br>', $lines));
    }

    /**
     * @return 'info'|'success'|'warning'|'danger'
     */
    public static function status(DriveCopySettings $settings, DriveCopyState $state, bool $demo): string
    {
        return match (true) {
            $demo, ! $settings->isOn() => 'info',
            $state->hasFailed() => 'danger',
            $state->copiedAt === null => 'warning',
            default => 'success',
        };
    }

    private static function path(string $text): string
    {
        return '<bdi dir="ltr">'.e($text).'</bdi>';
    }
}
