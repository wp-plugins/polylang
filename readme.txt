=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: multilingual, bilingual, translate, translation, language, multilanguage, international, localization
Requires at least: 3.8
Tested up to: 4.1
Stable tag: 1.6.5
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

The plugin admin interface is currently available in 37 languages: English, French, German by [Christian Ries](http://www.singbyfoot.lu), Russian by [yoyurec](http://yoyurec.in.ua) and unostar, Greek by [theodotos](http://www.ubuntucy.org), Dutch by [AlbertGn](http://wordpress.org/support/profile/albertgn), Hebrew by [ArielK](http://www.arielk.net), Polish by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl), [Bartosz](http://www.dfactory.eu/) and Sebastian Janus, Latvian by [@AndyDeGroo](http://twitter.com/AndyDeGroo), Italian by [Luca Barbetti](http://wordpress.org/support/profile/lucabarbetti), Danish by [Compute](http://wordpress.org/support/profile/compute), Spanish by Curro, Portuguese by [Vitor Carvalho](http://vcarvalho.com/), Lithuanian by [Naglis Jonaitis](http://najo.lt/), Turkish by [darchws](http://darch.ws/) and [Abdullah Pazarbasi](http://www.abdullahpazarbasi.com/), Finnish by [Jani Alha](http://www.wysiwyg.fi), Bulgarian by [pavelsof](http://wordpress.org/support/profile/pavelsof), Belarusian by [Alexander Markevitch](http://fourfeathers.by/), Afrikaans by [Kobus Joubert](http://translate3d.com/), Hungarian by Csaba Erdei, Norwegian by [Tom Boersma](http://www.oransje.com/), Slovak by [Branco (WebHostingGeeks.com)](http://webhostinggeeks.com/user-reviews/), Swedish by [matsii](http://wordpress.org/support/profile/matsii) and [Jon Täng](http://jontang.se), Catalan by [Núria Martínez Berenguer](http://www.linkedin.com/profile/view?id=127867004&trk=nav_responsive_tab_profile&locale=en_US), Ukrainian by [cmd soft](http://www.cmd-soft.com/), [http://getvoip.com/](http://getvoip.com/) and [Andrii Ryzhkov](https://github.com/andriiryzhkov), Estonian by [Ahto Naris](http://profiles.wordpress.org/ahtonaris/), Venetian by Michele Brunelli, simplified Chinese by [Changmeng Hu](http://www.wpdaxue.com), Indonesian by [ajoull](http://www.ajoull.com/), Arabic by [Anas Sulaiman](http://ahs.pw/), Traditional Chinese by [香腸](http://sofree.cc/), Czech by [Přemysl Karbula](http://www.premyslkarbula.cz), Serbian by Sinisa, Myanmar by Sithu Thwin, Croatian by Bajro, Brazilian Portuguese by [Henrique Vianna](http://henriquevianna.com/), Georgian by [Tours in Georgia](http://www.georgia-tours.eu/)
= Credits =

Most of the flags included with Polylang are coming from [famfamfam](http://famfamfam.com/) and are public domain. Icons are coming from [Icomoon](http://icomoon.io/) and are licensed under GPL. Wherever third party code has been used, credit has been given in the code’s comments.

= Do you like Polylang? =

Don't hesitate to [give your feedback](http://wordpress.org/support/view/plugin-reviews/polylang#postform). It will help making the plugin better. Other [contributions](http://polylang.wordpress.com/documentation/contribute/) (such as new translations or helping other users on the support forum) are welcome !

== Installation ==

1. Make sure you are using WordPress 3.5 or later and that your server is running PHP 5.2.4 or later (same requirement as WordPress itself)
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
* Search the [support forum](http://wordpress.org/support/plugin/polylang). I know that searching in the WordPress forum is not very convenient, but please give it a try. You can use generic search engines such as Google too as the WordPress forum SEO is very good. You will most probably find your answer here.
* If you still have a problem, open a new thread in the [support forum](http://wordpress.org/support/plugin/polylang).

= How to contribute? =

See http://polylang.wordpress.com/documentation/contribute/

== Screenshots ==

1. The Polylang languages admin panel in WordPress 3.8

== Changelog ==

= 1.7 =

* Minimum WordPress version is now v3.8
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
* fix: after adding a term translation, need to refresh the page before adding a new term
* fix: term translations rows are not modified in list table when a term is added / deleted or inline edited
* fix: post translations rows are not modified in list table when a post is inline edited
* fix: using brackets in language name breaks strings translations
* fix: quick edit may conflict with other plugins
* fix: impossible to use several dropdown languages widgets
* fix: the categories widget does not work correctly with dropdown
* fix: autosave post always created after manual save
* fix: tax query not filtered by language when using 'NOT IN' operator on a translated taxonomy
* fix: incorrect translation url for searches filtered by taxonomy

= 1.6.6

* fix: Illegal string offset 'taxonomy' introduced in v1.6.5
* fix: Undefined property: WP_Query::$queried_object_id when calling pll_the_languages(array('raw' => 1)) in a function hooked to 'wp'. props [KLicheR](https://wordpress.org/support/profile/klicher)

= 1.6.5 (2015-02-18) =

* Add new correspondances between WordPress locales and Facebook locales (for WPSEO and Jetpack users)
* fix: quick draft posts are always assigned the default category in the default language
* fix: Notice: Undefined offset: 0 in wp-includes/query.php introduced in WP 4.1
* fix: is_tax and is_archive are not correctly set when a custom taxonomy term is queried
* fix: conflict introduced by WPSEO 1.7.2+

= 1.6.4 (2015-02-01) =

* Add es_MX to predefined languages list
* Add compatibility with WordPress SEO sitemaps for multiple domains and subdomains
* fix: a new post is assigned the wrong (untranslated) default category if no category is assigned by the user
* fix: the home links now have the right scheme even if PLL_CACHE_HOME_URL is not set to false
* fix: fatal error when using old versions of WPSEO (I should do what I tell other to do!)
* fix: strings translations are not switched when using switch_to_blog

= 1.6.3 (2015-01-09) =

* Add Georgian translation contributed by [Tours in Georgia](http://www.georgia-tours.eu/)
* fix: WXR export does not include the language of untranslated terms (will now work only for newly saved terms)
* fix: better cleaning of DB when translated objects are deleted
* fix: incorrect (ajax) translations links when modifying a term language
* fix: warning: Illegal string offset 'taxonomy' introduced by the combination of WP 4.1 and some plugins.

= 1.6.2 (2014-12-14) =

* fix: bugs and inconsistencies compared to WPML in 'icl_get_languages' (should fix a conflict with Avada)
* fix: https issue
* fix: stop displaying an error when adding en_US as new language (translation not downloaded)
* fix: infinite redirect loop on (unattached) attachment links
* fix: impossible to add tags in post quick edit (introduced in 1.5)
* fix: the customizer does not land to the right page when cumulating: static front page + page name in url + default language code not hidden
* fix: read parent theme wpml-config.xml before child theme
* fix: add protection to avoid empty language
* fix: page preview link again

= 1.6.1 (2014-11-19) =

* Add Brazilian Portuguese translation contributed by [Henrique Vianna](http://henriquevianna.com/)
* Improve compatibility with Types: allow custom fields to be populated when creating a new translation
* Make it impossible to remove the translations of the default category
* Fix: possibility to add a path when using multiple domains (same path for all languages) broken since v1.5.6
* Fix: preview link for non default language when using multiple domains
* Fix: error displayed when setting the static front page and only one language has been defined
* Fix: revert changes on rewrite rules with front introduced in 1.6
* Fix: conflict with WordPress SEO when no language has been created

= 1.6 (2014-10-27) =

* Add Croatian translation contributed by Bajro
* Add new languages to predefined languages list: Azerbaijani, English (Australia), English (UK), Basque
* Add flag in front of the language select dropdown for posts and terms
* Add widget text translation
* Add opengraph support for locale and translations when WordPress SEO or Jetpack are activated
* Add error message if attempting to assign an untranslated page as static front page
* Add 'pll_sanitize_string_translation' filter to sanitize registered strings translations when saved
* Fix: change the en_US flag to US flag. The UK flag is now associated to en_GB
* Fix: change Belarussian locale from be_BY to bel to in agreement with translate.wordpress.org
* Fix home pages duplicate urls when using domains or subdomains
* Fix rewrite rules with front
* Fix: terms are always in default language when created from post bulk edit

See changelog.txt for older changelog
