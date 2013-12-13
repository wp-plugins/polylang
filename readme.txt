=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: multilingual, bilingual, translate, translation, language, multilanguage, international, localization
Requires at least: 3.1
Tested up to: 3.8
Stable tag: 1.3.1
License: GPLv2 or later

Polylang adds multilingual content management support to WordPress.

== Description ==

= Features  =

Polylang allows you to create a bilingual or multilingual WordPress site. You write posts, pages and create categories and post tags as usual, and then define the language for each of them. The translation of a post, whether it is in the default language or not, is optional. The translation has to be done by the site editor as Polylang does not integrate any automatic or professional translation service.

* You can use as many languages as you want. RTL language scripts are supported. WordPress languages files are automatically downloaded and updated.
* You can translate posts, pages, media, categories, post tags, menus, widgets... Custom post types, custom taxonomies, sticky posts and post formats, RSS feeds and all default WordPress widgets are supported.
* The language is either set by the content or by the language code in url (either directory or subdomain), or you can use one different domain per language
* Categories, post tags as well as some other metas are automatically copied when adding a new post or page translation
* A customizable language switcher is provided as a widget or in the nav menu
* The admin interface is of course multilingual too and each user can set the WordPress admin language in its profile

= Translators =

The plugin admin interface is currently available in 30 languages: English, French, German by [Christian Ries](http://www.singbyfoot.lu), Russian by [yoyurec](http://yoyurec.in.ua) and unostar, Greek by [theodotos](http://www.ubuntucy.org), Dutch by [AlbertGn](http://wordpress.org/support/profile/albertgn), Hebrew by [ArielK](http://www.arielk.net), Polish by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl) and [Bartosz](http://www.dfactory.eu/), Latvian by [@AndyDeGroo](http://twitter.com/AndyDeGroo), Italian by [Luca Barbetti](http://wordpress.org/support/profile/lucabarbetti), Danish by [Compute](http://wordpress.org/support/profile/compute), Spanish by Curro, Portuguese by [Vitor Carvalho](http://vcarvalho.com/), Lithuanian by [Naglis Jonaitis](http://najo.lt/), Turkish by [darchws](http://darch.ws/), Finnish by [Jani Alha](http://www.wysiwyg.fi), Bulgarian by [pavelsof](http://wordpress.org/support/profile/pavelsof), Belarusian by [Alexander Markevitch](http://fourfeathers.by/), Afrikaans by [Kobus Joubert](http://translate3d.com/), Hungarian by Csaba Erdei, Norwegian by [Tom Boersma](http://www.oransje.com/), Slovak by [Branco (WebHostingGeeks.com)](http://webhostinggeeks.com/user-reviews/), Swedish by [matsii](http://wordpress.org/support/profile/matsii) and [Jon Täng](http://jontang.se), Catalan by [Núria Martínez Berenguer](http://www.linkedin.com/profile/view?id=127867004&trk=nav_responsive_tab_profile&locale=en_US), Ukrainian by [cmd soft](http://www.cmd-soft.com/), Estonian by [Ahto Naris](http://profiles.wordpress.org/ahtonaris/), Venetian by Michele Brunelli, simplified Chinese by [Changmeng Hu](http://www.wpdaxue.com), Indonesian by [ajoull](http://www.ajoull.com/), Arabic by [Anas Sulaiman](http://ahs.pw/)

= Credits =

Most of the flags included with Polylang are coming from [famfamfam](http://famfamfam.com/) and are public domain. Icons are coming from [Icomoon](http://icomoon.io/) and are licensed under GPL. Wherever third party code has been used, credit has been given in the code’s comments.

= Do you like Polylang? =

Don't hesitate to [give your feedback](http://wordpress.org/support/view/plugin-reviews/polylang#postform). It will help making the plugin better. Other [contributions](http://polylang.wordpress.com/documentation/contribute/) (such as new translations or helping other users on the support forum) are welcome !

== Installation ==

1. Make sure you are using WordPress 3.1 or later and that your server is running PHP5 (if you are using WordPress 3.2 or newer, it does !)
1. If you tried other multilingual plugins, deactivate them before activating Polylang, otherwise, you may get unexpected results !
1. Download the plugin
1. Extract all the files.
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to the languages settings page and create the languages you need
1. Add the 'language switcher' widget to let your visitors switch the language.
1. Take care that your theme must come with the corresponding .mo files (Polylang downloads them for themes bundled with WordPress). If your theme is not internationalized yet, please refer to the [codex](http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers) or ask the theme author to internationalize it.

== Frequently Asked Questions ==

= Where to find help ? =

* Read the [documentation](http://polylang.wordpress.com/documentation/). It includes [guidelines to start working with Polylang](http://polylang.wordpress.com/documentation/setting-up-a-wordpress-multilingual-site-with-polylang/), a [FAQ](http://polylang.wordpress.com/documentation/frequently-asked-questions/) and the [documentation for programmers](http://polylang.wordpress.com/documentation/documentation-for-developers/).
* Search the [support forum](http://wordpress.org/support/plugin/polylang). I know that searching in the WordPress forum is not very convenient, but please give it a try. You can use generic search engines such as Google too as the WordPress forum SEO is very good. You will most probably find your answer here.
* If you still have a problem, open a new thread in the [support forum](http://wordpress.org/support/plugin/polylang).

= How to contribute? =

See http://polylang.wordpress.com/documentation/contribute/

== Screenshots ==

1. The Polylang languages admin panel in WordPress 3.3.1

== Upgrade Notice ==

= 1.3.1 =
Polylang 1.2 introduced major internal changes. More than ever, make a database backup before upgrading from 1.1.6 or older! If you are using a version older than 0.8, please ugrade to 0.9.8 before ugrading to 1.3

== Changelog ==

= 1.3.1 (2013-12-13) =

* Bug correction: fatal error on settings page if a static front page without language is set
* Bug correction: wrong home url when using different domains per language

= 1.3 (2013-12-11) =

* Refresh admin UI for better look in WP 3.8 and more dynamic comportment
* The "Detect browser language" option does now also controls returning visits (based on cookie).
* Improved performance by optimizing some queries for WP 3.5+
* The user biography in default language is now stored in default WordPress usermeta
* Add language parameter in API function pll_home_url and allow to call it on admin side
* Calling 'get_terms' with the 'lang' parameter now uses a cache object per language
* Bug correction: conflict with unstranslated taxonomies
* Bug correction: possible malformed translation archive url in language switcher
* Bug correction: a wrong language may be displayed in quick edit dropdown
* Bug correction: it is possible to add multiple translations (in the same language) for a single taxonomy term
* Bug correction: non public post types and taxonomies are visible in Polylang settings
* Bug correction: the language is always chosen from cookie (or browser preferences) in some installations
* Bug correction: Firefox language preference is not recognized when comparison is made on locale (instead of ISO 639-1 language code)
* Bug correction: incorrect tax_query in PLL_Auto_Translate

See changelog.txt for older changelog
