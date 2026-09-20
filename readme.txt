=== Humainbox ===
Contributors: humainbox
Tags: contact form, spam, antispam, form notifications, email
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See where every contact form on your site sends its notifications, then point them at one address to stop spam without losing real leads.

== Description ==

**Two things, and the first one is free, works on its own, and needs no account.**

= 1. Find out where your forms actually send =

A site picks up forms over the years. One in the theme, one a plugin added, one the
last agency built, each with its own notification settings on its own screen. Nobody
remembers which is which, and "who gets the enquiries from that form" is a question
most site owners cannot answer without opening a dozen screens.

This plugin answers it in one table: every form, from every supported form plugin, and
the address each one currently sends its notifications to. It makes no network
requests, stores nothing but what you type, and needs no account with anybody.

It will also tell you when a form notifies **nobody** — a contact form that quietly
accepts submissions and tells no one is the most expensive thing on a website, and it
is commoner than it sounds.

= 2. Point them somewhere better, in one action =

If your forms are collecting more spam than leads, the usual fixes make the
problem worse in a way nobody measures: a CAPTCHA turns visitors away and an
aggressive filter starts eating real ones, and a lost lead is silent. You only find out
when somebody asks why you never replied.

Humainbox is a service that sits between a contact form and the mailbox behind it. It
checks each submission, holds back the ones written by a machine, and forwards the real
enquiries to whoever should answer them. Nothing goes on your site: you change one
field — the address a form sends its notifications to — and this plugin changes that
field on as many forms as you like at once, keeping a copy of every original.

If you use Humainbox, it will also point the forms you choose at your Humainbox address
in one action — and keep a copy of what each one said before, so you can put them back
just as easily.

= Changing the address does not start blocking anything =

A new Humainbox inbox begins in dry run. Every submission is forwarded exactly as it is
today while the service records what it would have held, and you decide after a week of
your own mail. So pointing a live form at it is a change you can watch before it does
anything — and the address that form used before is saved here either way.

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
It checks each submission, holds the ones written by a machine, and forwards the real
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

Not for the inventory. Listing your forms and seeing where each one sends works with no
account, no address entered and no network request of any kind. You need an address
only if you want to point forms at one.

= What does a Humainbox account cost? =

It is free to open and free while you evaluate, with no card. There are paid plans for
larger sites; what they cost is on humainbox.com and this plugin does not ask you for
anything. Nothing on this screen changes until you paste in an address and press a
button.

= What happens to my original addresses? =

The first time this plugin changes a form, it records every notification on it and
where each one was addressed. Those are shown on the settings screen and can be put
back with one action — each notification to exactly the address it had, not to a
summary of them.

= What happens if I delete the plugin? =

Your forms are left exactly as they are — whatever address they currently deliver to
keeps working. The plugin's own settings are removed. The record of original addresses
is deliberately kept, so that reinstalling still lets you put things back; if you want
it gone, restore your forms first and the record clears itself.

= Does it change every notification on a form? =

Only the ones addressed to a fixed address — that, or the form plugin's own tag for
your site's admin address, which always resolves to the same place.

Notifications addressed with a tag that is worked out for each submission are left
exactly as they are, in all three plugins. Those are the "a copy of your enquiry"
mails that go back to the person who filled the form in, and notifications routed to
the author of whatever they were enquiring about. Overwriting one of those does not
redirect it to you — it sends you somebody else's receipt and sends them nothing.

If every notification on a form is addressed that way, the form is listed with the
reason and no checkbox, rather than offered and then reported as a failure.

= Will it work with Elementor, Ninja Forms, Fluent Forms? =

Not yet. Those store notification settings differently and this version does not touch
them rather than touch them badly. Pointing them at an address by hand works exactly the
same — it is one field in each of their own notification settings, and the menu path for
each is written out at https://humainbox.com/integrations

= One of my forms says it notifies nobody =

Then it does. A form with no recipient address accepts submissions and tells no one,
which is the most expensive thing a contact form can do quietly. That is worth fixing
whether or not you ever use Humainbox.

== Changelog ==

= 1.0.0 =
* First release. Inventory of Contact Form 7, WPForms and Gravity Forms notification
  recipients; bulk repointing; restore of original addresses.

== Upgrade Notice ==

= 1.0.0 =
First release.
