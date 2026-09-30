=== Humainbox ===
Contributors: humainbox
Tags: spam, anti-spam, contact form, captcha alternative, form notifications
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop contact form spam, including AI-written messages, without a CAPTCHA. Connects Contact Form 7, WPForms and Gravity Forms to Humainbox.

== Description ==

**Humainbox stops spam from reaching you through your website's contact forms — including the new kind, written by AI, that walks straight past CAPTCHAs and keyword filters. Real enquiries still reach you, and nothing is ever deleted.**

This plugin connects your forms to Humainbox in a few clicks, and shows you where every form on your site sends its enquiries today.

= Why contact form spam got worse =

Spam used to be easy to spot: broken English, a dozen links, "SEO services". Today it is written by AI. It reads like a real person, mentions your business by name, and passes every CAPTCHA, honeypot and keyword list — because it was designed to.

The usual fixes cost you real customers instead. A CAPTCHA makes genuine visitors give up before they press send. A stricter filter starts catching real enquiries, and a lost lead is silent: you only find out when somebody asks why you never replied.

= How Humainbox works =

1. **Your form sends to Humainbox.** Each form sends its notification email to your own Humainbox address instead of straight to your inbox. That is the only change — nothing is added to your website, and your visitors see no difference.
2. **Humainbox checks every submission.** Machine-written spam is held back. Each held message comes with the reason it was held.
3. **Real enquiries reach you.** They are forwarded to the people you choose, with the visitor's own address set as the reply address — so pressing Reply answers the visitor, exactly as before.

**Held is not deleted.** Anything Humainbox holds stays readable in your Humainbox panel, and one click sends it on.

= What it holds back, and what always reaches you =

**Held back as spam:**

* SEO, link-building and "rank higher on Google" offers
* Web design, app and offshore development pitches
* Crypto, traffic and directory-listing schemes
* Template messages sent to thousands of sites at once
* All of the above when written fluently by AI — the kind CAPTCHAs no longer stop

**Always reaches you:**

* Enquiries, quote requests and bookings
* Complaints and support questions
* Job applications, press and partnership approaches
* Suppliers introducing themselves
* Anything Humainbox is unsure about — when in doubt, it delivers

= Safe from the first minute =

* **Nothing is blocked in the first week.** Every new Humainbox inbox starts in *dry run*: every submission is still delivered to you, while Humainbox shows you what it would have held. You decide to switch filtering on once you have seen it work on your own mail.
* **Every change can be undone.** Before this plugin changes a form, it saves the address the form used. One button puts it back.
* **A test before anything depends on it.** One button sends a test message to your Humainbox address, the same way your forms send theirs. It also tells you if your site cannot send email at all — which would mean your forms have not been delivering either.

= What this plugin does =

* **Lists every form** from Contact Form 7, WPForms and Gravity Forms, and the address each one sends its enquiries to. This part works without an account, and warns you about any form that notifies nobody.
* **Points the forms you choose at Humainbox** in one action, instead of opening each form's settings one by one.
* **Keeps the addresses it replaced** and restores them on request.
* **Leaves alone what it should not change** — for example, the "copy of your message" email that goes back to the visitor.

It adds nothing to your pages: no scripts, no styles, no CAPTCHA, no links. It works only on its own settings screen, under Settings → Humainbox.

= Getting started =

1. Install and activate the plugin, then open **Settings → Humainbox**. The table shows your forms and where each one sends today.
2. Create a free Humainbox account at humainbox.com — no card needed.
3. In Humainbox, choose who should receive your enquiries. Anyone other than you gets one email asking them to confirm; until they do, their mail waits in your Humainbox panel.
4. Copy your inbox address (under **Inboxes**), paste it into the plugin and press **Send a test message**. It should appear in your Humainbox panel within a minute.
5. Tick the forms you want protected and press **Connect selected forms to Humainbox**.

= Supported form plugins =

* Contact Form 7
* WPForms and WPForms Lite
* Gravity Forms

Other form plugins are not listed and are never touched. You can still connect them by pasting your Humainbox address into their notification settings by hand — the steps for each are at https://humainbox.com/integrations

= About Humainbox =

Humainbox is an online service run by Reaktör Teknoloji. It has a free plan for one inbox and paid plans for teams and agencies. Everything in this plugin works the same on every plan.

== External services ==

This plugin makes no network requests. It does not call any API, load anything from a
remote server or send any data about your site in the background.

The address you enter is stored in your own database and written into your own form
plugins' settings.

Two things do result in email being sent, both only when you press a button:

* **Send a test message** sends one email, through your site's own mail (wp_mail), to the
  Humainbox address you saved. It contains your site's address and the display name of
  the logged-in user who pressed the button.
* **Connect selected forms to Humainbox** changes where those forms send their notifications.
  From then on, each form submission — the fields your visitors fill in — is emailed by
  your form plugin to your Humainbox address instead of to the address it used before.

Mail sent by your forms goes wherever the address you choose points — that is what
changing a notification recipient means, and it is true of any address you type into
those settings, ours or anyone else's. If that address is a Humainbox address, the
Humainbox service handles the message under its own terms and privacy policy:

* Terms: https://humainbox.com/legal/terms
* Privacy: https://humainbox.com/legal/privacy

== Frequently Asked Questions ==

= Is this a spam filter plugin? =

The filtering is done by the Humainbox service, not inside WordPress. That is why it adds nothing to your pages and does not slow your site down. This plugin connects your forms to it and keeps a way back.

= Does it add a CAPTCHA? =

No. Your visitors fill in your forms exactly as they do today.

= Do I need a Humainbox account? =

Not to see the list of your forms and where each one sends. You need an account, and the inbox address it gives you, to protect your forms from spam.

= How much does it cost? =

The plugin is free. Humainbox has a free plan for one inbox, and no card is needed to open an account. Paid plans for teams and agencies are listed on humainbox.com.

= What if a real enquiry is held by mistake? =

It is not lost. Held messages stay readable in your Humainbox panel, each with the reason it was held, and one click sends it on. In the first week nothing is held at all — you only see what would have been.

= Who receives the enquiries after the change? =

The people you set as recipients in Humainbox — not the addresses that were in the form before. If a form currently sends to more than one address, the plugin lists them so you can add each one in Humainbox first.

If a form also sends a copy (Cc or Bcc) to someone, that copy is left as it is and the plugin points it out: it does not pass through Humainbox, so it is not filtered.

= When I press Reply, who gets my answer? =

The visitor who filled in the form, just as before. Humainbox sets their address as the reply address on every enquiry it forwards.

= Can I undo it? =

Yes. The first time the plugin changes a form, it saves where that form used to send, and shows it in the **Before Humainbox** column of the forms table. Tick the form and press **Restore selected to original address**.

If you have changed a form by hand since, restoring leaves your change alone: only forms that still send to Humainbox are put back.

= What happens if I deactivate or delete the plugin? =

Your forms keep working exactly as they are, because the address is stored in your form plugin's own settings. The list of original addresses is deliberately kept, so reinstalling still lets you put things back. To remove it, restore your forms first.

= Does it change every notification on a form? =

No, only the ones that go to you. Notifications addressed to the visitor who filled in the form — the "copy of your message" email — or to someone worked out for each submission, such as the author of a listing, are never changed. Changing them would send you the visitor's receipt instead of them.

If a form only has notifications like that, it is listed with the reason and cannot be selected.

= Will it work with Elementor, Ninja Forms or Fluent Forms? =

Not in this version — those forms are not listed and never touched. You can still protect them by pasting your Humainbox address into their notification settings by hand. The steps for each are at https://humainbox.com/integrations

= One of my forms says it notifies nobody =

Then enquiries from that form are going nowhere. A form with no recipient address accepts submissions and tells no one. It is worth fixing whether or not you use Humainbox.

== Changelog ==

= 1.0.0 =
* First release: list Contact Form 7, WPForms and Gravity Forms notification recipients,
  connect selected forms to Humainbox in one action, send a test message, restore the
  original addresses.

== Upgrade Notice ==

= 1.0.0 =
First release.
