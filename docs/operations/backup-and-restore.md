# Backup and restore

Sales records must be kept for 10 years (BIR), so backups run every night and
a restore is tested before go-live and every quarter after.

## What is backed up

- The whole database (`mysqldump`, single transaction, so the POS can keep selling).
- `storage/app`: prescription photos and import files.
- `.env`: its `APP_KEY` is needed to read encrypted ID numbers after a restore.

Each backup is a zip encrypted with `BACKUP_ARCHIVE_PASSWORD` and checked after
it is written (`verify_backup`). Keep that password somewhere other than the
server, for example in the owner's password manager. A backup without it is
unreadable.

## Where copies go

| Disk | Set by | Purpose |
| --- | --- | --- |
| `local` | `storage/app/pharmacy-ims` | Fast restore after a mistake |
| `offsite` | `BACKUP_OFFSITE_PATH` | Survives theft, fire or a dead disk |

Point `BACKUP_OFFSITE_PATH` at a folder that leaves the building, such as a
Google Drive or OneDrive for desktop folder, or a network share, then set
`BACKUP_DISKS=local,offsite`.

Retention: every backup for 7 days, daily for 16 days, weekly for 8 weeks,
monthly for 12 months, yearly for 10 years.

## Schedule

| Time | Task |
| --- | --- |
| 01:00 | `backup:clean` removes old backups |
| 01:30 | `backup:run` makes tonight's backup |
| 07:00 | `backup:monitor` emails `BACKUP_NOTIFY_EMAIL` if the newest backup is over a day old |

On Windows, create a Task Scheduler task that runs every minute:

```
php C:\path\to\IMS-Pharmcy-with-POS\artisan schedule:run
```

Set `DB_DUMP_BINARY_PATH` to the folder holding `mysqldump.exe`, for example
`C:\Program Files\MySQL\MySQL Server 8.4\bin`.

## Run a backup by hand

```
php artisan backup:run
php artisan backup:list
```

## Restore drill

Do this on a spare machine or a staging copy, never on the live server.

1. Copy the newest zip from the `offsite` folder.
2. Extract it with 7-Zip, entering `BACKUP_ARCHIVE_PASSWORD`.
3. Create an empty database and load the dump:
   `mysql -u root -p pharmacy_ims_restore < db-dumps/mysql-pharmacy_ims.sql`
4. Copy `storage/app` back into place, and copy the `.env`, changing
   `DB_DATABASE` to the restored database.
5. Run `php artisan migrate --force` (it should report nothing to migrate).
6. Sign in and check: the last invoice number, today's Z-reading, stock on
   hand for three products, and that a senior citizen's ID number displays.
7. Record the date, the backup file name and the result in the restore log
   below.

## Restore log

| Date | Backup file | Restored by | Result |
| --- | --- | --- | --- |
| | | | |
