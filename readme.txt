=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: multilingual, bilingual, translate, translation, language, multilanguage, international, localization
Requires at least: 3.1
Tested up to: 3.7.1
Stable tag: 1.2.3
License: GPLv2 or later

Polylang adds multilingual content management support to WordPress.

== Description ==

Polylang 1.2 introduces major internal changes. More than ever, make a database backup if you ugrade from 1.1.6 or older.

= Features  =

Polylang allows you to create a bilingual or multilingual WordPress site. You write posts, pages and create categories and post tags as usual, and then define the language for each of them. The translation of a post, whether it is in the default language or not, is optional. The translation has to be done by the site editor as Polylang not integrate any automatic or professional translation service.

* You can use as many languages as you want. RTL language scripts are supported. WordPress languages files are automatically downloaded and updated.
* You can translate posts, pages, media, categories, post tags, menus, widgets... Custom post types, custom taxonomies, sticky posts and post formats, RSS feeds and all default WordPress widgets are supported.
* The language is either set by the content or by the language code in url (either directory or subdomain), or you can use one different domain per language
* Categories, post tags as well as some other metas are automatically copied when adding a new post or page translation
* A customizable language switcher is provided as a widget or in the nav menu
* The admin interface is of course multilingual too and each user can set the WordPress admin language in its profile

= Translators =

The plugin admin interface is currently available in 30 languages: English, French, German by [Christian Ries](http://www.singbyfoot.lu), Russian by [yoyurec](http://yoyurec.in.ua) and unostar, Greek by [theodotos](http://www.ubuntucy.org), Dutch by [AlbertGn](http://wordpress.org/support/profile/albertgn), Hebrew by [ArielK](http://www.arielk.net), Polish by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl) and [Bartosz](http://www.dfactory.eu/), Latvian by [@AndyDeGroo](http://twitter.com/AndyDeGroo), Italian by [Luca Barbetti](http://wordpress.org/support/profile/lucabarbetti), Danish by [Compute](http://wordpress.org/support/profile/compute), Spanish by Curro, Portuguese by [Vitor Carvalho](http://vcarvalho.com/), Lithuanian by [Naglis Jonaitis](http://najo.lt/), Turkish by [darchws](http://darch.ws/), Finnish by [Jani Alha](http://www.wysiwyg.fi), Bulgarian by [pavelsof](http://wordpress.org/support/profile/pavelsof), Belarusian by [Alexander Markevitch](http://fourfeathers.by/), Afrikaans by [Kobus Joubert](http://translate3d.com/), Hungarian by Csaba Erdei, Norwegian by [Tom Boersma](http://www.oransje.com/), Slovak by [Branco (WebHostingGeeks.com)](http://webhostinggeeks.com/user-reviews/), Swedish by [matsii](http://wordpress.org/support/profile/matsii), Catalan by [Núria Martínez Berenguer](http://www.linkedin.com/profile/view?id=127867004&trk=nav_responsive_tab_profile&locale=en_US), Ukrainian by [cmd soft](http://www.cmd-soft.com/), Estonian by [Ahto Naris](http://profiles.wordpress.org/ahtonaris/), Venetian by Michele Brunelli, simplified Chinese by [Changmeng Hu](http://www.wpdaxue.com), Indonesian by [ajoull](http://www.ajoull.com/), Arabic by [Anas Sulaiman](http://ahs.pw/)


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

= 1.2.3 =
Polylang 1.2 introduced major internal changes. More than ever, make a database backup before upgrading from 1.1.6 or older! If you are using a version older than 0.8, please ugrade to 0.9.8 before ugrading to 1.2.3

== Changelog ==

= 1.2.3 (2013-11-17) =

* Avoid fatal error when ugrading with Nextgen Gallery active
* Bug correction: menus locations of non default language are lost at theme deactivation
* Bug correction: impossible to set menus locations of non default language in some specific cases
* Bug correction: bbpress admin is broken

= 1.2.2 (2013-11-14) =

* Updated Polish translation thanks to [Bartosz](http://www.dfactory.eu/)
* Delay strings translations upgrade from 'wp_loaded' to 'admin_init' to avoid fatal error when wp-ecommerce is active
* Remove Jetpack infinite scroll compatibility code as it seems useless with new Polylang 1.2 code structure
* Bug correction: fatal error when doing ajax on frontend
* Bug correction: ICL_LANGUAGE_CODE incorrectly defined when doing ajax on frontend
* Bug correction: ['current_lang'] and ['no-translation'] indexes disappeared from pll_the_languages raw output
* Bug correction: invalid argument supplied for foreach() in /polylang/include/mo.php on line 57
* Bug correction: cookie may not be correctly set
* Bug correction: languages columns may not be displayed in custom post types and custom taxonomies tables

= 1.2.1 (2013-11-11) =

* Update badly encoded Latvian translation
* Suppress one query in PLL_WPML_Config when not in multisite
* Bug correction: strings translations are not correctly upgraded
* Bug correction: nav menus locations are not correctly upgraded for non default language

= 1.2 (2013-11-10) =

This version does include important changes in database. More than ever, make a database backup before upgrading

* Add Arabic translation contributed by [Anas Sulaiman](http://ahs.pw/)
* Major rewrite with new structure
* Change the language and translations model from meta to taxonomy (no extra termmeta table created anymore)
* Move the strings translations from option to a custom post type
* Add support for language code in subdomain and for one different domain per language (experimental)
* Add support of wordpress importer plugin. Export must have been done with Polylang 1.2+ (experimental)
* Add support for theme navigation customizer (was de-activated by Polylang since WP 3.4)
* Request confirmation for deleting a language
* Better management of default category for each language
* Now check if date and post type archives are translated before displaying the language switcher
* Update management of the 'copy' action of the custom fields section in wpml-config.xml
* Add support for ICL_LANGUAGE_CODE and ICL_LANGUAGE_NAME of the WPML API on admin side
* Add support of WPSEO custom strings translations when the language is set from content
* Modify admin language filter for valid html and better visibility
* Synchronization is now disabled by default (due to too much conflicts / questions on the forum)
* Include rel="alternate" hreflang="x" selflink per google recommendation
* Improve inline documentation
* Bug correction: wrong datatype for second argument in polylang/include/auto-translate.php (introduced in 1.1.6)
* Bug correction: same id is used for all language items in menu
* Bug correction: wpml-config.xml file not loaded for sitewide active plugins on network installations
* Bug correction: page parent dropdown list (in page attributes metabox) not correctly displayed when switching from a language with empty list

See changelog.txt for full changelog
