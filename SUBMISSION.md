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

## A near miss worth keeping

`assets()` — the method that enqueues the stylesheet and the script — was written,
then silently lost to a `git checkout` run to undo a deliberate test mutation. It took
the notice-copy fix with it. Nothing failed: the files shipped, the screen rendered,
and the select-all did not select while the confirm before rewriting somebody's live
forms did not confirm. It looked exactly like a plugin that works.

`bin/review-check.php` now fails when an asset ships and nothing enqueues it. The file
being present is not the feature; the file being loaded is.

## Still needs a person

- [ ] **`Contributors:` must be a real wordpress.org username.** It says `humainbox`,
      which is a guess. Register the account, then put the actual slug there. A
      contributor that does not resolve is an immediate bounce and the most avoidable
      one on this list.

- [ ] **⚠️ DECIDE THE PLUGIN NAME BEFORE SUBMITTING. THE SLUG IS TAKEN FROM IT AND CAN
      NEVER BE CHANGED** — not by you, not by the review team.

      "Humainbox" is a service nobody has searched for yet. wordpress.org's search
      reads the title, so a plugin called only that is findable by people who already
      know the name and by nobody else — which is the opposite of why it exists. A
      title carrying what it does ("Humainbox — Contact Form Recipients", or similar)
      is findable by somebody typing "contact form notification email" into their own
      dashboard. This is the one decision here that cannot be undone later.

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

- [ ] **Gravity Forms has still never been run.** Contact Form 7 and WPForms have.
      Gravity is paid, so it needs a licence or a trial site — and its adapter is the
      one whose write path has had the least contact with reality.

- [ ] Read the whole settings screen once in a browser, and once under 782px.
      Everything here was verified through WP-CLI, which renders no layout at all —
      the route drawing, the state dots and the callout have been proved to be in the
      HTML and never once seen.
