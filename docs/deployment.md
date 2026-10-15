# GitHub, the mosque laptop, and updates

The project is developed at home, kept on GitHub, and runs on the mosque laptop. Changes travel one way:

```
home computer ──git push──▶ GitHub ──update.bat (git pull)──▶ mosque laptop
```

Nobody edits the system on the laptop. The data travels the other way only as backup files.

## What git carries and what it does not

| | In the repository | How the laptop gets it |
|---|---|---|
| The code, the docs, the default slide designs, the fonts | yes | `git pull` |
| `vendor/` (the libraries) | no, only `composer.lock` | the package the first time; `composer install` on later updates |
| `public/css/filament`, `public/js/filament`, `public/fonts/filament` | no, generated from the libraries | the package; `php artisan filament:upgrade` on updates |
| `php/` (PHP itself, with `composer.phar`) | no | the package |
| `.env` (database password, application key) | **never** | copied by hand, once |
| `backups/`, `storage/` contents, the database | **never** | they are the laptop's own |

`.gitignore` keeps everything below the first row out. Before the first push, look at `git status` and make sure no `.env`, no `.zip` backup and no `.sql` dump is listed (the only SQL file of the project is `docs/erd.sql`, the diagram of the tables).

## Once: put the project on GitHub

Create an **empty** repository on GitHub (no README, no .gitignore). A **private** repository is the better choice for a system that belongs to one mosque; the repository holds no password and no data either way. Then, in the project folder at home:

```bat
git init -b main
git add .
git status
git commit -m "Rawda: the mosque management system"
git remote add origin https://github.com/<you>/<repository>.git
git push -u origin main
```

Git may warn that "CRLF will be replaced by LF" in a few files (`composer.json`, `lang/ar/*.php`). That is `.gitattributes` doing its work and is harmless.

Read what `git status` lists before you commit. It should be around 500 files (under 3 MB): `app`, `bootstrap`, `config`, `database`, `deploy`, `docs`, `lang`, `public`, `resources`, `routes`, `storage` (only its `.gitignore` files), `tests` and the files of the root.

## Once: install the mosque laptop

On the laptop you need Windows 10 or 11 (64-bit) and:

- **MySQL Server 8**, installed as a Windows service, with the `root` password that is in the `DB_PASSWORD` line of your `.env` (or change that line on the laptop);
- **Git for Windows** (https://git-scm.com), with its default options;
- if Windows reports a missing `VCRUNTIME140.dll` when the system starts: **Microsoft Visual C++ Redistributable 2015–2022 (x64)**.

PHP and Composer do not need to be installed: they travel in the package.

Steps:

1. **At home**, after pushing: double-click `deploy\make-package.bat`. It builds `rawda-package` next to the project folder: a clone of what you committed, plus `vendor`, the panel's generated assets, and `php` with `composer.phar`. About 200 MB.
2. Copy your **`.env`** into the package by hand. The tool never copies it.
3. Move the package to the laptop, to a simple path such as **`C:\rawda`**.
4. On the laptop, double-click **`start.bat`**. The first start creates the database and its tables and asks you to create the admin account.
5. Still on the laptop, with the system closed and the laptop online, double-click **`update.bat`** once. It should say "This folder is up to date". If the repository is private, Git asks you to sign in to GitHub the first time; sign in with an account that can read the repository. Doing this on installation day means the first real update holds no surprise.

For a private repository, an account or token that can only **read** this one repository is enough for the laptop, and safer than your own account. GitHub offers that as a fine-grained personal access token (repository access: this repository only; permission "Contents": read-only), which you paste when Git asks for a password.

### Without the package

A plain clone also works, on any computer that has PHP 8.4, Composer, MySQL and Git:

```bat
git clone https://github.com/<you>/<repository>.git C:\rawda
```

Copy `.env` into it, then double-click `deploy\after-update.bat` (it installs the libraries, which needs the internet, and prepares the database), then `start.bat`.

## Every update

**At home**

1. Make the change. If it changes the database, it is a new migration.
2. Run the tests: `php vendor\phpunit\phpunit\phpunit`. All green.
3. `git add`, `git commit`, `git push`.

**At the mosque**

1. Close the system: close its black window (and the demo's, if open).
2. Make sure the laptop is online.
3. Double-click **`update.bat`**.
4. When it says "Start the system with start.bat", do that.

What `update.bat` does, in order:

| Step | What happens | If it fails |
|---|---|---|
| 1 | Checks that Git is there, that the folder is a clone, that ports 8000 and 8010 are free (system and demo closed) | Says what to do. Nothing was changed |
| 2 | Checks that no file of the system was changed on the laptop | Lists the files and stops. Nothing was changed |
| 3 | `git pull --ff-only` | "Could not be downloaded": no internet, or not signed in. Nothing was changed |
| 4 | `deploy\after-update.bat` asks Composer whether the libraries are already the ones `composer.lock` names. If not: `composer install`, up to three tries | **Goes back** to the version before (`git reset --hard` to the commit noted in step 2) and says the system was not updated. The system still runs |
| 5 | Rebuilds the framework's package list and Filament's assets | "The update is not complete" |
| 6 | `php artisan rawda:prepare`: a `before-update` backup if the database will change, then the migrations, then the daily backup | "The update is not complete". The backup is in `backups` |

Most updates change no library. Step 4 then installs nothing and the whole update takes well under a minute. When libraries did change, expect several minutes: Composer downloads them and then reads the whole `vendor` folder again. On a freshly copied folder that reading alone took about five minutes on the development computer.

The libraries are installed **with** the development ones (no `--no-dev`). That is deliberate: it is exactly the set the tests ran with at home, and the laptop serves only itself (`127.0.0.1`). `LauncherTest` keeps it that way.

### Which version is the laptop on?

When it finishes, `update.bat` prints a line such as `Version 3f2a9c1 of 2026-10-07`. At any time, in the folder of the system: `git log -1 --oneline`.

### Going back to an older version

Only the developer does this, and only when a new version misbehaves at the mosque. In the folder of the system, with the system closed:

```bat
git log --oneline -5
git reset --hard <the commit to go back to>
deploy\after-update.bat
```

That is all when the newer version brought **no new migration**.

When it did, the database now has tables or columns the older code does not know. Finish the way back from inside the older version:

1. Start the system (now the older version) and open Settings → Backups.
2. Take a backup and download it, so nothing is lost for good. Only the newer version can restore that file.
3. Restore the backup of kind "before update" that the update took. The database is then exactly what it was before the update; what was entered since is no longer in it.

Do the code first and the restore second. A restore always ends by running the migrations the running version has, so restoring under the newer version would bring the new tables straight back.

The next `update.bat` moves the laptop forward again, so fix the problem at home and push before anybody runs it.

## Moving the data

The data never goes through git. To move it from one computer to another: on the old one, Settings → Backups → take a backup and **download** it; on the new one, **add a backup from a file**, then **restore** it. A backup made by a newer version of the system is refused by an older one: update first.

The automatic backups are on the same disk as the data. Copy the newest file from `backups\` to a USB stick regularly.

## The scripts

| File | What it does |
|---|---|
| `start.bat` | sets `APP_ENV=production`, `APP_DEBUG=false`, then `deploy\serve.bat 8000` |
| `demo.bat` | the same, plus the demo's own database, folders, cookie and cache prefix, then `deploy\serve.bat 8010 demo` |
| `deploy\serve.bat` | finds PHP (the `php` folder, else the installed one), runs `deploy\check.php`, `rawda:prepare` (and `rawda:demo` for the demo), then PHP's built-in server on `127.0.0.1` |
| `deploy\check.php` | says in plain words why this PHP cannot run the system |
| `deploy\php.ini` | the settings of the bundled PHP; copied into `php\` at every start |
| `update.bat` | checks, `git pull --ff-only`, hands over to `after-update.bat`, goes back if the libraries fail |
| `deploy\after-update.bat` | `composer install` when the libraries are not the ones `composer.lock` names, then the package list, Filament assets and `rawda:prepare`. Can be run alone to repair a folder |
| `deploy\make-package.bat` | builds the folder for the laptop from the last commit |

Three things about them that are easy to break:

- **`update.bat` may be replaced by the very update it downloads.** Windows reads a batch file while running it, so everything after `git pull` is inside one parenthesised block, which is read whole beforehand, and nothing in that block uses `goto`. Keep it that way. New steps belong in `deploy\after-update.bat`, which is started only after the pull and is therefore always the new version.
- **`after-update.bat` ends with 2 when the libraries could not be installed**, and with 1 for any other failure. `update.bat` goes back only on 2.
- **Composer's own scripts are not run on the laptop** (`--no-scripts`): `after-update.bat` runs the same commands itself, with a PHP that is told where its settings file is. If you add a script to `post-autoload-dump` in `composer.json`, add it to `after-update.bat` too; `LauncherTest` fails until you do.

`LauncherTest` checks all of this by reading the files. Run the tests after touching any `.bat` file.

## Troubleshooting

| What you see | What to do |
|---|---|
| `update.bat`: "Git was not found" | Install Git for Windows, then start it again |
| "fatal: detected dubious ownership in repository" | The folder was copied by another Windows user. Run the command Git prints (`git config --global --add safe.directory …`) once |
| "The new version could not be downloaded" | No internet, GitHub is not reachable, or Git is not signed in (private repository). The lines above the message are Git's own words |
| "These files of the system were changed on this computer" | Someone edited a file on the laptop. If the change is not wanted: `git restore .` in the folder, then `update.bat` again |
| "The system was NOT updated: the libraries…" | The download of the libraries failed three times. The laptop is back on the version it had. Try again on a better connection |
| Composer fails with "Permission denied" on a temporary file | An antivirus was scanning the file. Start `update.bat` again; what was downloaded is kept |
| "The update is not complete" after the database step | Read the lines above it. The backup taken before the update is in `backups\`. Do not start the system until it is solved; ask the developer |
| The panel has no styles after an update | `php artisan filament:upgrade` in the folder (with the bundled PHP: `php\php.exe -c php\php.ini artisan filament:upgrade`) |
| "PHP was not found" | The `php` folder is missing from the folder of the system: copy it from the package |
| "The settings file (.env) is missing" | Copy `.env` into the folder of the system |

Everything about starting the system day to day, and its own troubleshooting table, is in the Arabic [README](../README.md).

## A checklist for a release

1. `git status` is clean except for what you mean to commit.
2. The whole test suite is green.
3. A change of the database is in a **new** migration file.
4. You tried the change in `demo.bat`.
5. `git push`.
6. At the mosque: close the system, `update.bat`, `start.bat`, and look at the screen you changed.
