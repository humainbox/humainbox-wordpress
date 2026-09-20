# Humainbox for WordPress

See where every contact form on a WordPress site actually delivers, then point them all
at one address — and put the originals back whenever you like.

The first half is free, needs no account, and is the part most sites have never checked:
**a list of every form on the site and the address each one currently notifies.** Forms
accumulate. The address in one of them was typed years ago by whoever built the site, and
nothing since has re-read it.

---

## Do not take the privacy claims on trust — check them

This plugin makes two promises that matter, and both of them are negative claims. A
negative claim cannot be demonstrated by a marketing page. It can be read out of source
in about a minute, which is the entire reason this repository is public.

**1. It makes no network requests. None, ever, to anywhere — including to us.**

```sh
grep -rnE "wp_remote_|curl_init|curl_exec|fsockopen|file_get_contents|fopen" --include="*.php" \
  humainbox.php includes/ admin/
```

Expect one hit: a comment in `humainbox.php` saying there is no HTTP. Nothing else. The
plugin never phones home, never checks a licence, never reports an install, and never
sends your form addresses anywhere. It cannot, because there is nothing in it that could.

**2. It prints nothing on the public site, and adds nothing to any page a visitor loads.**

```sh
grep -rnoE "add_(action|filter)\( *'[a-z_]+'" --include="*.php" humainbox.php includes/ admin/
```

Every hook that comes back is an admin one — `admin_menu`, `admin_enqueue_scripts`,
`admin_post_*`, `plugins_loaded`, `plugin_action_links_`. There is no `the_content`
filter, no shortcode, no `wp_enqueue_scripts`, no front-end output of any kind. A visitor
to the site cannot tell the plugin is installed.

**3. It writes exactly two options, and here they are.**

```sh
grep -rnoE "(update|add|delete)_option\( *[A-Z_]+" --include="*.php" humainbox.php includes/ admin/
```

| Option | Holds |
| --- | --- |
| `humainbox_settings` | the one address you saved |
| `humainbox_original_recipients` | a snapshot of what each form sent to before you changed it |

The second one is the safety net, and it is why Restore works. Nothing writes to a form
without writing here first — see `Humainbox_Forms::apply()`.

`bin/review-check.php` enforces all three of these in CI-ish fashion before a release is
built: `curl_init`, `curl_exec`, `fsockopen`, `add_shortcode` and
`add_filter( 'the_content' )` are refused outright.

---

## What it does

**Find out where your forms send.** One screen, every form the site has, and the address
each currently notifies. Free, no account, nothing leaves the site.

**Point them at one address.** Tick the forms, paste a Humainbox address, apply. The
original address of each is saved first.

**Put them back.** Restore returns every form to the exact address it had. It refuses to
run if the snapshot is missing rather than guessing — an early version reconstructed the
address from a display string and corrupted multi-notification forms.

### Supported form plugins

| Plugin | Adapter |
| --- | --- |
| Contact Form 7 | `includes/adapters/class-humainbox-cf7-adapter.php` |
| WPForms | `includes/adapters/class-humainbox-wpforms-adapter.php` |
| Gravity Forms | `includes/adapters/class-humainbox-gravity-adapter.php` |

Each adapter reports whether a form's recipient can safely be changed, and **refuses the
ones that cannot.** That refusal is not a formality. Contact Form 7 recipients are
frequently dynamic — `[_site_admin_email]`, or a shortcode that resolves to the author of
the listing being viewed. Overwriting one of those would send every enquiry to the wrong
person, on every form, silently. The adapter will not touch a recipient that is not a
fixed address.

---

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer
- One of the three form plugins above

## Building a release

```sh
bin/build.sh
```

Produces `humainbox.zip` — the files that do the work, plus `readme.txt` and `LICENSE`.
Everything in `.distignore` stays out: the developer CLI, this README, the listing
artwork. That file is worth reading; between them those exclusions accounted for 41 of
the 44 findings the first Plugin Check run produced.

---

## About the service

Humainbox filters contact-form submissions: machine-written junk is held back, real
enquiries are forwarded to whoever should answer them, and nothing is ever deleted.

You do **not** need an account to use the audit half of this plugin, and it is worth
saying plainly that the audit is the half most sites benefit from — knowing where your
forms deliver is useful whether or not you ever change the address.

https://humainbox.com

## Licence

GPL-2.0-or-later. See `LICENSE`.
