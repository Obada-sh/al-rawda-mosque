# Architecture

The system is a Laravel application whose whole interface is one Filament panel. What makes it more than a default Filament project is a strict split between **what the admin sees** (Filament) and **what the system does** (requests, actions, services). This page explains that split, the conventions that keep it, and the decisions that would look odd without their reason.

## The layers

```
app/Filament         screens: forms, tables, buttons, colours, labels
        │  calls, through Attempt
        ▼
app/Actions          one class per use case: validate, check the rules, write
        │  uses
        ▼
app/Http/Requests    the shape of the input (ValidationException)
app/Services         calculations and reads shared by actions and screens
app/Models           rows and their relations
```

Dependencies only point downwards. Nothing under `app/Actions`, `app/Services`, `app/Models` or `app/Http/Requests` knows that Filament exists, with one deliberate exception: `App\Services\Notifications\FilamentAdminNotifier`, which is the Filament implementation of the `AdminNotifier` contract and is bound in `AppServiceProvider`.

### Requests: every validation rule

`app/Http/Requests/**` holds one class per use case (`EnrollmentRequest`, `TransferRequest`, `RecitationRequest`, ...). They all extend `App\Http\Requests\BaseRequest`, which is an ordinary Laravel `FormRequest` with one addition: it can validate **outside HTTP**.

```php
$data = EnrollmentRequest::validateData($input);              // returns only the validated fields
$data = CircleRequest::validateData($input, ['course_id' => 5, 'ignore_id' => $circle->id]);
```

The panel has no controllers, so that is how the rules run: every action calls `validateData()` first. The few real controllers (`SetupController`, `AttendanceCardController`, `BackupDownloadController`) use the same classes.

What `BaseRequest` does before the rules run (`normalize()`):

- trims strings and turns empty strings into `null`;
- leaves passwords exactly as typed (`$verbatimFields`);
- converts Arabic-Indic and Persian digits to `0-9` in the fields a class lists in `$digitFields`, so a number typed on an Arabic keyboard is a number.

Rules that depend on what is being edited ("unique, except this record") read it from the **context** array, the second argument of `validateData()`. Each class documents the context keys it reads in its doc comment.

Every request class also carries its Arabic `messages()` and `attributes()`: the wording of an error is decided next to its rule, never in a screen.

### Actions: every write

`app/Actions/<Area>/<Verb><Thing>.php`, for example `Enrollments/EnrollStudent`, `Recitations/RecordRecitation`, `Backups/RestoreBackup`. An action:

1. is a `final class` with one public method, `handle()`, and gets what it needs through its constructor;
2. takes the **raw input array** and validates it itself, so no caller can skip validation;
3. checks the rules that need the current state of the system and throws `BusinessRuleViolation` when one is broken;
4. writes inside `DB::transaction()` when more than one row is involved, and locks the rows a concurrent request could race for (`lockForUpdate()`, `createOrFirst()`);
5. returns the model (or a small result object) the caller needs.

`app/Actions/Enrollments/EnrollStudent.php` is a good one to read first: it is short and shows all five points.

Two kinds of failure, and they are never mixed:

| Exception | Raised by | Means | The screen shows |
|---|---|---|---|
| `ValidationException` | a Request class | the input itself is wrong | the message under the field |
| `App\Exceptions\BusinessRuleViolation` | an action or a service | valid input would break a rule ("already enrolled in this course") | a red notification |

`BusinessRuleViolation::because('enrollment.duplicate', 'الطالب مسجَّل مسبقاً في هذه الدورة.')` carries a **stable key** (`$violation->rule`) and the **Arabic message**. Tests assert on the key, the admin reads the message. Each action lists the keys it can throw in the `@throws` line of `handle()`.

The scan actions deliberately do not throw for expected situations: `Attendance\ScanCard` (the scan screen's one entry, which hands a teacher's card to `RecordTeacherScan` and a student's to `RecordScanAttendance`, for today or for the earlier day the desk is set to) returns a `ScanResult` with a `ScanStatus` (recorded, already recorded, unknown card, not a class day, ...). The scan screen must always have something to show in a colour, including for a card it does not know. In the same spirit `RecordBulkAttendance` records many students at once and returns a `BulkAttendanceResult` (who was recorded, who already was, who was refused and why) instead of stopping at the first refusal.

### Services: shared reads and calculations

`app/Services/<Area>` holds what more than one action or screen needs:

| Folder | What it is |
|---|---|
| `Memorization` | `MemorizationLedger` (which pages a student has memorized), `JuzExamSynchronizer`, `JuzMap` |
| `Points` | `PointsLedger`, the only writer of `point_transactions` (attendance, recitation, activity and manual points) |
| `Attendance` | `SessionResolver`: finds or opens the class day an attendance belongs to |
| `Enrollments` | `EnrollmentCalendar`: the dates on which something may be recorded for an enrollment |
| `Statistics` | the reports. `StudentStandings` computes one row per student in a course; every other report is built from those rows |
| `Finance` | `ExpenseReport` and its result objects |
| `Market` | `PointsBalance` (what a student can still spend in a course) and `MarketItemPictures` |
| `Export` | the PowerPoint slides: `LeadersSlides`, `LeadersTemplates`, and `Pptx/*`, a small editor of `.pptx` files (filling placeholders, numbered slots, and boards: one cell repeated and laid out to fit, `SlideBoard`, `BoardLayout`, `TextFitter`) |
| `Backup` | writing, checking and restoring backup files, without `mysqldump` |
| `Cards` | QR drawing and card code generation (implementations of two contracts); `CardDesign`, the measures of the printed card |
| `Teachers` | `TeacherCircles` (the circles a teacher supervises or assists in a course, the teachers of a course), `TeacherCards` (a teacher's card in a course), `TeacherPhotos` |
| `Files` | `KeptPictures`: a folder of the private disk for one kind of picture; `StudentPhotos`, `TeacherPhotos`, `Circles\CircleLogos` and `Market\MarketItemPictures` are its folders |
| `Demo` | `DemoMode` and `DemoAccount` |
| `Settings`, `Notifications`, `Students` | the settings store, the admin notifier, `ProfileCompleteness`, `StudentPhotos` |

Read services return **readonly result objects** (`StudentStanding`, `CircleStanding`, `ExpenseTotals`, `CircleLeaderboard`, ...), not Eloquent models and not arrays. A report row is a computed figure; giving it a class means the screens, the slides and the tests all read the same named fields.

### Models

Models hold relations, casts, scopes and questions about **one row** (`$course->isActive()`, `$enrollment->hasLeft()`, `$student->isArchived()`). They do not hold use cases. Two settings in `AppServiceProvider::boot()` make mistakes loud instead of silent:

```php
Model::preventSilentlyDiscardingAttributes();   // filling a field that is not fillable throws
Model::preventAccessingMissingAttributes();     // reading a column that was not selected throws
```

### Contracts

`app/Contracts` has four interfaces, bound in `AppServiceProvider`:

| Contract | Implementation | Why it is a contract |
|---|---|---|
| `AdminNotifier` | `FilamentAdminNotifier` | business rules notify the admin without knowing the panel; tests bind a recorder |
| `CardCodeGenerator` | `NumericCardCodeGenerator` | codes must be digits only (the scanner types through the keyboard layout) |
| `QrCodeRenderer` | `BaconQrCodeRenderer` | the drawing library can be replaced |
| `SettingsStore` | `DatabaseSettingsStore` | the `settings` table, read once per request |

Interfaces exist only where there is a second implementation (the tests' recorder) or a library to keep at arm's length. There is no repository layer: Eloquent is used directly.

## The panel (`app/Filament`)

```
Filament/
  Resources/<Plural>/
      <Name>Resource.php          navigation, model, which pages
      Pages/                      List/Create/Edit/View, or one Manage page for simple lists
      Schemas/<Name>Form.php      the fields of the form
      Tables/<Plural>Table.php    the columns, filters and row buttons
      RelationManagers/           tables shown inside another record (a course's circles, ...)
  Pages/                          screens that are not a table of one model:
                                  ScanAttendance, Backups, ManageSettings, Reports/*, Auth/*
  Widgets/                        the parts of the home page (MosqueBanner, OverviewStats) and of the
                                  report pages (Reports/Letterhead heads every report)
  Support/                        everything screens share (see below)
  Enums/PanelGroup.php            the sections of the side menu, in order
```

### `Attempt`: the single doorway

A screen never calls an action directly. It wraps the call in `App\Filament\Support\Attempt`, which turns the two exceptions into what that kind of screen should do:

| Method | For | On `ValidationException` | On `BusinessRuleViolation` |
|---|---|---|---|
| `Attempt::form($callback, $form)` | the form of a page | messages under the fields | red notification, form stays open |
| `Attempt::action($action, $callback)` | a button that opens a form in a window | messages under the fields of the window | red notification, window stays open |
| `Attempt::confirmed($callback)` | a button that only asks "are you sure?" | – | red notification, confirmation closes |
| `Attempt::button($callback)` | a button with neither | – | red notification, returns `null` |

Two examples from the code:

```php
// Resources/Students/Pages/CreateStudent.php: a page form
protected function handleRecordCreation(array $data): Model
{
    return Attempt::form(
        fn (): Student => app(SaveStudent::class)->handle($data),
        $this->form,
    );
}

// Resources/Recitations/Pages/ManageRecitations.php: a button with a window
CreateAction::make()
    ->using(fn (array $data, Action $action, RecordRecitation $recordRecitation): Recitation => Attempt::action(
        $action,
        fn (): Recitation => $recordRecitation->handle($data),
    ))
```

When a form field is not named like the request field, pass the third argument `$fieldOf` to map one to the other.

Filament forms in this project therefore declare **no validation rules** (`->required()` and friends). Where a field should *look* required, it uses `->markAsRequired()`. The star is decoration; the rule is in the request class.

### What screens share (`Filament/Support`)

| Class | Purpose |
|---|---|
| `Options` | the choices of drop-down lists: what is active today, plus the value a record already holds |
| `Fields\StudentSelect`, `Fields\EnrollmentSelect` | searchable pickers (there can be hundreds of students) |
| `Fields\FileToCheck` | an upload the action checks and stores itself; says the size limit of this computer |
| `Fields\PhotoUpload` | the photo of a student or a teacher, cut by the admin to the circle of the card |
| `Presenters\CardPresenter` | the address of the card design's picture; how much the card is enlarged on the design page |
| `Filters\CourseFilter`, `Filters\DateRangeFilter` | table filters used on several screens |
| `Presenters\*` | how values are written and coloured (`ReportPresenter`, `ExamPresenter`, ...) |
| `Reports\ReportFilterFields`, `Reports\ReportRows` | the filter of the report pages; sorting, searching and paging of computed rows |
| `Enrollments\EnrollmentActions`, `Tables\TransfersLogTable`, `Export\LeadersSlidesAction`, `Attendance\BulkAttendanceAction`, `Documents\DocumentActions` | buttons and tables that appear on more than one screen |
| `Download` | sends an `ExportedFile` made by an action to the browser |
| `Theme`, `DemoNotice` | the palette and font; the marks that say "this is the demo" |

`App\Providers\Filament\PanelDefaultsProvider` sets what every screen would otherwise repeat: dates as `d/m/Y` (and the months of the date picker numbered 1 to 12), digits `0-9`, Saturday as the first day of the week, non-native date pickers and selects, page sizes.

`Documents\DocumentActions` and `Cards\CardPrintActions` are the buttons that open the print pages (in a new window, through `NewWindow` when a choice is made first).

### Report pages

`Pages/Reports/Statistics`, `KnightRanking` and `FinanceReport` extend `FilteredDashboard`: a filter form at the top, widgets below. The widgets read the filter through the trait `ReadsReportFilter` (or `ReadsExpenseFilter`), which builds a `ReportFilter` from the page's filter state. An invalid choice gives `null`: the page shows the message under the field and the widgets show nothing until it is corrected. Filters are checked by `ReportFilterRequest` / `ExpenseFilterRequest`, like any other input.

`ReportFilter` (course, circle, period) is the one definition of "which students and which days". Every report and the slides read it, so the same choice gives the same numbers everywhere.

## Things that are tied to use, not to a clock

The laptop is not always on, so nothing depends on a scheduler or a queue. What other systems do at night happens here when the admin opens a page:

| What | Where | When |
|---|---|---|
| Automatic backup of the day | middleware `TakeDailyBackup` → `GuardDailyBackup` → `RunDailyBackup` | first panel page of the day, after the page was sent; also at every start (`rawda:prepare`) |
| Evening copy for Google Drive | middleware `TakeEveningDriveCopy` → `GuardEveningDriveCopy` → `CopyBackupToDrive` | after a GET panel page or a tick, once a day from the chosen time (a missed evening is caught up at the next use) |
| Reminder about incomplete profiles | middleware `RemindAdmin` → `RemindAboutIncompleteProfiles` | when the panel is opened, every N days (a setting) |
| Notification that a juz exam is waiting | event `JuzExamOpened` → `NotifyAdminOfPendingJuzExam` | when the recitation that completes the juz is saved (after commit) |

A failed automatic backup never stops the admin from working: it is logged and reported once a day in the notifications. The same goes for the evening copy, which is tried again half an hour later.

The one thing tied to a time of day, the evening copy, needs a page to ask: while it is on, every page of the signed-in admin carries a small script (`resources/views/filament/partials/tick.blade.php`, render hook `BODY_END`) that calls `GET /admin/tick` (`TickController`, an empty 204) every five minutes; the middleware does the work after that answer was sent. So the copy happens on time as long as one page of the panel is open, and costs nothing when nothing is due.

## Requests through the application

`bootstrap/app.php` and `AdminPanelProvider` set the order:

1. `RefuseRealDataAtDemoAddress` (global, first): on the demo's port, a system that is not on a demo database answers 503.
2. `RedirectToSetupWhenNoAdmin`: until an admin exists, every panel address leads to `/setup`.
3. Filament's `Authenticate`, then `RemindAdmin`, `TakeDailyBackup` and `TakeEveningDriveCopy`.

Routes outside the panel are in `routes/web.php` (`/` redirects to `/admin`; `/setup`). More routes are registered **inside** the panel by `AdminPanelProvider::authenticatedRoutes()`, so only the signed-in admin reaches them: the printable cards of the students and of the teachers, the download of a backup, the pictures (students' and teachers' photos, circles' logos, market items' pictures; files deliberately not in the public folder), the documents printed on A4 under `/admin/print/`: a student's file (`StudentFileController`), the files of many students (`StudentFilesController`), a circle's list of students or its attendance codes (`CircleSheetController`), and the list of the mosque's students (`MosqueSheetController`); and the tick of open pages (`/admin/tick`).

**Printed documents.** Each is prepared by an action that returns readonly objects (`Actions\Documents\PrepareStudentFile` → `StudentFile`, `PrepareStudentFiles` → `StudentFiles`, both through `StudentFileBuilder`; `PrepareCircleSheets` → `PrintedCircleSheets`: the `SheetOptions` asked for and a `CircleSheet` per circle; `PrepareMosqueSheet` → `MosqueSheet`; both lists build their records with `SheetRecords` and print `SheetLine`s through `documents.partials.students-table`), and drawn by a view of `resources/views/documents` on the shared `documents.layout` (the toolbar with back, logo and print) and `documents.partials.letterhead` (the mosque's logo at the top right, what the document is next to it, the day at the left), styled by `public/css/documents.css` and `print-toolbar.css`. Their figures come from `StudentStandings`, so a sheet and the statistics never disagree. The "back" button of every print page is `Controllers\Concerns\ReturnsToPanel`: the panel page the admin came from, never another print page, never another site.

There is one role. `User::canAccessPanel()` lets every signed-in user in, and `BaseRequest::authorize()` returns `true`. If roles are ever added, those are the two places to start.

## Configuration

| Where | What belongs there |
|---|---|
| `.env` | the database, `APP_KEY`, and optional overrides of `config/rawda.php`. Never committed |
| `config/rawda.php` | settings of **the computer**: backups folder, how many automatic backups to keep, whether the evening copy for Google Drive may run here, folder of uploaded files, the demo's port |
| table `settings` (`SettingsStore`, keys in `App\Support\SettingKey`) | settings of **the mosque**, edited in the panel: its name, the reminder interval, the evening copy for Google Drive (its folder, time and days kept; they travel with a backup, so a restored system on another computer may need its folder set again) |
| `deploy/php.ini` | the settings of the PHP that travels with the system |

Of the files in `config/`, only `app.php` and `rawda.php` were written for this project. The others are the untouched defaults of the Laravel skeleton.

## Decisions that need their reason

**The tests run on MySQL, not SQLite.** The schema uses foreign keys with `RESTRICT`, `CHECK` constraints, enums and strict mode, and the backup code speaks MySQL. Testing on another engine would test another system. `Tests\TestCase` refuses to run on a database whose name does not end with `_testing`.

**`php -S` instead of `php artisan serve`.** `artisan serve` starts the web server as a child process and rebuilds its environment from `.env`, dropping what the caller set. `demo.bat` sets `DB_DATABASE=rawda_demo` before starting; through `artisan serve` that was lost and the demo served the real database. `deploy/serve.bat` therefore starts PHP's built-in server directly with Laravel's router file.

**The demo is recognised by its database, not by a flag.** `DemoMode::isOn()` is true only when the database name ends with `_demo`. A flag can be set wrongly; a system connected to the real data can never believe it is the demo.

**Memorized pages are rebuilt, not updated.** The admin can back-date, correct and delete recitations. Maintaining "who owns page 37" incrementally has endless edge cases, so `MemorizationLedger::rebuildFor($student)` recomputes one student from their recitations after every change. A student has at most 604 pages and a few hundred recitations; the rebuild is cheap and always right.

**Everything a student does belongs to the enrollment.** Attendance, recitations and points point to `enrollments` (a student in a course), not to the student and not to the circle. A transfer only changes `enrollments.circle_id`, so the history follows the student to the new circle with no data to move. See [domain.md](domain.md).

**Money is never a float.** `App\Support\Money` computes in integers; MySQL sums `DECIMAL` columns; amounts travel as decimal strings.

**Backups are written in PHP.** `DatabaseDumper` writes plain SQL in a strict line format, and `DatabaseRestorer` refuses any file with a line it would not have written. No `mysqldump` binary is needed, and the code works under a folder with an Arabic name.

**Paths with Arabic letters.** The development folder is `E:\جوامع\rawda`. Windows programs that read paths through the old ANSI functions break there. That is why the bundled PHP is told where its `php.ini` is by a relative path (`-c php\php.ini`), why Composer's scripts are run by `deploy/after-update.bat` instead of by Composer, and why the batch files contain ASCII only.

**Offline.** The font (IBM Plex Sans Arabic) is in `public/fonts`, Filament's assets are published into `public`, QR codes are SVG generated in PHP. Before adding a library, check that it does not load anything from a CDN.
