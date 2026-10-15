<?php

namespace App\Filament\Pages\Auth;

use App\Actions\Auth\UpdateAdminProfile;
use App\Filament\Support\Attempt;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use SensitiveParameter;

/**
 * The admin's own account: name, username and password.
 */
class EditProfile extends BaseEditProfile
{
    /**
     * Form field => field of AdminProfileRequest.
     */
    private const REQUEST_FIELDS = [
        'new_password' => 'password',
        'new_password_confirmation' => 'password_confirmation',
    ];

    public static function getLabel(): string
    {
        return 'حسابي';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الحساب')
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->markAsRequired()
                            ->autofocus(),
                        TextInput::make('username')
                            ->label('اسم المستخدم')
                            ->markAsRequired()
                            ->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                Section::make('تغيير كلمة المرور')
                    ->description('اترك هذه الحقول فارغة إذا كنت لا تريد تغيير كلمة المرور.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('كلمة المرور الحالية')
                            ->password()
                            ->revealable()
                            ->autocomplete('current-password')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('new_password')
                            ->label('كلمة المرور الجديدة')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->helperText('ثمانية أحرف على الأقل.')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('new_password_confirmation')
                            ->label('تأكيد كلمة المرور الجديدة')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->extraInputAttributes(['dir' => 'ltr']),
                    ]),
            ]);
    }

    /**
     * Only the name and the username are sent to the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return Arr::only($data, ['name', 'username']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $input = [];

        foreach ($data as $field => $value) {
            $input[self::REQUEST_FIELDS[$field] ?? $field] = $value;
        }

        return Attempt::form(
            fn (): User => app(UpdateAdminProfile::class)->handle($record, $input),
            $this->form,
            fn (string $field): string => array_search($field, self::REQUEST_FIELDS, true) ?: $field,
        );
    }

    protected function afterSave(): void
    {
        foreach (['current_password', 'new_password', 'new_password_confirmation'] as $field) {
            $this->data[$field] = null;
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ بيانات الحساب';
    }
}
