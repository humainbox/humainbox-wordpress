# Before this is submitted

`php bin/review-check.php` covers the mistakes a script can see, and `bin/build.sh`
makes the ZIP that actually goes out. Both are clean. What follows is the part they
cannot do.

## Done — verified on a real site, not reasoned about

The plugin was installed on a live WordPress (6.9.1) with Contact Form 7 and WPForms
Lite, and the full cycle was run through both adapters. Three bugs came out of it that
no amount of reading would have found, because every line of all three was correct PHP:

- **Contact Form 7 would have overwritten a per-submission recipient.** Three of six
  forms on the first real site delivered to a mail tag, one of them
  `[custom-post-author-email-shortcode]` — the author of the listing being enquired
  about. Replacing that with one address sends every listing's enquiries to the wrong
  person, silently. Refused now, in the adapter and in the interface.
- **The WPForms adapter never activated.** `isset( wpforms()->form )` is false on
  current WPForms, which keeps its objects in a registry. A site with WPForms active
  was told "WPForms is not active on this site".
- **Restore corrupted multi-notification forms.** The backup stored the readable
  summary — `{admin_email}, {field_id="1"}` — and the restore wrote that whole string
  into the first notification as if it were one address, then deleted the backup. The
  undo destroyed the only record of what it was undoing. Backups are per-notification
  snapshots now.

Also fixed while running the official Plugin Check against the built ZIP:

- **The ABSPATH guard was invisible to the checker.** Correct, present, and on line 58
  — and Plugin Check reads only the first fifty. It is above the rationale now.
- **The developer's own files were being shipped**, producing 41 of 44 findings.
  `.distignore` and `bin/build.sh` exist for that; the ZIP is 13 files.
- The short description was 152 characters against a hard limit of 150.

`wp plugin check humainbox` on the built ZIP: **no errors found.**

## Found in the 2026-09-30 review, verified in a scratch WordPress 7.1.2

37 checks against real Contact Form 7 and WPForms Lite forms, plus Plugin Check 2.1
(including experimental checks): no errors, no warnings.

- **WPForms: every backslash in a form was deleted on save.** wpforms_decode() unslashes,
  and update() unslashes again in WPForms' default mode. Reproduced, then fixed by writing
  back the raw JSON slashed as far as update() will unslash it.
- **Forms with a visitor-copy read "Unchanged" after being pointed at us**, and the route
  said "nothing routed yet". Status now comes from the adapter (`routing`).
- **The bare humainbox.com domain was accepted** — hello@ would have sent customers' leads
  to our support desk. Only `in.humainbox.com` (and subdomains) now.
- **Restore overwrote changes made by hand since.** It now only restores notifications
  that still go to Humainbox, and says so.
- **Gravity: toType 'email' with a merge tag ({Email:3}) was overwritten.** Same rule as
  the other two adapters now.
- A failed apply left a backup record for an unchanged form; it is withdrawn now.
- New: a test-message button, notes for multi-recipient forms and Cc/Bcc/Mail (2) copies.

## A near miss worth keeping

`assets()` — the method that enqueues the stylesheet and the script — was written,
then silently lost to a `git checkout` run to undo a deliberate test mutation. It took
the notice-copy fix with it. Nothing failed: the files shipped, the screen rendered,
and the select-all did not select while the confirm before rewriting somebody's live
forms did not confirm. It looked exactly like a plugin that works.

`bin/review-check.php` now fails when an asset ships and nothing enqueues it. The file
being present is not the feature; the file being loaded is.

## Still needs a person

- [x] **`Contributors: humainbox`** — the account exists (registered 2026-09-30 with
      `accounts@humainbox.com`, which is how the review team verifies brand ownership).

- [x] **Name stays "Humainbox", slug `humainbox`.** A longer, descriptive name would make
      the slug `humainbox-contact-form-…` while the text domain stays `humainbox` — a
      mismatch the review bounces. The display title can be made descriptive after
      approval; the slug never can.

- [ ] **Confirm `Tested up to:` on the day.** It says 7.1, which was correct when
      checked against api.wordpress.org. Do not take it from a local install — those
      lag, which is what an out-of-date site is.

- [ ] **Screenshots.** The icon and banner are done — `assets/` holds
      `icon-256x256.png`, `icon-128x128.png` and both banner sizes, rendered from the
      brand mark. Screenshots are not: take one of the settings screen with a few real
      forms in it, save it as `assets/screenshot-1.png`, and add a Screenshots section
      to the readme with its caption.

      ⚠️ `assets/` is in `.distignore` on purpose. wordpress.org serves it from the
      repository, not from the plugin — shipping it would put artwork on every install
      to draw a page nobody sees from inside their dashboard.

- [x] **1.1.0 adapters run against real forms (2026-10-01):** Elementor Forms (via the GPL
      Pro Elements fork), Ninja Forms, Fluent Forms, Forminator and Formidable, in a MySQL
      WordPress — 48 checks. SQLite cannot run their tables; use MySQL.

- [ ] **Gravity Forms has still never been run.** Contact Form 7 and WPForms have.
      Gravity is paid, so it needs a licence or a trial site — and its adapter is the
      one whose write path has had the least contact with reality.

- [ ] Read the whole settings screen once in a browser, and once under 782px.
      Everything here was verified through WP-CLI, which renders no layout at all —
      the route drawing, the state dots and the callout have been proved to be in the
      HTML and never once seen.
