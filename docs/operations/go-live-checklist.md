# Go-live checklist (Phase 9)

Work through this in order. Items marked **(people)** cannot be done in code;
they need the owner, pharmacist, accountant or staff. Go live only when the
last section passes seven days in a row.

## 1. Decisions (Phase 0, must be settled first)

- [ ] **(people)** BIR path agreed with the accountant: accredited POS
      (Certificate of Accreditation + Permit to Use per terminal), or the
      fallback of recording sales here while official invoices stay on the
      current registered machine or manual invoices.
- [ ] **(people)** Hosting: in-store server on the local network (recommended
      where the internet is unreliable) or cloud.
- [ ] **(people)** Dangerous drugs in scope? If not, turn off
      _Store settings → Prescription → Store sells dangerous drugs_.
- [ ] **(people)** Confirm with the RDO/pharmacist: how long filled
      prescriptions are kept, and whether PWD customers still need a booklet
      (_Store settings → Statutory discounts_).

## 2. Server

- [ ] MySQL 8.4 LTS installed; `DB_CONNECTION=mysql`, database created.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set, new `APP_KEY`.
- [ ] `REQUIRE_TWO_FACTOR=true`, `SESSION_ENCRYPT=true`, HTTPS on (even on the LAN: a self-signed or internal CA certificate).
- [ ] Mail configured (`MAIL_*`) so expiry alerts, backup failures and the daily summary arrive.
- [ ] `php artisan migrate --force` then `php artisan db:seed --class=RolesAndPermissionsSeeder --force`.
- [ ] `php artisan config:cache route:cache view:cache` and `npm run build`.
- [ ] Windows Task Scheduler runs `php artisan schedule:run` every minute (backups, ledger check, expiry alerts, daily summary).
- [ ] `php artisan queue:work` runs as a service (notifications use the queue).

## 3. Backups (see backup-and-restore.md)

- [ ] `BACKUP_ARCHIVE_PASSWORD` set and stored outside the server.
- [ ] `BACKUP_OFFSITE_PATH` points at a synced cloud folder or network share; `BACKUP_DISKS=local,offsite`.
- [ ] `php artisan backup:run` succeeds; the zip appears in both places.
- [ ] **(people)** Restore drill done on another machine and recorded in the restore log.

## 4. Store setup

- [ ] Owner account created; demo users removed (never run `db:seed` on production — it creates demo users).
- [ ] _Store settings_: registered name, TIN, address, VAT status, BIR accreditation number, FDA licence, receipt footer.
- [ ] Branch and terminals with BIR machine identification number (MIN), Permit to Use and serial.
- [ ] Staff accounts with roles; supervisor PINs for pharmacists; owner and pharmacists turn on two-factor.
- [ ] Product list imported (_Products → Import_); spot-check 20 products, prices, units and barcodes.
- [ ] Suppliers, prescribers and regular senior/PWD customers entered.
- [ ] **(people)** Full physical count; opening stock imported (_Stock on hand → Opening stock_) the night before go-live.
- [ ] Thermal printer set as the browser's default printer, 80 mm paper, "open cash drawer" enabled in the driver; scanner set to send Enter after each code.

## 5. Acceptance tests (people, on the real hardware)

- [ ] **(people)** A cashier completes a five-item cash sale with only the keyboard and scanner.
- [ ] **(people)** A senior citizen sale prints the VAT exemption and 20% discount; the pharmacist checks the amounts.
- [ ] **(people)** An Rx sale with a partial fill; the prescription book shows the balance.
- [ ] **(people)** Void and refund with the supervisor PIN; both print their own numbers.
- [ ] **(people)** Pull the network cable mid-sale, reconnect, press F12: one invoice only.
- [ ] **(people)** Close the shift, print the X-reading, run the Z-reading; the Z-reading matches _Reports → Sales by day_.
- [ ] **(people)** The pharmacist and accountant sign off a printed invoice, a Z-reading and each record book.
- [ ] Load test on a copy of the server: `php artisan pharmacy:load-test --products=<your SKU count> --days=365` — product search under 1 s, reports under 3 s.

## 6. Parallel run (1–2 weeks)

- [ ] **(people)** Run beside the old process; reconcile each day's Z-reading against the old totals and the cash count.
- [ ] **(people)** Spot-check 10 products' shelf stock against _Stock on hand_ each day.
- [ ] `stock:verify-ledger` reports no mismatch (it runs nightly; check the log/audit log).

## 7. Go-live gate

- [ ] For seven days in a row: Z-reading, cash count and stock spot-checks all reconcile.
- [ ] **(people)** BIR accreditation and permit steps completed per the decision in section 1.
- [ ] **(people)** On-site support arranged for the first week.
