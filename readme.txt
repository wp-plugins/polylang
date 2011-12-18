=== Polylang ===
Contributors: Chouby
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=CCWWYUUQV8F4E
Tags: bilingual, language, i18n, international, l10n, localization, multilingual, multisite, translate, translation, widget
Requires at least: 3.1
Tested up to: 3.3
Stable tag: 0.5.1

Polylang adds multilingual support to WordPress. You set a language for each post and it will be displayed only when browsing this language.

== Description ==

You write posts, pages and create categories and post tags as usual, and then define the language for each of them. The translation is optional. A language switcher widget is provided with the plugin. The plugin does not integrate automatic or professional translation. You have to do the work yourself.

= Upgrade Notice =

Your custom flags in 'polylang/local_flags' directory will be removed when automatically upgrading from v0.4, v0.4.1, v0.4.2. So either do a manual upgrade or backup your custom flags. This problem is solved when upgrading from v0.4.3+ to a higher version. It's always safer to do a database backup before any upgrade.

= Features =

* You can create as many languages as you want
* You can translate posts, pages, categories, post tags, menus
* RSS feed available for each language
* Support for Search form (see the FAQ in the documentation)
* Support for pretty permalinks
* Support for static page (in the right language) used as front page
* All WordPress default widgets (archives, categories, pages, recent comments, recent posts, tag cloud and calendar) are automatically in the right language
* Language switcher provided as a widget or in the nav menu
* All widgets can be displayed or not, depending on the language (new in 0.3)
* Each user can set the WordPress admin language in its profile (new in 0.4)
* Support for custom post types and custom taxonomies (new in 0.4)
* Support for multisite (new in 0.5)

The plugin admin interface is currently available in:

* English
* French
* German contributed by [Christian Ries](http://www.singbyfoot.lu)
* Russian contributed by [yoyurec](http://yoyurec.in.ua)

Other translators are welcome !

= Notes =

* The tests have been made with WordPress from version 3.1 up to 3.3. The plugin does not work with WordPress 3.0.5 and older.
* Your server must run PHP5
* You must deactivate other multilingual plugins before activating Polylang, otherwise, you may get unexpected results !
* Unlike some other plugins, if you deactivate Polylang, your blog will go on working as smoothly as possible. All your posts, pages, category and post tags would be accessible (without language filter of course...).

= Feedback or ideas =

Don't hesitate to [give your feedback](http://wordpress.org/tags/polylang?forum_id=10). It will help making the plugin better. Don't hesitate to rate the plugin too.

== Upgrade Notice ==

Your custom flags in 'polylang/local_flags' directory will be removed when automatically upgrading from v0.4, v0.4.1, v0.4.2. So either do a manual upgrade or backup your custom flags. This problem is solved when upgrading from v0.4.3+ to a higher version. It's always safer to do a database backup before any upgrade.

== Installation ==

1. Download the plugin
1. Extract all the files.
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add the 'language switcher' Widget to let your visitors switch the language.
1. Go to the languages settings page and create the languages you need
1. Upload corresponding .mo files in WordPress languages directory (no need for English). You can download them from [here](http://svn.automattic.com/wordpress-i18n/).
1. Take care that your theme must come with the corresponding .mo file too. If your theme is not internationalized yet, please refer to the [codex](http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers) or ask the theme author to internationalize it.

== Frequently Asked Questions ==

= Why using Polylang and not other well established equivalent plugins ? =

WPML: I tested only the last non-commercial version (2.0.4.1) with WP 3.0.5. The plugin looks quite complete. It's however very heavy (almost 30000 lines of code !). The fact that it has turned commercial is probably adapted to companies or very active bloggers but not well adapted to small blogs.

Xili language: I tested the version 2.2.0. It looks too complex. For example you need to install 3 different plugins to manage post tags translation. If managing post translations is quite easy (and inspired Polylang...), the way to manage categories and post tags translations is not enough user friendly in my opinion. As WPML it's very heavy (about 12000 lines of code).

qtranslate: I tested the version 2.5.23. As claimed by its author, it's probably the best existing plugin... when using it. However, you must know that it is very difficult to come back to a clean site if you deactivate it (as, for example, one post in the database contains all translations). Moreover, it modifies urls so again, if you deactivate it, all links to your internal urls would be broken (not good for SEO).

In comparison to these plugins, Polylang tries to keep things simple and light, and does not mess your blog if you deactivate it. But it is still very young so be indulgent ;-)

= Where to find help ? =

* Read the [documentation](http://plugins.svn.wordpress.org/polylang/trunk/doc/documentation-en.pdf) supplied whith the plugin (in the doc directory) and search the [support forum](http://wordpress.org/tags/polylang?forum_id=10) first. You will most probably find your answer here.
* If you still have a problem, open a new thread in the [support forum](http://wordpress.org/tags/polylang?forum_id=10).

= Is Polylang compatible with multisite ? =

Yes. Since v0.5

= Can I use my own flags for the language switcher ? =

Yes. You have to use PNG or JPG files and name them with the WordPress locale. For example, en_US.png. Then upload these files in the `/polylang/local_flags` directory. Don't use the `/polylang/flags` directory as your files would be removed when updating the plugin.

== Screenshots ==

1. The Polylang languages admin panel in WordPress 3.3

== Changelog ==

= 0.5.1 (2011-12-18) =

* Improved German translation contributed by [Christian Ries](http://www.singbyfoot.lu)
* Bug correction: Translated homepage not recognized as home page when it displays posts
* Bug correction: Predefined language list does not work on IE8
* Bug correction: On some installations, "Add New" post doesn't keep intended language
* Bug correction: Fatal error when Polylang is used together with the plugin Tabbed Widgets
* Bug correction: Language Switcher points sometimes to wrong places

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
* Bug correction: When using a static front page, the page for posts does not work when using the default permalink settings
* Bug correction: The search form does not work if a static front page is used
* Bug correction: Quick edit breaks translations
* Bug correction: Categories and post tags translations don't work for more than 2 languages
* Bug correction: The output of wp_page_menu is not correct for non default languages

= 0.4.4 (2011-11-28) =

* Bug correction: When using a static front page, the translated home page displays posts instead of the translated page
* Bug correction: Automatic language setting of existing categories and post tags does not work correctly

= 0.4.3 (2011-11-19) =

* Add Russian translation contributed by [yoyurec](http://yoyurec.in.ua)
* Bug correction: Impossible to suppress the language name in the language switcher widget settings
* Bug correction: Post's page does not work when using a static front page
* Bug correction: Flags in local_flags directory are removed after an automatic upgrade (now works for an upgrade from 0.4.3+ to a higher version)
* Bug correction: Switching to default language displays a 404 Error when hiding the default language in url and displaying the language switcher as dropdown
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
* Bug correction: 'wp_list_pages' page order is ignored when plugin is enabled
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
