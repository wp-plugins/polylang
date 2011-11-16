=== Polylang ===
Contributors: Chouby
Tags: bilingual, multilingual, language, i18n, l10n, international, translate, translation, widget
Requires at least: 3.1
Tested up to: 3.2.1
Stable tag: 0.4.2

Adds multilingual support to WordPress.

== Description ==

Polylang adds multilingual support to WordPress. It acts as a language filter for posts you have written in several languages. It will however not make the translation for you ! If you are looking for automatic translation, look for another plugin. Unlike some other plugins, it does not integrate professionnal translation.

You write posts, pages and create categories and post tags as usual. You just have to define the language and it will be displayed only if the visitor is browsing this language. Optionaly, you can mark each post, page, category and post tag to be the translation of another one. Thus if, for example, your visitor is reading a post, he can switch (using the language switcher widget provided with the plugin) to the same post translated in another language (provided that you translated it !).

= Features =

* You can create as many languages as you want
* You can translate posts, pages, categories, post tags, menus
* RSS feed available for each language
* Support for Search form (see FAQ)
* Support for pretty permalinks
* Support for static page (in the right language) used as front page
* All WordPress default widgets are automatically in the right language : archives, categories, pages, recent comments, recent posts, tag cloud and calendar
* All widgets can be displayed or not, depending on the language (new in 0.3)
* Language switcher provided as a widget
* The plugin admin is currently available in English, French and German
* Each user can set the WordPress admin language in its profile (new in 0.4)
* Support for custom post types and custom taxonomies (new in 0.4)

= Notes =

* The tests have been made with WordPress 3.2.1 and with the Twenty Eleven theme (see FAQ). Although I did not test previous versions, I see no reason why it should not work with WordPress 3.1. However the plugin should not work with WordPress 3.0.5 and lower.
* Your server must run PHP5
* Multisite is not supported yet.
* You must deactivate other multilingual plugins before activating Polylang, otherwise, you may get unexpected results !
* Unlike some other plugins, if you deactivate Polylang, your blog will go on working as smoothly as possible. All your posts, pages, category and post tags would be accessible (without language filter of course...).

= Feedback or ideas =

You use the plugin or just tested it ? Don't hesitate to [give your feedback](http://wordpress.org/tags/polylang?forum_id=10). It will help making the plugin better. Don't hesitate to rate the plugin too.

== Installation ==

1. Download the plugin
1. Extract all the files. 
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add the 'language switcher' Widget to let your visitors switch the language.
1. Go to the languages settings page and create the languages you need
1. Upload corresponding .mo files in WordPress languages directory (no need for English). You can download it from [here](http://svn.automattic.com/wordpress-i18n/).
1. Take care that your theme must come with the corresponding .mo file too. If your theme is not internationalized yet, please refer to the [codex](http://codex.wordpress.org/I18n_for_WordPress_Developers#I18n_for_theme_and_plugin_developers) or ask the theme author to internationalize it.

== Frequently Asked Questions ==

= Why using Polylang and not other well established equivalent plugins ? =

WPML: I tested only the last non-commercial version (2.0.4.1) with WP 3.0.5. The plugin looks quite complete. It's however very heavy (almost 30000 lines of code !). The fact that it has turned commercial is probably adapted to companies or very active bloggers but not well adapted to small blogs.

Xili language: I tested the version 2.2.0. It looks too complex. For example you need to install 3 different plugins to manage post tags translation. If managing post translations is quite easy (and inspired Polylang...), the way to manage categories and post tags translations is not enough user friendly in my opinion. As WPML it's very heavy (about 12000 lines of code).

qtranslate: I tested the version 2.5.23. As claimed by its author, it's probably the best existing plugin... when using it. However, you must know that it is very difficult to come back to a clean site if you deactivate it (as, for example, one post in the database contains all translations). Moreover, it modifies urls so again, if you deactivate it, all links to your internal urls would be broken (not good for SEO).

In comparison to these plugins, Polylang tries to keep things simple and light, and does not mess your blog if you deactivate it. But it is still very young so be indulgent ;-) 

= The language filter is lost when using the search form =

Your theme uses the template searchform.php (as Twenty Eleven does) or hardcoded the search form and javascript is disabled. Unfortunately Polylang currently does not support this. So you have the following alternatives to get the things work well:

* Use the 'get_search_form' function and place your custom search form in functions.php as described in the [codex](http://codex.wordpress.org/Function_Reference/get_search_form). The plugin also works well if you use the default search form provided by WordPress.
* Enable javascript (unfortunately you can't control this for your visitors so the first solution is better) 

= I activated the plugin and my posts are not displayed any more =

You MUST define a language for all your posts and pages otherwise they will not pass the language filter... 

= I activated the plugin and my categories and post tags are not displayed any more =

You MUST define a language for all your categories and post tags otherwise they will not pass the language filter...

= Is Polylang compatible with multisite ? = 

Not yet.

= Is it possible to display a language switcher without using the widget =

It is possible to get a language switcher where you want in your theme without using the widget. For this, you can simply use in your theme the following instruction: `do_action('the_languages');`

= Can I use my own flags for the language switcher ? =

Yes. You have to use PNG files and name them with the WordPress locale. For example, en_US.png. Then upload these files in the `/polylang/local_flags` directory. Don't use the `/polylang/flags` directory as your files may be overwritten when updating the plugin.

= Polylang does not come with a lot flags. Where can I find other flags ? =

There are many sources. I included some of the [famfamfam](http://www.famfamfam.com) flags which I renamed.

= How to know the current language in the theme ? =

WordPress provides at least two functions for the theme or plugin author to know the current language:
* `get_locale()` returns the WordPress locale in the format `en_US`
* `get_bloginfo('language')` returns the locale in the format `en-US`
Note the difference between '_' and '-' in the two functions.
You can look at the following forum topics:
[Return the current language as variable for your template](http://wordpress.org/support/topic/plugin-polylang-return-the-current-language-as-variable-for-your-template)
[How to translate/switch specific contents on templates](http://wordpress.org/support/topic/plugin-polylang-how-to-translateswitch-specific-contents-on-templates.html)

== Changelog ==

= 0.4.2 (2011-11-16) =

* Bug correction: language settings page is broken

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
