=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: multilingual, bilingual, translate, translation, language, multilanguage, i18n, international, l10n, localization
Requires at least: 3.1
Tested up to: 3.6
Stable tag: 1.1.2
License: GPLv2 or later

Polylang adds multilingual content management support to WordPress.

== Description ==

= Features  =

Polylang allows you to create a bilingual or multilingual WordPress site. You write posts, pages and create categories and post tags as usual, and then define the language for each of them. The translation is optional. The plugin does not integrate automatic or professional translation.

* You can have as many languages as you want. RTL languages are supported. WordPress languages files are automatically downloaded and updated.
* You can translate posts, pages, media, categories, post tags, menus, widgets... Custom post types, custom taxonomies, sticky posts and post formats, RSS feeds and all default WordPress widgets are supported.
* Categories, post tags as well as some other metas are automatically copied when adding a new post or page translation
* Support for multisite, pretty permalinks and static page used as front page
* A customizable language switcher is provided as a widget or in the nav menu
* As a bonus, each user can set the WordPress admin language in its profile

= Translators =

The plugin admin interface is currently available in 25 languages: English, French, German contributed by [Christian Ries](http://www.singbyfoot.lu), Russian contributed by [yoyurec](http://yoyurec.in.ua) and unostar, Greek contributed by [theodotos](http://www.ubuntucy.org), Dutch contributed by [AlbertGn](http://wordpress.org/support/profile/albertgn), Hebrew contributed by [ArielK](http://www.arielk.net), Polish contributed by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl), Latvian contributed by [@AndyDeGroo](http://twitter.com/AndyDeGroo), Italian contributed by [Luca Barbetti](http://wordpress.org/support/profile/lucabarbetti), Danish contributed by [Compute](http://wordpress.org/support/profile/compute), Spanish contributed by Curro, Portuguese contributed by [Vitor Carvalho](http://vcarvalho.com/), Lithuanian contributed by [Naglis Jonaitis](http://najo.lt/), Turkish contributed by [darchws](http://darch.ws/), Finnish contributed by [Jani Alha](http://www.wysiwyg.fi), Bulgarian contributed by [pavelsof](http://wordpress.org/support/profile/pavelsof), Belarusian contributed by [Alexander Markevitch](http://fourfeathers.by/), Afrikaans contributed by [Kobus Joubert](http://translate3d.com/), Hungarian contributed by Csaba Erdei, Norwegian contributed by [Tom Boersma](http://www.oransje.com/), Slovak contributed by [Branco (WebHostingGeeks.com)](http://webhostinggeeks.com/user-reviews/), Swedish contributed by [matsii](http://wordpress.org/support/profile/matsii), Catalan contributed by [Núria Martínez Berenguer](http://nuriamb.capa.webfactional.com), Ukrainian contributed by [cmd soft](http://www.cmd-soft.com/)


Other [contributions](http://polylang.wordpress.com/documentation/contribute/) are welcome !

= Feedback or ideas =

Don't hesitate to [give your feedback](http://wordpress.org/support/plugin/polylang). It will help making the plugin better. Don't hesitate to rate the plugin too.

== Installation ==

1. Make sure you are using WordPress 3.1 or later and that your server is running PHP5 (if you are using WordPress 3.2 or newer, it does !)
1. If you tried other multilingual plugins, deactivate them before activating Polylang, otherwise, you may get unexpected results !
1. Download the plugin
1. Extract all the files.
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to the languages settings page and create the languages you need
1. Add the 'language switcher' widget to let your visitors switch the language.
1. Take care that your theme must come with the corresponding .mo files (Polylang downloads them for Twenty Ten and Twenty Eleven). If your theme is not internationalized yet, please refer to the [codex](http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers) or ask the theme author to internationalize it.

== Frequently Asked Questions ==

= Where to find help ? =

* Read the [documentation](http://polylang.wordpress.com/documentation/). It includes [guidelines to start working with Polylang](http://polylang.wordpress.com/documentation/setting-up-a-wordpress-multilingual-site-with-polylang/), a [FAQ](http://polylang.wordpress.com/documentation/frequently-asked-questions/) and the [documentation for programmers](http://polylang.wordpress.com/documentation/documentation-for-developers/).
* Search the [support forum](http://wordpress.org/support/plugin/polylang). I know that searching in the WordPress forum is not very convenient, but please give it a try. You can use generic search engines such as Google too as the WordPress forum SEO is very good. You will most probably find your answer here.
* If you still have a problem, open a new thread in the [support forum](http://wordpress.org/support/plugin/polylang).

== Screenshots ==

1. The Polylang languages admin panel in WordPress 3.3.1

== Contribute ==

If you wonder how you can help Polylang, here are some ideas. As you will read, there is no need to be a PHP developper!

= Help other users of Polylang =

The [support forum](http://wordpress.org/support/plugin/polylang) is here so that users of the plugin can get help when they need it. However, I will not always be available to answer questions. You don't need to be a developer to help out. Very often similar questions have been answered in the past.

= Report bugs =

Don't hesitate to report bugs on the [support forum](http://wordpress.org/support/plugin/polylang).

= Test new versions =

You can subscribe to the [blog](http://polylang.wordpress.com/) that I use to announce development versions and then, test the new versions and report bugs before the final release. It helps a lot!

= Translate the admin interface =

Polylang is already available in 25 languages. It's very easy to add a new one! Download [poedit](http://www.poedit.net/download.php) (available for Windows, Mac OS X and Linux). Rename the file polylang.pot found in the polylang/languages directory into something like polylang-your_locale.po. Open the file with poedit and start translating (keeping strange codes such as %s, %1$s as is). Once done, just save and you will get two files polylang-your_locale.po and polylang-your_locale.mo that you can send to the author. The translation will be included with the next release and will be covered by the same license as Polylang (GPLv2 or later).

= Communicate =

If you like Polylang, you can spread the word... Rating the plugin is very easy, you can write some words about the plugin, make a link to the plugin page...

= What else ? =

Every suggestions are welcome.

== Upgrade Notice ==

= 1.0 =
If you are using a version older than 0.8, please ugrade to 0.9.8 before ugrading to 1.0 or later

== Changelog ==

= 1.1.2 (2013-06-18) =

* Posts and terms now inherit parent's language if created outside the standard WordPress ui
* Improve the compatibility with the plugins Types and The Events Calendar, and again with WordPress SEO
* Improve performance
* Improve html validation
* Add 'raw' argument to 'pll_the_languages'
* Add the filter 'pll_translation_url'
* Bug correction: no language is set for a (translated custom taxonomy) term when added from a (non tranlated) custom post type edit page
* Bug correction: warning if 'get_terms' is called with a non-array 'include' argument (introduced in 1.1.1)
* Bug correction: warning if the menu language switcher has nothing to display

= 1.1.1 (2013-05-20) =

* Move nav menu language switcher split from 'wp_nav_menu_objects' to  'wp_get_nav_menu_items' filter
* Add the filter 'pll_redirect_home'
* Automatically translate ids in 'include' argument of 'get_terms' (useful for the menus in the Suffusion theme)
* Add compatibility with Jetpack infinite scroll
* Bug correction: rtl text direction not set when adding the language code to all urls (introduced in 1.1)
* Bug correction: hide again navigation panel in theme customizer as it still doesn't work
* Bug correction: is_home not set on translated page when searching an empty string
* Bug correction: fatal error when creating a post or term from frontend (introduced in 1.1)
* Bug correction: attachments may load a wrong language when media translation was enabled then disabled
* Bug correction: warning when querying posts before the action 'wp_loaded' has been fired (in auto-translate.php)
* Bug correction: potential issue if other plugins use the filter 'get_nav_menu'
* Bug correction: interference between language inline edit and search in admin list tables
* Bug correction: auto-translate breaks queries tax_query when the 'field' is set to 'id'
* Bug correction: search is not filtered by language for default permalinks (introduced in 1.1)
* Tests done with WP 3.6 beta 3 and Twenty thirteen

= 1.1 (2013-05-10) =

* When adding the language to all urls, the language is now defined in (plugins_loaded, 1) for better compatibility with some plugins (WordPress SEO)
* When querying posts and terms, ids are now automatically translated
* Add the possibility to group string translations
* Add the possibility to delete strings registered with 'icl_register_string'
* Move the option 'polylang_widgets' in general polylang options
* Better integration of the multilingual nav menus (everything is now integrated in the menus page of WordPress
* The language switcher is now a menu item which can be placed everywhere in a nav menu
* Posts or terms created from frontend are now assigned the current language (or another one if specified in the variable 'lang')
* Bug correction: continents-cities-xx_XX.mo not downloaded
* Bug correction: a gzipped 404 page is downloaded when a mo file does not exist on WordPress languages files repository
* Bug correction: post_date_gmt not synchronized together with post_date
* Tests done with WP 3.6 beta 2 and Twenty thirteen

See changelog.txt for full changelog
