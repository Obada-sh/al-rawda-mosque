# Rawda developer guide

Rawda is the management system of a mosque's Quran school: students, courses and circles, attendance by QR card, recitation and juz exams, points, statistics, the "Quran Knight" ranking, PowerPoint slides of the best students, printed sheets, expenses and backups (with an evening copy for Google Drive).

It runs on **one Windows laptop, offline**, for **one admin**. Those two facts explain most of its design: there is no queue, no scheduler, no mail, no roles, and nothing is loaded from the internet (the evening copy for Google Drive is only put in a folder: the Google Drive program sends it).

The guide for the people who *use* the system is the Arabic [README](../README.md) in the root of the project. This folder is for the people who *change* it.

## Where to start

| You want to | Read |
|---|---|
| Understand how the code is organised and why | [architecture.md](architecture.md) |
| Understand the tables and the rules of the mosque | [domain.md](domain.md), and [erd.sql](erd.sql) to see the tables in MySQL Workbench |
| Set up a development computer, run the tests, add a feature | [development.md](development.md) |
| Push to GitHub, install the mosque laptop, update it | [deployment.md](deployment.md) |

## The stack

| Part | Version | Notes |
|---|---|---|
| PHP | 8.4 | the version the system is installed with and tested on |
| Laravel | 13 | |
| Filament | 5 | the whole interface is one Filament panel at `/admin` |
| Livewire | 4 | comes with Filament |
| MySQL | 8 | also for the tests: they run on a real MySQL database |
| PHPUnit | 12 | |
| bacon/bacon-qr-code | 3 | QR codes as SVG, no image extension needed |

There is no Node build. The styles of the project are plain CSS files in `public/css`, and Filament publishes its own scripts and styles into `public`. The default slide design (`resources/pptx/leaders.pptx`) is the mosque's own PowerPoint file, edited in PowerPoint.

## The project in one page

```
app/
  Http/Requests/     every validation rule, one class per use case
  Actions/           every write, one class per use case
  Services/          reads and calculations that more than one action or screen needs
  Models/            Eloquent models: relations, casts, small questions about one row
  Filament/          the admin panel: screens only, no rules
  Contracts/         the few interfaces the application layer depends on
  Enums/  Support/   values with Arabic labels; small pure helpers
  Console/Commands/  rawda:prepare, rawda:backup, rawda:admin, rawda:demo, ...
config/rawda.php     settings that belong to the computer (folders, demo port)
database/            migrations, the two seeders of a new database, the demo seeder
deploy/              what start.bat, demo.bat and update.bat share
docs/                this guide
resources/pptx/      the default slide design
tests/               Unit (no database) and Feature (MySQL), Feature/Panel drives the screens
start.bat            starts the real system        http://127.0.0.1:8000
demo.bat             starts the demo               http://127.0.0.1:8010
update.bat           brings an installed copy up to date from GitHub
```

A request, from the click to the database:

```
screen (app/Filament)                 shows forms and tables, holds no rule
   └─ Attempt::form / action / ...    turns exceptions into what the screen shows
        └─ Action::handle($input)     one use case, in a transaction
             ├─ XxxRequest::validateData($input)   shape of the input  → ValidationException
             ├─ business rules                      state of the system → BusinessRuleViolation
             └─ models and services                 the write itself
```

## Five rules that keep the code the way it is

1. **Validation lives in `app/Http/Requests` and nowhere else.** A screen never validates; an action never trusts its input.
2. **One action per use case.** Anything that changes data goes through a class in `app/Actions`, also when a test or a console command does it.
3. **Screens hold no rule.** Every call from a screen into an action goes through `App\Filament\Support\Attempt`.
4. **Never edit a migration that has shipped.** Add a new one. Old backups are restored with their old tables and then migrated forward.
5. **The scripts are part of the system.** `start.bat`, `demo.bat` and `update.bat` have tests (`tests/Feature/LauncherTest.php`). Never start the system with `php artisan serve` from a script: it hides the caller's environment from the system it serves, which is how the demo once showed the real data.

The reasons behind each rule are in [architecture.md](architecture.md).
