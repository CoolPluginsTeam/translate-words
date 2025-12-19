=== Translate Words ===
Contributors: BinaryMoon, Coolplugins
Tags: gettext, ngettext, string translations, translate
Requires at least: 4.9.0
Tested up to: 6.5.0
Stable tag: trunk
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://paypal.me/binarymoon

Translate all the strings of your website through the WordPress admin.

== Description ==

Translate words used in plugins and themes with the text of your choice.

This plugin allows you to translate the original plugin/ theme text AND the translations that have been created. For example if you're using the German translation of a theme, you can then change the translated text to something that fits your website better.

You can also change the core WordPress text to something else. For example you might wants to change 'More Posts' to 'More Articles' or 'More Stories'.

Your translated text can replace strings with text containing HTML. This works well when you want to add links, or make part of the text bold/ italic.

It will NOT translate dynamic strings that use %s or %d, so "%s has been added to your cart." is not translatable.

**Inspired by these plugins**

I made Translated Words because I wanted a very simple plugin that worked the way I wanted, to do this I forked [WP Override Translations](https://wordpress.org/plugins/wp-override-translations/). I later found out that WP Override Translations is a fork of [Gettext override translations](https://wordpress.org/plugins/gettext-override-translations/).

== Usage ==

1. Install and activate the plugin
1. Go to Settings > Translate Words
1. Enter text found in your theme/ plugins in the 'Current' box.
1. Enter the text you want to use in the 'New' box.
1. Hit save.

Note: This does not work with post content, it is only for text passsed through the gettext functions.

== Screenshots ==

1. An example translating an English word to a different phrase.

== Changelog ==

= 1.2.6 - 6th February 2024 =
* Improve the previous fix. It didn't pick up on translations where words/ phrases contained punctuation.

= 1.2.5 - 30th January 2024 =
* Tweak replacements to only replace whole words and not the middle of words.

= 1.2.4 - 4th March 2023 =
* Fix PHP 8.2 error.

= 1.2.3 - 1st January 2023 =
* Fix undefined array key error in admin.

= 1.2.2 - 12th July 2022 =
* Do case sensitive replacements first, then case insensitive ones.

= 1.2.1 - 22nd May 2022 =
* Fix issue with new translations not saving properly.
* Reduce duplication further (and remove the liklihood of this bug from re-appearing).

= 1.2.0 - 24th January 2022 =
* Fix bug with the remove button not working. Thanks @capbussat for the suggested fix and others for the reports!

= 1.1.1 - 23rd November 2021 =
* Escape js output.

= 1.1 - 23rd November 2021 =
* Add support for translating Gutenberg editor strings that use the new(ish) JavaScript localisation.

= 1.0.2 - 3rd May 2021 =
* Update register_setting to use updated properties.
* Fix translation string replacement.

= 1.0.1 - 30th April 2021 =
* Simplify code for string replacement. Now uses pure PHP functions rather than loops and multiple str_replace. Shorter code and hopefully a little faster.

= 1.0.0 - 30th April 2021 =
* First release
