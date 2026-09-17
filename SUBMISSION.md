# Before this is submitted

`php bin/review-check.php` covers the mistakes a script can see. What follows is the
part it cannot: things only a person with an account, a browser and a real site can do.
Each one below has rejected somebody else's plugin.

## Must be done by a person

- [ ] **`Contributors:` in readme.txt must be a real wordpress.org username.** It
      currently says `humainbox`, which is a guess. Register the account first, then put
      the actual username there. A contributor slug that does not resolve is an
      immediate bounce, and it is the single most avoidable one on this list.

- [ ] **Check the slug is free** at `wordpress.org/plugins/humainbox/`. The slug is
      taken from the plugin name at submission and **cannot be changed afterwards** —
      not by you, not by the review team.

- [ ] **Confirm `Tested up to:`** is the current WordPress version on the day of
      submission. It says 7.1. A value two majors behind reads as abandoned before
      anybody opens a file.

- [ ] **Install and run the official Plugin Check plugin** (`plugin-check`) against the
      ZIP. It is what the reviewer runs first, and it sees things this repository's own
      script does not.

## Must be tested on a real site

The adapters are written against each form plugin's public API, but they have not been
run against a live install. Untested code that writes to somebody's notification
settings is the one thing here that could actually cost a business its enquiries.

For **each** of Contact Form 7, WPForms and Gravity Forms:

- [ ] The form appears in the table with the address it really delivers to
- [ ] Applying changes the address **and the form's own settings screen agrees**
- [ ] The rest of the mail template is untouched — subject, body, headers, attachments
- [ ] Restoring puts the original back, exactly
- [ ] Applying twice does not overwrite the backup with our own address
- [ ] A real submission arrives at the new address

And once, anywhere:

- [ ] Nothing this plugin adds appears in the page source of the public site
- [ ] A non-administrator cannot reach the screen or post to the handlers
- [ ] Deleting the plugin leaves the forms working

## Packaging

- [ ] `bin/` is **excluded from the ZIP**. It is a development tool, it uses PHP 8
      functions without WordPress's polyfills, and shipping it invites questions about
      code that is not part of the plugin.
- [ ] `SUBMISSION.md` is excluded too.
- [ ] The ZIP's top-level folder is `humainbox/`.

```
zip -r humainbox.zip humainbox -x 'humainbox/bin/*' 'humainbox/SUBMISSION.md' 'humainbox/.git/*'
```

## What the review will probably ask about

Worth having the answer ready rather than discovering it in a reply three weeks later.

**"Your plugin requires a paid service."** It does not. The inventory — every form and
where it delivers — works with no account and no address entered, and that is stated in
the readme's first lines. The service is optional and disclosed.

**"Where does data go?"** Nowhere. This plugin makes no network requests at all. The
`== External services ==` section says so, and the code contains no `wp_remote_*` call
to check it against.

**"Why do you need to write to other plugins' settings?"** Because that is the entire
function, it is done through each plugin's own public API rather than the database, and
the previous value is recorded before anything is overwritten.
