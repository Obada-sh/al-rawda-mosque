# Developing

How to set up a development computer, run the system and its tests, and add to it without breaking the conventions described in [architecture.md](architecture.md).

## What you need

- Windows 10 or 11 (the start scripts are batch files; the PHP code itself runs anywhere)
- PHP 8.4 with the extensions `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`. [Laravel Herd](https://herd.laravel.com) provides all of it
- Composer 2
- MySQL Server 8, running as a service
- Git for Windows

`deploy\check.php` is the list the system itself checks before it starts.

## First setup from a clone

```bat
git clone <the repository> rawda
cd rawda
composer install
copy .env.example .env
php artisan key:generate
```

Then open `.env` and set `DB_PASSWORD` (and `DB_USERNAME` if it is not `root`). Leave `DB_DATABASE=rawda`.

```bat
start.bat
```

`start.bat` creates the database if it does not exist, creates its tables, fills the two starting lists (tajweed levels, departments), and opens `http://127.0.0.1:8000`. The first page asks you to create the admin account.

`composer install` also publishes Filament's scripts and styles into `public` (the `post-autoload-dump` scripts in `composer.json`). Those folders are in `.gitignore`: if the panel ever appears without its styles, run `php artisan filament:upgrade`.

## Running

| Command | What runs | Database |
|---|---|---|
| `start.bat` | the real system at `http://127.0.0.1:8000`, as `production` with debug off | `DB_DATABASE` of `.env` |
| `demo.bat` | the demo at `http://127.0.0.1:8010` | `rawda_demo`, filled with invented data |

Both can run at once. Each keeps a black window open; closing it stops that server. The server's own output is in `storage\logs\server-<port>.log`, the application's errors in `storage\logs\laravel.log`.

While developing you may prefer errors on screen. Run it by hand:

```bat
php artisan serve
```

That serves the database of `.env` in the `local` environment with debug on. It is fine by hand; it must never be used in `start.bat` or `demo.bat` (see "php -S" in [architecture.md](architecture.md)).

Useful commands:

| Command | Does |
|---|---|
| `php artisan rawda:prepare` | what `start.bat` does before serving: wait for MySQL, create/migrate the database, daily backup |
| `php artisan rawda:admin` | create the admin, or reset a forgotten password |
| `php artisan rawda:backup` | take a backup now (`--daily` for the automatic one) |
| `php artisan rawda:demo --fresh` | rebuild the demo data. Only works on a `_demo` database, so run it as `demo.bat` does or not at all |
| `php artisan rawda:prepare-testing-db` | create the database of the tests |

## Tests

```bat
php artisan rawda:prepare-testing-db
php vendor\phpunit\phpunit\phpunit
```

The first line is needed once: it creates the MySQL database `rawda_testing`. The whole suite takes three to four minutes (`phpunit.xml` gives it 512 MB of memory: PHP's 128 MB for the command line is not enough for it). One file or one test:

```bat
php vendor\phpunit\phpunit\phpunit tests\Feature\TransferTest.php
php vendor\phpunit\phpunit\phpunit --filter test_a_student_is_enrolled_in_the_circle_the_admin_picked
```

What to know about them:

- **They run on real MySQL** (`phpunit.xml` sets `DB_DATABASE=rawda_testing`), with the user and password of `.env`. `Tests\TestCase` refuses any database whose name does not end with `_testing`, redirects backups to `storage/framework/testing/backups`, turns the automatic backup off and fakes the disk of uploaded files. A test cannot touch real data.
- `tests/Unit` needs no database: `Money`, `Ranking`, `QuranPages`, `ArabicNoun`, the slide template and the layout of its board, the backup file format. `tests/Fixtures/slides` holds designs with numbered places, the kind of slide the admin's older designs have.
- Tests that need a picture take it from `Tests\Support\Pictures`, which holds real PNG bytes: `UploadedFile::fake()->image()` needs PHP's image extension, and the tests must not.
- `tests/Feature` tests the **actions**: one file per area (`EnrollmentTest`, `RecitationTest`, `JuzExamTest`, `BackupTest`, ...).
- `tests/Feature/Panel` drives the **screens** through Livewire as the signed-in admin, on a fixed date (`PanelTestCase` travels to Wednesday 2026-10-07 17:00). These tests prove that a screen really goes through its action and shows errors where it should.
- `tests/Feature/LauncherTest.php` reads the batch files and checks what they promise.

Conventions used in the tests:

- A test name is a sentence about the system: `test_everything_the_student_did_follows_them_to_the_new_circle`.
- Data is built **through the real actions**, with the helpers of `Tests\Concerns\BuildsDomain`: `$this->enroll()`, `recite()`, `attend()`, `award()`, `holdActivity()`, `moveToCircle()`, `spend()`. Factories (`database/factories`) only create the simple rows (a student, a course, a circle, a teacher).
- A broken rule is asserted by its key: `$this->assertViolates('enrollment.duplicate', fn () => $this->enroll($student, $course));`
- Bad input is asserted with `$this->expectException(ValidationException::class);`
- Notifications to the admin are asserted on `$this->adminNotifier` (a `RecordingAdminNotifier` bound in place of the panel's notifier).
- The base `TestCase` turns off what pages would do behind a test's back (`rawda.backups.automatic`, `rawda.backups.drive`); a test that wants it turns it on. The "Google Drive folder" of `DriveCopyTest` and `DriveCopyPanelTest` is a fresh folder of the system's temporary files: the system refuses a Drive folder inside the project.

**Run the whole suite before every push.** The mosque laptop pulls what you push.

Laravel Pint is installed as a development dependency (`vendor\bin\pint --test` lists what it would reformat). It is not yet part of the workflow.

## Adding a feature

The order below is the order in which the layers depend on each other. As an example, take "record that a student arrived late".

1. **Database.** A new migration in `database/migrations` (`php artisan make:migration add_late_minutes_to_attendances`). Use real foreign keys and `RESTRICT` unless the child is meaningless without its parent. Never edit an existing migration.
2. **Model.** Add the column to `$fillable` and `casts()`. A missing `$fillable` entry throws in this project instead of being ignored.
3. **Request.** Add the field to the request class of the use case (`ManualAttendanceRequest`), or create `app/Http/Requests/<Area>/<UseCase>Request.php` extending `BaseRequest`, with `rules()`, Arabic `messages()` and `attributes()`. List number fields in `$digitFields`.
4. **Action.** Change the action, or create `app/Actions/<Area>/<Verb><Thing>.php`: a `final class` with `handle(array $input)`, which calls `XxxRequest::validateData($input)` first, throws `BusinessRuleViolation::because('area.rule', 'الرسالة')` for rules that depend on state, and writes in a transaction. Document the rule keys in `@throws`.
5. **Test the action** in `tests/Feature`: the normal case, each rule key, each invalid input.
6. **Screen.** Add the field to the form class (`Schemas/…Form.php`) or the column to the table class (`Tables/…Table.php`). Call the action through `Attempt`. Give every field an Arabic `->label()`, also when the label is hidden: some layouts show it on narrow screens.
7. **Test the screen** in `tests/Feature/Panel`: that saving works, and that a validation message lands under its field.
8. **Reports**, if the feature should count somewhere: start from `Services\Statistics\StudentStandings`.
9. **Demo data**, if the feature should be visible in the demo: `database/seeders/DemoSeeder.php`.

Smaller recipes:

| To add | Touch |
|---|---|
| a setting of the mosque | `Support\SettingKey`, `Http\Requests\Settings\SettingsRequest`, `Actions\Settings\SaveSettings`, `Filament\Pages\ManageSettings` |
| a setting of the computer | `config/rawda.php`, read with `config('rawda.…')`, overridable from `.env` |
| a value with a fixed list of choices | an enum in `app/Enums` with `label()` and the trait `HasOptions`; cast it in the model |
| a drop-down list of records | `Filament\Support\Options` |
| a screen in the side menu | a Resource or a Page; its group is a case of `Filament\Enums\PanelGroup` |
| a notification to the admin | a method on `Contracts\AdminNotifier`, implemented in `FilamentAdminNotifier` and in `tests/Support/RecordingAdminNotifier` |
| a placeholder on the slides | `Services\Export\LeadersSlides` (and its doc comment, and the Arabic README) |
| a console command | `app/Console/Commands`; it is discovered automatically |
| a file the system keeps (a picture, a template) | a service that owns one folder of the private disk (see `Services\Students\StudentPhotos`); that folder added to `Services\Backup\KeptFiles` so backups carry it; `Http\Requests\Rules\Picture` if it is a picture; a route in `AdminPanelProvider::authenticatedRoutes()` if a browser must show it |
| something printed on the attendance card | a field of `Actions\Cards\AttendanceCard`, filled by `PrepareAttendanceCards` and `PrepareTeacherCards`, drawn at its place of the PSD in `resources/views/cards/badge.blade.php` (the fixed part is `public/img/card/background.png`) |
| the mosque's logo | replace `public/img/logo.png` (and `favicon.png`), and the picture of the logo on the slide of `resources/pptx/leaders.pptx` in PowerPoint; the card's copy is inside `public/img/card/background.png` |

A new table needs nothing for backups: `DatabaseDumper` writes every table it finds.

## Text, numbers and dates

- The interface is Arabic and right-to-left; the code, comments, commit messages and these docs are English.
- **Messages** for the admin are written where the rule is: in the request class, or in the `BusinessRuleViolation`. **Labels** of enum values are in the enum (`label()`).
- Counted nouns go through `Support\ArabicNoun` (صفحة واحدة، صفحتان، 3 صفحات، 11 صفحة). Do not concatenate a number and a noun by hand.
- Digits are always `0-9`, in tables, inputs and on printed cards. Input typed with Arabic digits is converted by `BaseRequest`.
- Dates are stored as `Y-m-d` and shown in numbers only: `d/m/Y` for a day, `m/Y` for a month (`Dates::show()`, `Dates::showWithWeekday()`, `Dates::showMonth()`). No month is ever named, not even in the date picker, whose list of months `PanelDefaultsProvider` numbers 1 to 12. The time zone is `Asia/Damascus`.
- "Today" matters: attendance by scan is recorded for the current date of the computer.

## Filament notes worth knowing

Things that cost time once and are easy to forget:

- **Render hooks are resolved through the container**: `->renderHook(…, fn (DemoNotice $notice): ?View => $notice->badge())` works, and returning `null` renders nothing.
- **Button colours.** Filament picks white text on a coloured button only if shades 500 and 600 of the palette are dark enough. The stock emerald gave mint buttons with dark text, so `Filament\Support\Theme::PRIMARY` is a palette shifted one step darker.
- **`Select` and date pickers are non-native everywhere** (`PanelDefaultsProvider`), so the browser's language cannot change how a date is shown.
- **A column hidden by the user's column toggle is still searched**, and the toggle state persists in the session.
- **A table repeater shows the field labels in its stacked layout on narrow screens.** That is why hidden labels still need Arabic text.
- **Stat cards use container queries**; `public/css/panel.css` makes the value shrink with the card instead of overflowing.
- Uploads the action stores itself use `Filament\Support\Fields\FileToCheck` (`storeFiles(false)`), and the size limit shown is the smaller of the system's and PHP's (`Support\UploadLimit`).

## Upgrading the libraries

```bat
composer update
php vendor\phpunit\phpunit\phpunit
```

Then try the screens you care about in `demo.bat`, and commit `composer.json` and `composer.lock` together. The laptop installs exactly what `composer.lock` names the next time `update.bat` runs there. A major version of Filament or Laravel is a project of its own: read their upgrade guides first.

## Git habits for this project

- `main` is what the mosque laptop follows. Push to it only what passed the tests.
- Work on a branch for anything that takes more than one sitting, and merge when it is done.
- Never commit `.env`, anything from `backups/`, or a database dump. `.gitignore` already keeps them out; do not force them in.
- The batch files must keep Windows line endings and contain ASCII only. `.gitattributes` and `LauncherTest` take care of the first; your editor must not "fix" them.
- A change of the database is a new migration, always.

How a push reaches the laptop is in [deployment.md](deployment.md).
