=== Plugin Name ===
Contributors: Chouby
Tags: bilingual, multilingual, language, i18n, l10n, international
Requires at least: 3.1
Tested up to: 3.2.1
Stable tag: 0.1

Adds multilingual support to WordPress.

== Description ==

This plugin will not make the translation for you ! I you are looking for automatic translation, look for another plugin. Unlike some other plugins, Polylang does not integrate professionnal translation.

You write posts, pages and create categories and post tags as usual. You just have to define the language and it will be displayed only if the visitor is browsing this language. Optionaly, you can mark each post, page, category and post tag to be the translation of another one. Thus if, for example, your visitor is reading a post, it can switch (using the simple language switcher widget provided with the plugin) to the same post translated in another language (provided that you translated it !). There is no limit for the number of language.

Of course, the plugin itself is internationalized but is currently available only in English and in French.

Unlike some other plugins, if you deactivate Polylang, your blog will go on working as smoothly as possible. All your posts, pages, category and post tags would be accessible without language filter.

The tests have been made with WordPress 3.2.1 and with the Twenty Eleven theme (see FAQ). Although I did not test previous versions, I see no reason why it should not work with WordPress 3.1. However the plugin should not work with WordPress 3.0.5 and lower. Multisite has not been tested.

This is the initial version. Although I tested it carefully, there are probably still bugs. There are also lacking features. Remember that it is always better to test locally (or on a test server) that a plugin is fitting your needs before going in production. Note also that you must deactivate other multilingual plugins before activating Polylang. Otherwise, you may get unexpected results !

== Installation ==

1. Download the plugin
1. Extract all the files. 
1. Upload everything (keeping the directory structure) to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add the 'language switcher' Widget to let your visitors switch the language.
1. Go to the languages settings page and create the languages you need
1. Upload corresponding .mo files in WordPress languages directory (no need for English). You can download it from [here](http://svn.automattic.com/wordpress-i18n/). Take care that your theme must come with the corresponding .mo file too.

== Frequently Asked Questions ==

= Why using Polylang and not other well established equivalent plugins ? =

WPML: I tested only the last non-commercial version with WP 3.0.5. The plugin looks quite complete. It's however very heavy (almost 30000 lines of code !). The fact that it has turned commercial is probably adapted to companies or very active bloggers but not well adapted to small blogs.

Xili language: I tested the version 2.2.0. It looks too complex. For example you need to install 3 different plugins to manage post tags translation. If managing post translations is quite easy (and inspired Polylang...), the way to manage categories and post tags translations is not enough user friendly is my opinion. As WPML it's very heavy (about 12000 lines of code).

qtranslate: I tested the version 2.5.23. As claimed by its author, it's probably the best existing plugin... when using it. However, you must know that it is very difficult to come back to a clean site if you deactivate it (as, for example, one post in the database contains all translations). Moreover, it modifies urls so again, if you deactivate it, all links to your internal urls would be broken (not good for SEO).

In comparison to these plugins, Polylang tries to keep things simple and light, and does not mess your blog if you deactivate it. But it is still very young so be indulgent ;-) 

= Language filter is lost when using the search form =

Your theme uses the template searchform.php (as Twenty Eleven does) or hardcoded the search form and javascript is disabled. Unfortunately Polylang currently does not support this. So you have the following alternatives to get the things work well:

* Use the 'get_search_form' function and place your custom search form in functions.php as described in the [codex](http://codex.wordpress.org/Function_Reference/get_search_form). The plugin also works well if you use the default search form provided by WordPress.
* Enable javascript (unfortunately you can't control this for your visitors so the first solution is better) 

= Language filter is lost when using the calendar widget =

The plugin is not compatible with the calendar widget. The calendar displays well but it does not link to posts filtered in the right language. Consider using the Archives widget instead.

= The homepage link always send to the homepage in the default language =

Your theme has not been adapted to Polylang and both javascript and cookies are disabled. Unfortunately, it seems impossible (or too complex for me !) to correctly filter home_url (or bloginfo('url') and be sure that all things work. So you have the following alternatives:
 
* Use 'bloginfo('lang_url)' in your theme whenever you want to link to the homepage in the right language. Don't use this template tag for the search form action.  
* Enable javascript and/or cookies (unfortunately you can't control this for your visitors so the first solution is better) 

= I activated the plugin and my posts are not displayed any more =

You MUST define a language for all your posts and pages otherwise they will not pass the language filter... 

= I activated the plugin and my categories and post tags are not displayed any more =

You MUST define a language for all your categories and post tags otherwise they will not pass the language filter... 

== Changelog ==

= 0.1 =
* Initial release

