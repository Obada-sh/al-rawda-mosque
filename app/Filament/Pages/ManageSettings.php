<?php

namespace App\Filament\Pages;

use App\Actions\Export\DownloadLeadersTemplate;
use App\Actions\Export\RestoreDefaultLeadersTemplate;
use App\Actions\Export\SaveLeadersTemplate;
use App\Actions\Settings\SaveSettings;
use App\Contracts\SettingsStore;
use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Attempt;
use App\Filament\Support\Download;
use App\Filament\Support\Fields\FileToCheck;
use App\Http\Requests\Export\SlideTemplateRequest;
use App\Services\Export\LeadersTemplates;
use App\Services\Export\Pptx\SlideDeckTemplate;
use App\Support\ArabicNoun;
use App\Support\SettingKey;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The general settings of the system.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Settings;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'الإعدادات العامة';

    protected static ?string $title = 'الإعدادات العامة';

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(SettingsStore $settings): void
    {
        $this->form->fill([
            'mosque_name' => $settings->get(SettingKey::MOSQUE_NAME),
            'incomplete_profiles_reminder_days' => $settings->get(
                SettingKey::INCOMPLETE_PROFILES_REMINDER_DAYS,
                (string) SettingKey::DEFAULT_REMINDER_DAYS,
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('الجامع')
                    ->schema([
                        TextInput::make('mosque_name')
                            ->label('اسم الجامع')
                            ->helperText('يظهر أعلى النظام وعلى بطاقات الحضور.'),
                    ]),
                Section::make('التذكيرات')
                    ->schema([
                        TextInput::make('incomplete_profiles_reminder_days')
                            ->label('التذكير بملفات الطلاب غير المكتملة كل')
                            ->helperText('يصلك إشعار في جرس الإشعارات ما دام هناك طلاب ملفاتهم غير مكتملة.')
                            ->inputMode('numeric')
                            ->suffix('يوم')
                            ->markAsRequired(),
                    ]),
                Section::make('شرائح أوائل الحلقات')
                    ->description('التصميم الذي تُكتب عليه أسماء طلاب الحلقات عند تصدير ملف PowerPoint من صفحتي الإحصائيات والفارس القرآني.')
                    ->schema([
                        Text::make(fn (LeadersTemplates $templates): string => self::templateInUse($templates))
                            ->weight('bold'),
                        Text::make('لتصميم شرائحك الخاصة: نزّل التصميم الافتراضي من زر «قالب شرائح الأوائل» أعلى الصفحة، وغيّر في PowerPoint ما تشاء من الألوان والخطوط والخلفية، ثم ارفعه من الزر نفسه.'),
                        Text::make(self::placeholdersGuide()),
                        Text::make(self::boardGuide()),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('حفظ الإعدادات')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    /**
     * The slide template has its own buttons: a file is uploaded, checked
     * and put to use at once, apart from the rest of the settings.
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('uploadLeadersTemplate')
                    ->label('رفع قالب خاص')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->modalHeading('رفع قالب لشرائح الأوائل')
                    ->modalDescription('ملف PowerPoint (‎.pptx) فيه شريحة واحدة تحمل الخانات. يحلّ محل التصميم المستعمل الآن.')
                    ->modalSubmitActionLabel('استعمال هذا القالب')
                    ->schema([
                        FileToCheck::make('template', SlideTemplateRequest::MAX_KILOBYTES)
                            ->label('ملف القالب'),
                    ])
                    ->action(function (array $data, Action $action, SaveLeadersTemplate $saveTemplate): void {
                        $template = Attempt::action($action, fn (): SlideDeckTemplate => $saveTemplate->handle($data));

                        Notification::make()
                            ->title('تم اعتماد قالبك')
                            ->body(self::capacityOf($template))
                            ->success()
                            ->send();
                    }),
                Action::make('downloadDefaultLeadersTemplate')
                    ->label('تنزيل التصميم الافتراضي')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (DownloadLeadersTemplate $download): StreamedResponse => Download::of($download->default())),
                Action::make('downloadCustomLeadersTemplate')
                    ->label('تنزيل قالبي الحالي')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn (LeadersTemplates $templates): bool => $templates->hasCustom())
                    ->action(fn (DownloadLeadersTemplate $download): ?StreamedResponse => Attempt::button(
                        fn (): StreamedResponse => Download::of($download->custom()),
                    )),
                Action::make('restoreDefaultLeadersTemplate')
                    ->label('العودة إلى التصميم الافتراضي')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (LeadersTemplates $templates): bool => $templates->hasCustom())
                    ->requiresConfirmation()
                    ->modalHeading('العودة إلى التصميم الافتراضي؟')
                    ->modalDescription('يُزال قالبك الخاص من النظام. نزّله أولاً إن أردت الاحتفاظ بنسخة منه.')
                    ->modalSubmitActionLabel('نعم، استعمل التصميم الافتراضي')
                    ->action(fn (RestoreDefaultLeadersTemplate $restoreDefault) => $restoreDefault->handle())
                    ->successNotificationTitle('عاد التصميم الافتراضي'),
            ])
                ->label('قالب شرائح الأوائل')
                ->icon(Heroicon::OutlinedPresentationChartBar)
                ->color('gray')
                ->button(),
        ];
    }

    public function save(SaveSettings $saveSettings): void
    {
        $data = $this->form->getState();

        Attempt::form(fn () => $saveSettings->handle($data), $this->form);

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->success()
            ->send();
    }

    /**
     * The placeholders are Latin words inside an Arabic sentence: each is
     * kept left-to-right so its braces stay around it.
     */
    private static function placeholdersGuide(): HtmlString
    {
        return new HtmlString(
            'اترك الخانات كما هي، فالنظام يكتب مكان '.self::code('title').' عنوان الشريحة (مثل «نقاط حلقة الفرقان»)، ومكان '
            .self::code('name').' اسم الطالب، ومكان '.self::code('value').' رقمه (صفحاته أو نقاطه أو أيام حضوره أو أجزاء سبره). '
            .'وتتوفر أيضاً '.self::code('score').' (الرقم مع وحدته) و'.self::code('place').' (المرتبة) و'.self::code('circle')
            .' و'.self::code('supervisor_line').' و'.self::code('course').' و'.self::code('period').' و'.self::code('mosque').'.',
        );
    }

    private static function boardGuide(): HtmlString
    {
        return new HtmlString(
            'خانة الطالب ('.self::code('name').' و'.self::code('value').' وما يحيط بهما) تُرسم مرة واحدة، فيكرّرها النظام لكل طالب ويكبّرها أو يصغّرها لتملأ المساحة. '
            .'المساحة هي الشكل المسمّى <code dir="ltr">cells</code> (لا يظهر في الشرائح)، ويُضَمّ إلى الخانة كل شكل يبدأ اسمه بـ <code dir="ltr">cell</code>. '
            .'والقوالب القديمة ذات الأماكن المرقّمة ('.self::code('name1').' و'.self::code('name2').'...) ما زالت تعمل: يملؤها الطلاب بالترتيب وتُضاف شرائح للبقية.',
        );
    }

    private static function code(string $placeholder): string
    {
        return '<code dir="ltr">{{'.$placeholder.'}}</code>';
    }

    private static function templateInUse(LeadersTemplates $templates): string
    {
        if (! $templates->hasCustom()) {
            return 'المستعمل الآن: التصميم الافتراضي.';
        }

        $template = $templates->custom();

        return $template === null
            ? 'المستعمل الآن: قالبك الخاص، لكن ملفه لم يعد يُقرأ. ارفعه من جديد أو عد إلى التصميم الافتراضي.'
            : 'المستعمل الآن: قالبك الخاص. '.self::capacityOf($template);
    }

    /**
     * How many students one slide of a template shows.
     */
    private static function capacityOf(SlideDeckTemplate $template): string
    {
        $students = ArabicNoun::student()->count($template->itemsPerSlide());

        return $template->isBoard()
            ? "يرتّب طلاب الحلقة في الشريحة بحجم يناسب عددهم، حتى {$students} في الشريحة الواحدة."
            : "أماكنه المرقّمة تتسع لـ {$students} في الشريحة، ويُكمَل بقية الطلاب في شرائح تالية.";
    }
}
