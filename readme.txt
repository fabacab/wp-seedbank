=== WP-Seedbank ===
Author: The Hummingbird Project
Contributors: meitar
Plugin URL: http://hummingbirdproject.org/initiatives/wordpress-seedbank-plugin/
Donate link: https://npo.justgive.org/nonprofits/donate.jsp?ein=45-3043080
Tags: custom posts, community, seedbank, ecology, seed swap, trade
Requires at least: 3.5.2
Tested up to: 3.8
Stable tag: 0.3
License: GPL3

The WP-SeedBank plugin turns any self-hosted WordPress blog into a community seedbank (or seed library). :D

== Description ==

Add a seedbank (or seed library) to your WordPress-powered website. Registered users can post seed offers and requests (called "seed exchange posts") for others to search, making it easier to swap seeds and grow your community.

Features include:

* Ability to import a comma-separated values file to create seed exchanges in batches.
* Ability to customize verbage to suit a specific community's needs.

= Quick start guide =

Follow these steps to get your seed exchange website up and running quickly:

1. Install WordPress on your website, if you haven't already. (You can use [WordPress's Famous 5-Minute Install instructions](https://codex.wordpress.org/Installing_WordPress#Famous_5-Minute_Install).)
1. Install the WP-Seedbank plugin. (You can use [WordPress's Automatic Plugin Installation](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins) instructions, or the WordPress Beginner "[Step by Step Guide to Install a WordPress Plugin for Beginners](http://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/)" instructions.)
1. Enable user registration from your [WordPress General Settings screen](https://codex.wordpress.org/Settings_General_Screen) by checking the box that reads "Anyone can register." This allows the people in your community to make user accounts on your website and use the WP-Seedbank software.
    1. Set *New User Default Role* to *Contributor*. This enables the people in your community who have made user accounts to create seed exchange posts and submit them for your review to publish.
1. Share the Web address (URL) of your website with your community and invite them to join.
1. Periodically check your website for seed exchange posts created by members of your community, review, and publish them. (The same process that you use for [reviewing and publishing regular posts applies here](https://codex.wordpress.org/Writing_Posts).)
1. Optionally, you can promote members of your community who have created seed exchange posts in the past to the Author role, so that they no longer need your approval to publish seed exchange posts. Learn more about [WordPress's user roles and capabilities](https://codex.wordpress.org/Roles_and_Capabilities).

== Installation ==

1. Download the plugin file.
1. Unzip the file into your 'wp-content/plugins/' directory.
1. Go to your WordPress administration panel and activate the plugin.

== Screenshots ==

1. The new Seed Exchange request/offer page.

2. The Batch Exchange page lets you upload a file to create many seed exchanges at once.

== Frequently Asked Questions ==

= Why doesn't my post for, say, Cauliflower show up in the WordPress search? =

The Seed Exchange posts you're creating use WordPress taxonomies, which WordPress doesn't include in search results by default. This means that if you're creating a Seed Exchange post for a given vegetable, but the only place the name of this vegetable appears is in the plugin's custom "Common Name" field (and not in the post's title or content), then WordPress can't find that posting based on a simple keyword search. If you'd like to make the WP-SeedBank's taxonomies searchable, too, we recommend the [Search Everything plugin](https://wordpress.org/plugins/search-everything/).

== Changelog ==

= 0.3 =
* Complete internal rewrite to improve code quality, security, and maintainability.
* [Bugfix](https://github.com/meitar/wp-seedbank/issues/6): Seed Exchange posts are now correctly linked to their respective category pages.
* Feature: Seed Exchange posts can now be composed in "visual" (WYSIWYG) mode.
* Feature: Show information entered into the Seed Exchange Details box on the post itself, without needing to write your own template. If not already active on your website, go to WordPress's "Settings -> Reading" screen, and choose whether to position the details above or below the post content in the new "Seedbank Settings" section.
* Feature: Contextual help offers step-by-step instructions for use. Click the "Help" button on any SeedBank page for help with that page.

= 0.2.3 =
* Simpler seed exchange editing screen for typical users.
* Manage comments (public replies to seed exchange posts) on the post edit screen itself.
* Allow admins to reassign seed exchange author.
* Seed exchange posts can now be viewed as their own archive category.
* Fix bug where singular post type name wasn't correctly set or displayed.

= 0.2.2 =
* Fix bug where permalink structure did not reflect seed exchange post types.

= 0.2.1 =
* Fix minor JavaScript user interface bugs.
* Remove unused activation code.

= 0.2 =
* Make "Swap" the default seed exchange type.
* Cleaner interface for viewing seed exchanges and importing from CSV files.
* More default plant common names.
* Clean internal code references.

= 0.1 =
* Initial public release.

== Credits ==

The WP-Seedbank plugin could not have been possible without the generosity of the Team Hummingbird Project volunteers at [Cleveland GiveCamp](http://clevelandgivecamp.org/). Special thanks to:

* [Meitar Moscovitz](http://meitarmoscovitz.com/), [Cyberbusking.org](http://Cyberbusking.org)
* Alex Redinger
* Kip Price

Maintaining this plugin is a volunteer, community effort, and a labor of love for the original development team. However, if you like it, please consider [donating to The Hummingbird Project](https://npo.justgive.org/nonprofits/donate.jsp?ein=45-3043080), [purchasing one of Meitar's web development books](http://www.amazon.com/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fs%3Fie%3DUTF8%26redirect%3Dtrue%26sort%3Drelevancerank%26search-type%3Dss%26index%3Dbooks%26ref%3Dntt%255Fathr%255Fdp%255Fsr%255F2%26field-author%3DMeitar%2520Moscovitz&tag=maymaydotnet-20&linkCode=ur2&camp=1789&creative=390957), or [contributing to Meitar's Cyberbusking fund](http://Cyberbusking.org/). Your support makes continued development possible!
