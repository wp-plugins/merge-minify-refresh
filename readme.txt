=== Merge + Minify + Refresh ===
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=VL5VBHN57FVS2
Contributors:launchinteractive
Tags: merge, concatenate, minify, yuicompressor, closure, refresh
Requires at least: 3.6.1
Stable tag: trunk
Tested up to: 4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Merges/Concatenates CSS & Javascript and then minifies using Minify (for CSS) and Google Closure (for JS with Minify as a fallback).

== Description ==

This plugin merges/concatenates Cascading Style Sheets & Javascript files into groups. It then minifies the generated files using Minify (for CSS) and Google Closure (for JS - fallback to Minify when not available). Minification is done via WP-Cron so that it doesn't slow down the website. When JS or CSS changes files are re-processed. No need to empty cache!

Inspired by [MinQueue](https://wordpress.org/plugins/minqueue/) and [Dependency Minification](https://wordpress.org/plugins/dependency-minification) plugins.

Minification by [Minify](https://github.com/matthiasmullie/minify) and [Google Closure](https://developers.google.com/closure/)

In order to ensure fast loading times its recommended to set long expiry dates for CSS and JS as well as make sure gzip or deflate is on.

**Note** Installing this plugin on a server with the eAccellerator module installed has the potential to break as Minify uses anonymous functions which return NULL. [View eAccellerator Issue Thread](https://github.com/eaccelerator/eaccelerator/issues/12)

**Features**

*	Merges JS and CSS files to reduce the number of HTTP requests
*	Handles scripts loaded in the header & footer
*	Compatable with localized scripts
*	Creates WP-Cron for minification as this can take some time to complete
*	Minifies JS with Google Closure (requires php exec) with fallback to Minify
*	Minifies CSS with Minify
*	Failed minification doesn't break the site. Visitors will instead only see the merged results
*	Stores Assets in /wp-content/mmr/ folder
*	Uses last modified date in filename so any changes to JS or CSS automatically get re-processed and downloaded on browser refresh
*	View status of merge and minify on settings page in WordPress admin

== Installation ==

1. Upload the `merge-minify-refresh` folder to the `/wp-content/plugins/` directory or upload the zip within WordPress
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.4.2 =
* Append ; to merged script files to prevent javascript errors

= 1.4.1 =
* woocommerce compatability

= 1.4 =
* Remove unused code in insepect_scripts()
* Resolved issues with late enqued scripts and styles
* Changed code for removal of string continuations
* Stopped dequeing styles and scripts as we now use done

= 1.3 =
* Process styles/scriptes enqued within body of page in the footer
* Prevent scripts enqued within body of page outputting twice
* Clear previous processed files fully when purge all clicked

= 1.2 =
* Bugfix

= 1.1 =
* Only write admin ajax response when it has changed
* CSS now compressed using Minify
* JS compressed with Minify when Closure not available

= 1.0 =
* Don't remove unminified files anymore for rare occasions when css or js return a 404 error
* Admin now updates automatically.

= 0.9 =
* Fix issue with scripts failing to compile because of remove_continuations

= 0.8 =
* Fix bug when javascript and css has same handle

= 0.7 =
* Bugfix

= 0.6 =
* Remove Javascript String Continuations
* Show queued scripts/css in admin
* Prevent YUI Compressor stripping 0 second units (minified transitions now work)

= 0.5 =
* Ensure file paths are absolute
* Use ABSPATH instead of DOCUMENT_ROOT

= 0.4 =
* Ignore CSS url paths that start with http

= 0.3 =
* Minor code refactoring and cleanup

= 0.2 =
* Log error when exec not available
* Fix remote url detection
* Fix admin header redirect

= 0.1 =
* Initial Release


