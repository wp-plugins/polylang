=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: bilingual, language, i18n, international, l10n, localization, multilanguage, multilingual, multisite, translate, translation
Requires at least: 3.1
Tested up to: 3.3.1
Stable tag: 0.7.2

Polylang adds multilingual content management support to WordPress.

== Description ==

= Upgrade Notice =

When upgrading from 0.5.1 or older, your custom flags in 'wp-content/plugins/polylang/local_flags' directory should move to 'wp-content/polylang'. People using the function 'pll_the_language' should be aware that it does not display the 'ul' tag anymore. I wrote about the reasons for these changes in the [forum](http://wordpress.org/support/topic/development-of-polylang-version-06). When upgrading from 0.6.1 or older, people using RTL languages must edit these languages and set the text direction to RTL in order for Polylang to work properly (the RTL property of the language is not set automatically when upgrading).

= Features  =

You write posts, pages and create categories and post tags as usual, and then define the language for each of them. The translation is optional. The plugin does not integrate automatic or professional translation.

* You can have as many languages as you want. RTL languages are now supported. WordPress languages files are automatically downloaded and updated.
* You can translate posts, pages, categories, post tags, menus, widgets... Custom post types, custom taxonomies and post formats, RSS feeds and all default WordPress widgets are supported.
* Categories, post tags as well as some other metas are automatically copied when adding a new post or page translation
* Support for Search form (see the FAQ in the documentation)
* Support for multisite, pretty permalinks and static page used as front page
* A language switcher is provided as a widget or in the nav menu
* As a bonus, each user can set the WordPress admin language in its profile

Unlike some other similar plugins, if you deactivate Polylang, your blog will go on working as smoothly as possible. All your posts, pages, category and post tags would be accessible - without language filter of course - provided that you do not check the option: "Add language information to all URL including posts, pages, categories and post tags".

The plugin admin interface is currently available in:

* English
* French
* German contributed by [Christian Ries](http://www.singbyfoot.lu)
* Russian contributed by [yoyurec](http://yoyurec.in.ua)
* Greek contributed by [theodotos](http://www.ubuntucy.org)
* Dutch contributed by [AlbertGn](http://wordpress.org/support/profile/albertgn)
* Hebrew contributed by [ArielK](http://www.arielk.net)
* Polish contributed by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl)

Other translators are welcome !

= Feedback or ideas =

Don't hesitate to [give your feedback](http://wordpress.org/tags/polylang?forum_id=10). It will help making the plugin better. Don't hesitate to rate the plugin too.

== Upgrade Notice ==

When upgrading from 0.5.1 or older, your custom flags in 'wp-content/plugins/polylang/local_flags' directory should move to 'wp-content/polylang'. People using the function 'pll_the_language' should be aware that it does not display the ul tag anymore. When upgrading from 0.6.1 or older, people using RTL languages must edit these languages and set the text direction to RTL in order for Polylang to work properly (the RTL property of the language is not set automatically when upgrading).

== Installation ==

1. Make sure you are using WordPress 3.1 or later and that your server is running PHP5 (if you are using WordPress 3.2 or newer, it does !)
1. If you tried other multilingual plugins, deactivate them before activating Polylang, otherwise, you may get unexpected results !
1. Download the plugin
1. Extract all the files.
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to the languages settings page and create the languages you need
1. Add the 'language switcher' Widget to let your visitors switch the language.
1. Take care that your theme must come with the corresponding .mo files (Polylang downloads them for Twenty Ten and Twenty Eleven). If your theme is not internationalized yet, please refer to the [codex](http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers) or ask the theme author to internationalize it.

== Frequently Asked Questions ==

= Where to find help ? =

* Read the [documentation](http://plugins.svn.wordpress.org/polylang/trunk/doc/documentation-en.pdf) supplied whith the plugin (in the doc directory). It includes guidelines to start working with Polylang, a much bigger FAQ than here and the API documentation for programmers.
* Search the [support forum](http://wordpress.org/tags/polylang?forum_id=10). I know that searching in the WordPress forum is not very convenient, but please give it a try. You can use generic search engines such as Google too as the WordPress forum SEO is very good. You will most probably find your answer here.
* If you still have a problem, open a new thread in the [support forum](http://wordpress.org/tags/polylang?forum_id=10).

= Is Polylang compatible with multisite ? =

Yes. Since v0.5. You can either activate it at network level or at site level.

= Can I use my own flags for the language switcher ? =

Yes. You have to use PNG or JPG files and name them with the WordPress locale corresponding to the language. For example, en_US.png for English. Then upload these files in the `wp-content/polylang` directory. Don't use the `/polylang/flags` directory as your files would be removed when updating the plugin.

== Screenshots ==

1. The Polylang languages admin panel in WordPress 3.3.1

== Changelog ==

= 0.7.2 (2012-02-15) =

* Add Polish translation contributed by [Peter Paciorkiewicz](http://www.paciorkiewicz.pl)
* Add 5 new languages to predefined list
* completely reworked rewrite rules
* Bug correction: custom nav menus do not work in Artisteer generated themes
* Bug correction: having a single language causes multiple warnings while saving post/page.
* Bug correction: custom nav menu broken on archives pages
* Bug correction: the language switcher does not link to translated post type archive when using pretty permalinks
* Bug correction: the tags are not saved in the right language when translated tags have the same name
* Bug correction: bad link in post preview when adding language code to all urls
* Bug correction: feed not filtered by language when adding language code to all urls
* Bug correction: duplicate canonical link when used together with WordPress SEO by Yoast
* Bug correction: the all posts admin page is messed if another plugin adds a column
* Bug correction: 404 error on static front page when adding language code to all urls (including default language)

= 0.7.1 (2012-02-06) = 

* Allow using 3 characters languages codes (ISO 639-2 or 639-3)
* The predefined languages dropdown list now displays the locale to help differentiate some languages
* Add 5 new languages to predefined list
* Bug correction: the filter 'pll_copy_post_metas' does not work
* Bug correction: impossible to add a tag in the edit post panel
* Bug correction: rewrite rules not correct
* Bug correction: cache issue with css and js files

= 0.7 (2012-01-30) =

* Add Hebrew translation contributed by [ArielK](http://www.arielk.net)
* Add support for RTL languages for both frontend and admin
* Twenty Ten and Twenty Eleven languages files are now automatically downloaded when creating a new langage
* Improve filtering tags by language in the edit post panel
* Category parent dropdown list is now filtered by language
* Category parents are now synchronized between translations
* Add the possibility to have the language information in all URL
* Add support for post formats
* Add option allowing not to show the current language in the language switcher (for both menu and widget)
* Add a title attribute (and the possibility to personalize it with a filter) to flags
* pll_get_post and pll_get_term second parameter is now optional and defaults to current language
* Add pll_the_language_link filter allowing to filter translation links outputed by the language switcher
* The option PLL_DISPLAY_ALL is no longer supported
* Bug correction: Autosave reset to default language
* Bug correction: blog info not translated in feeds
* Bug correction: post comments feed always in default language
* Bug correction: undefined index notice when setting up a custom menu widget
* Bug correction: rewrite rules are not correctly reset when deactivating the plugin
* Bug correction: is_home not correctly set on pages 2, 3...
* Bug correction: avoid naming conflicts (in sql queries) with other themes / plugins
* Bug correction: bad language detection and url rewriting of custom post types archives

= 0.6.1 (2012-01-12) =

* Add Dutch translation contributed by [AlbertGn](http://wordpress.org/support/profile/albertgn)
* Disable everything except the languages management panel while no language has been created
* Bug correction: can't have the same featured image in translated posts
* Bug correction: parent page dropdown does appear only after the page has been saved
* Bug correction: archives widget not working anymore
* Bug correction: string translations does not work for WP < 3.3
* Bug correction: fix fatal error in string translations caused by widgets using the old API
* Bug correction: the strings translation panel is unable to translate strings with special characters
* Bug correction: Polylang "is_front_page" returns true on archives pages

= 0.6 (2012-01-07) =

* Add Greek translation contributed by [theodotos](http://www.ubuntucy.org)
* WordPress languages files are now automatically downloaded when creating a new langage (and updated when updating WordPress)
* Add the possibility to change the order of the languages in the language switcher
* Add the possibility to translate the site title, tagline and widgets titles
* Categories, post tags, featured image, page parent, page template and menu order are now copied when adding a new translation
* Translations are now accessibles in the "Posts", "Pages", "Categories" and "Post tags" admin panels
* Improve the dropdown language switcher widget (sends now to translated page or home page based on options)
* Move custom flags from polylang/local_flags to wp_content/polylang
* Add two options to "pll_the_languages" ('hide_if_no_translation' and 'hide_current'). *The function does not output ul tag anymore*
* Improve API
* Bug correction: Twenty eleven custom Header problem with v0.5.1
* Bug correction: front-page.php not loaded for translated front page

= 0.5.1 (2011-12-18) =

* Improved German translation contributed by [Christian Ries](http://www.singbyfoot.lu)
* Bug correction: translated homepage not recognized as home page when it displays posts
* Bug correction: predefined language list does not work on IE8
* Bug correction: on some installations, "Add New" post doesn't keep intended language
* Bug correction: fatal error when Polylang is used together with the plugin Tabbed Widgets
* Bug correction: language Switcher points sometimes to wrong places

= 0.5 (2011-12-07) =

* Add multisite support
* Rework the Polylang admin panel. There is now a set of predefined languages
* Improve categories and tags language filter in the edit post panel
* Categories and tags created in the edit post panel are now created with the same language as the post
* The language switcher can now force the link to the front page instead of the translated page
* The nav menus can now display a language switcher
* Improved performance
* Optimized the calendar widget (less code and sql queries executed)
* Added the possibility to display posts and terms with no language set (see the documentation to know how to enable this functionnality)
* Started the creation of a small API for theme and plugin programmers
* Bug correction: when using a static front page, the page for posts does not work when using the default permalink settings
* Bug correction: the search form does not work if a static front page is used
* Bug correction: quick edit breaks translations
* Bug correction: categories and post tags translations don't work for more than 2 languages
* Bug correction: the output of wp_page_menu is not correct for non default languages

= 0.4.4 (2011-11-28) =

* Bug correction: When using a static front page, the translated home page displays posts instead of the translated page
* Bug correction: Automatic language setting of existing categories and post tags does not work correctly

= 0.4.3 (2011-11-19) =

* Add Russian translation contributed by [yoyurec](http://yoyurec.in.ua)
* Bug correction: impossible to suppress the language name in the language switcher widget settings
* Bug correction: post's page does not work when using a static front page
* Bug correction: flags in local_flags directory are removed after an automatic upgrade (now works for an upgrade from 0.4.3+ to a higher version)
* Bug correction: switching to default language displays a 404 Error when hiding the default language in url and displaying the language switcher as dropdown
* Other minor bug corrections
* Tests done with WordPress 3.3 beta 3

= 0.4.2 (2011-11-16) =

* Bug correction: language settings page is broken in v0.4.1

= 0.4.1 (2011-11-16) =

* Bug correction: flags shows even when you set doesn't to show
* Bug correction: custom taxonomies do not work
* Bug correction: some users get the fatal error: call to undefined function wp_get_current_user() in /wp-includes/user.php on line 227

= 0.4 (2011-11-10) =

* Add a documentation (in English only)
* Add the possibility to hide the url language information for the default language
* Add the possibility to set the admin language in the user profile
* Add the possibilty to fill existing posts, pages, categories & tags with the default language
* Add support for custom post types and custom taxonomies
* Add the possibility to display flags in the language switcher
* Add CSS classes to customize rendering of the language switcher
* Add the possibility to display the language switcher as a dropdown list
* Add support for calendar widget
* Improve performance: less sql queries
* Improve data validation when creating or updating languages
* Bug correction: 'wp_list_pages' page order is ignored when the plugin is enabled
* Bug correction: when using 'edit' or 'add new' (translation) for posts, the categories appear in the wrong language
* Bug correction: pages are not included in language post count
* Bug correction: the language switcher does not display languages if there are only pages
* Bug correction: the widget filter does not allow to come back to 'all languages' once a language has been set
* Other minor bug corrections

= 0.3.2 (2011-10-20) =

* Bug correction: authors pages are not filtered by language
* Bug correction: language pages use the archive template
* Bug correction: database error for comments on posts and pages
* Bug correction: "Add new" translation for pages creates a post instead of a page
* Bug correction: the search query does not look into pages

= 0.3.1 (2011-10-16) =

* Bug correction: the widget settings cannot be saved when activating Polylang
* Bug correction: the archives widget does not display any links
* Bug correction: ajax form for translations not working in the 'Categories' and 'Post tags' admin panels

= 0.3 (2011-10-07) =

* Add language filter for widgets
* Improved performance for filtering pages by language
* Improved security
* Minor bug correction with versions management

= 0.2 (2011-10-05) =

* Add language filter for nav menus
* Add German translation
* Add language filter for recent comments
* Add ajax to term edit form
* Add ajax to post metabox
* Improved performance for filtering terms by language
* Bugs correction

= 0.1 (2011-09-22) =
* Initial release
