=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: multilingual, bilingual, translate, translation, language, multilanguage, international, localization
Requires at least: 3.8
Tested up to: 4.2
Stable tag: 1.7.5
License: GPLv2 or later

Making WordPress multilingual

== Description ==

= Features  =

Polylang allows you to create a bilingual or multilingual WordPress site. You write posts, pages and create categories and post tags as usual, and then define the language for each of them. The translation of a post, whether it is in the default language or not, is optional. The translation has to be done by the site editor as Polylang does not integrate any automatic or professional translation service.

* You can use as many languages as you want. RTL language scripts are supported. WordPress languages packs are automatically downloaded and updated.
* You can translate posts, pages, media, categories, post tags, menus, widgets...
* Custom post types, custom taxonomies, sticky posts and post formats, RSS feeds and all default WordPress widgets are supported.
* The language is either set by the content or by the language code in url, or you can use one different subdomain or domain per language
* Categories, post tags as well as some other metas are automatically copied when adding a new post or page translation
* A customizable language switcher is provided as a widget or in the nav menu
* The admin interface is of course multilingual too and each user can set the WordPress admin language in its profile

= Translators =

The plugin admin interface is currently available in 37 languages: English, French, German by [Christian Ries](http://www.singbyfoot.lu), Russian by [yoyurec](http://yoyurec.in.ua) and unostar, Greek by [theodotos](http://www.ubuntucy.org), Dutch by [AlbertGn](http://wordpress.org/support/profile/albertgn), Hebrew by [ArielK](http://www.arielk.net), Polish by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl), [Bartosz](http://www.dfactory.eu/) and Sebastian Janus, Latvian by [@AndyDeGroo](http://twitter.com/AndyDeGroo), Italian by [Luca Barbetti](http://wordpress.org/support/profile/lucabarbetti), Danish by [Compute](http://wordpress.org/support/profile/compute), Spanish by Curro, Portuguese by [Vitor Carvalho](http://vcarvalho.com/), Lithuanian by [Naglis Jonaitis](http://najo.lt/), Turkish by [darchws](http://darch.ws/) and [Abdullah Pazarbasi](http://www.abdullahpazarbasi.com/), Finnish by [Jani Alha](http://www.wysiwyg.fi), Bulgarian by [pavelsof](http://wordpress.org/support/profile/pavelsof), Belarusian by [Alexander Markevitch](http://fourfeathers.by/), Afrikaans by [Kobus Joubert](http://translate3d.com/), Hungarian by Csaba Erdei, Norwegian by [Tom Boersma](http://www.oransje.com/), Slovak by [Branco (WebHostingGeeks.com)](http://webhostinggeeks.com/user-reviews/) and [Maros Kucera](https://maroskucera.com), Swedish by [matsii](http://wordpress.org/support/profile/matsii) and [Jon Täng](http://jontang.se), Catalan by [Núria Martínez Berenguer](http://www.linkedin.com/profile/view?id=127867004&trk=nav_responsive_tab_profile&locale=en_US), Ukrainian by [cmd soft](http://www.cmd-soft.com/), [http://getvoip.com/](http://getvoip.com/) and [Andrii Ryzhkov](https://github.com/andriiryzhkov), Estonian by [Ahto Naris](http://profiles.wordpress.org/ahtonaris/), Venetian by Michele Brunelli, simplified Chinese by [Changmeng Hu](http://www.wpdaxue.com), Indonesian by [ajoull](http://www.ajoull.com/), Arabic by [Anas Sulaiman](http://ahs.pw/), Traditional Chinese by [香腸](http://sofree.cc/), Czech by [Přemysl Karbula](http://www.premyslkarbula.cz), Serbian by Sinisa, Myanmar by Sithu Thwin, Croatian by Bajro, Brazilian Portuguese by [Henrique Vianna](http://henriquevianna.com/), Georgian by [Tours in Georgia](http://www.georgia-tours.eu/)

= Credits =

Most of the flags included with Polylang are coming from [famfamfam](http://famfamfam.com/) and are public domain. Wherever third party code has been used, credit has been given in the code’s comments.

= Do you like Polylang? =

Don't hesitate to [give your feedback](http://wordpress.org/support/view/plugin-reviews/polylang#postform). It will help making the plugin better. Other [contributions](http://polylang.wordpress.com/documentation/contribute/) (such as new translations or helping other users on the support forum) are welcome !

== Installation ==

1. Make sure you are using WordPress 3.8 or later and that your server is running PHP 5.2.4 or later (same requirement as WordPress itself)
1. If you tried other multilingual plugins, deactivate them before activating Polylang, otherwise, you may get unexpected results !
1. Download the plugin
1. Extract all the files.
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to the languages settings page and create the languages you need
1. Add the 'language switcher' widget to let your visitors switch the language.
1. Take care that your theme must come with the corresponding .mo files (Polylang downloads them for themes and plugins bundled with WordPress). If your theme is not internationalized yet, please refer to the [codex](http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers) or ask the theme author to internationalize it.

== Frequently Asked Questions ==

= Where to find help ? =

* Read the [documentation](http://polylang.wordpress.com/documentation/). It includes [guidelines to start working with Polylang](http://polylang.wordpress.com/documentation/setting-up-a-wordpress-multilingual-site-with-polylang/), a [FAQ](http://polylang.wordpress.com/documentation/frequently-asked-questions/) and the [documentation for programmers](http://polylang.wordpress.com/documentation/documentation-for-developers/).
* First time users should read [Polylang - Getting started](http://plugins.svn.wordpress.org/polylang/doc/polylang-getting-started.pdf), a user contributed PDF document which explains the basics with a lot of screenshots.
* Search the [support forum](https://wordpress.org/search/). You will most probably find your answer here.
* Read the sticky posts in the [support forum](http://wordpress.org/support/plugin/polylang).
* If you still have a problem, open a new thread in the [support forum](http://wordpress.org/support/plugin/polylang).

= How to contribute? =

See http://polylang.wordpress.com/documentation/contribute/

== Screenshots ==

1. The Polylang languages admin panel in WordPress 3.8

== Changelog ==

= 1.7.5 (2015-05-11) =

* Add 'pll_languages_list' filter
* fix: warning when a plugin calls 'icl_object_id' with an untranslated post type (seen in ACF 4.4.1)
* fix: the language is not correctly set from the url when using PATHINFO permalinks (introduced in 1.6!)
* fix: notice when a search is filtered by a taxonomy term in a different language

= 1.7.4 (2015-05-03) =

* fix: translated taxonomies and post types from wpml-config.xml are not filtered on frontend (introduced in 1.7.2)
* fix: WPML strings translations not always loaded (introduced in 1.7)
* fix: $.ajaxPrefilter() may not work as expected [props ScreenfeedFr](https://wordpress.org/support/topic/ajaxprefilter-may-not-work-as-expected)
* fix: can't hide the language code for the default language when using subdomains
* fix: incorrect static front page url when hiding the default language information
* fix: an untranslated posts page may display posts in all languages
* fix: javascript error when changing the language of a hierarchical post type from the languages metabox in WP 4.2
* fix: subdomains urls are malformed when the main site uses www.
* fix: suggest tags are not filtered in quick edit
* fix: parent page dropdown list not filtered in quick edit

= 1.7.3 (2015-04-11) =

* the transient 'pll_languages_list' now stores an array of arrays instead of an array of PLL_Language objects
* fix: fatal error for users hosted at GoDaddy (due to PLL_Language objects stored in a transient) 
* fix: additional query vars are removed from home page
* fix: categories are not filtered by the admin language switcher in posts list table (introduced in 1.7)
* fix: when using multiple domains, the domain url is lost when modifying the language slug
* fix: the queried object is incorrectly set for author archives (introduced in 1.6.5)
* fix: notice when a nav menu assigned to a translated nav menu location has been deleted
* fix: no canonical redirection when using pretty permalinks and querying default permalinks

= 1.7.2 (2015-03-23) =

* fix: comments are filtered for posts in a post type not managed by Polylang
* fix: translated static front page don't work when setting PLL_CACHE_HOME_URL to false (introduced in 1.7)
* fix: the query for taxonomies on custom post types is broken (when adding the language code to the url)

= 1.7.1 (2015-03-20) =

* fix: wrong redirection when using a static front page and replacing the page name by the language code (introduced in 1.7)

= 1.7 (2015-03-19) =

* Minimum WordPress version is now v3.8
* Add new languages to the predefined languages list: Swiss German, Hazaragi
* Add compatibility with nested tax queries introduced in WP 4.1
* Add compatibility with splitting shared terms to be introduced in WP 4.2
* Add the possibility to change the domain in the default language when using multiple domains (avoids a conflict with the domain mapping plugin)
* Add the possibility to set the language from the code in url when using default permalinks
* Adding the language code in url is now default at first activation (should improve the out of the box compatibility with other plugins and themes)
* Add new language switcher option to hide a language with no translation
* pll_the_languages() now outputs the js code to handle language change in dropdown list (as done by the widget)
* Improve performance by using base64 encoded flags + various slight optimizations
* Improve protection against chained redirects
* The find posts list is now filtered per media language when clicking on attach link in Media library
* Copy alternative text when creating a media translation 
* The category checklist in quick edit is now filtered per post language instead of admin language filter
* Quick and bulk language edit don't break translations anymore if the new language is free
* Make it impossible to change the language of the default categories
* Make sure that a default category defined in settings->writing is translated in all languages
* Tweak css for mobiles in add and edit term form
* Tweak the query getting the list of available posts in the autocomplete input field in the post languages metabox
* fix: after adding a term translation, need to refresh the page before adding a new term
* fix: term translations rows are not modified in list table when a term is added / deleted or inline edited
* fix: post translations rows are not modified in list table when a post is inline edited
* fix: using brackets in language name breaks strings translations
* fix: quick edit may conflict with other plugins
* fix: impossible to use several dropdown languages widgets
* fix: pll_the_languages() may display a dropdown with empty options
* fix: the categories widget does not work correctly with dropdown
* fix: autosave post always created after manual save
* fix: tax query not filtered by language when using 'NOT IN' operator on a translated taxonomy
* fix: incorrect translation url for searches filtered by taxonomy
* fix: backward incompatibility for edited_term_taxonomy action introduced in WP 4.2
* fix: the home link may be incorrect on MS Windows
* fix: tags in wrong language may be assigned when bulk editing posts in several languages
* fix: tags created when bulk editing posts are not assigned any language
* fix: Illegal string offset 'taxonomy' introduced in v1.6.5
* fix: Undefined property: WP_Query::$queried_object_id when calling pll_the_languages(array('raw' => 1)) in a function hooked to 'wp'. props [KLicheR](https://wordpress.org/support/profile/klicher)
* fix: Notice in admin.php when used with MailPoet plugin

See changelog.txt for older changelog
