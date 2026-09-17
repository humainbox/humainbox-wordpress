=== Humainbox ===
Contributors: humainbox
Tags: contact form, spam, antispam, form notifications, email
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See where every contact form on this site delivers, point them all at one address in a click, and keep a copy of the originals so you can put them back.

== Description ==

A site picks up forms over the years. One in the theme, one a plugin added, one the
last agency built, each with its own notification settings on its own screen. When you
need to change where enquiries go, "change the recipient address" turns into finding
twelve settings pages and hoping you found them all.

This plugin lists them in one table: every form, from every supported form plugin, and
the address each one currently delivers to. That part works on its own and needs no
account anywhere.

If you use Humainbox, it will also point the forms you choose at your Humainbox address
in one action — and keep a copy of what each one said before, so you can put them back
just as easily.

= It is not part of your site's request handling =

This plugin writes a setting and gets out of the way. It adds nothing to your pages, no
scripts, no styles, no credits, no links. Deactivate it or delete it and your forms
carry on exactly as they are, because the address lives in your form plugin's own
settings, not here.

= Supported form plugins =

* Contact Form 7
* WPForms and WPForms Lite
* Gravity Forms

Forms from other plugins are not listed. The table will say which of the three are
active on your site so you can tell the difference between "no forms" and "not
supported".

= What Humainbox is =

Humainbox is a paid service that sits between a contact form and the mailbox behind it.
It reads each submission, holds the ones written by a machine, and forwards the real
enquiries to whoever should answer them. This plugin is a convenience for setting it up
across several forms at once; the service works exactly the same if you change the
addresses by hand.

== External services ==

This plugin does not contact any external service. It makes no network requests at all.

The address you enter is stored in your own database and written into your own form
plugins' settings. Nothing about your site, your forms or your visitors is sent
anywhere by this plugin.

Mail sent by your forms goes wherever the address you choose points — that is what
changing a notification recipient means, and it is true of any address you type into
those settings, ours or anyone else's. If that address is a Humainbox address, the
Humainbox service handles the message under its own terms and privacy policy:

* Terms: https://humainbox.com/legal/terms
* Privacy: https://humainbox.com/legal/privacy

== Frequently Asked Questions ==

= Do I need a Humainbox account? =

Not for the inventory. Listing your forms and seeing where each one delivers works with
no account and no address entered. You need an address only to point forms at one.

= What happens to my original addresses? =

The first time this plugin changes a form, it records what that form delivered to
beforehand. Those are shown on the settings screen in plain text and can be restored
with one action.

= What happens if I delete the plugin? =

Your forms are left exactly as they are — whatever address they currently deliver to
keeps working. The plugin's own settings are removed. The record of original addresses
is deliberately kept, so that reinstalling still lets you put things back; if you want
it gone, restore your forms first and the record clears itself.

= Does it change every notification on a form? =

For WPForms, yes — every notification on the form, because leaving one behind would
send an unfiltered copy as well.

For Gravity Forms, only notifications addressed to a plain email address. Notifications
routed to a form field or by conditional rules are left alone: those send to whatever
the visitor typed, and overwriting them would break a "send me a copy" confirmation
rather than redirect it.

= Will it work with Elementor, Ninja Forms, Fluent Forms? =

Not yet. Those store notification settings differently and this version does not touch
them rather than touch them badly.

== Changelog ==

= 1.0.0 =
* First release. Inventory of Contact Form 7, WPForms and Gravity Forms notification
  recipients; bulk repointing; restore of original addresses.

== Upgrade Notice ==

= 1.0.0 =
First release.
