=== WP-Seedbank ===
Contributors: maymay
Donate link: https://npo.justgive.org/nonprofits/donate.jsp?ein=45-3043080
Tags: custom posts, community, seedbank, ecology, seed swap, trade
Requires at least: 3.5.2
Tested up to: 3.9
Stable tag: 0.4.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The WP-SeedBank plugin turns any self-hosted WordPress blog into a community seedbank (or seed library). :D

== Description ==

Add a seedbank (or seed library) to your WordPress-powered website. Registered users can post seed offers and requests (called "seed exchange posts") for others to search, making it easier to swap seeds and grow your community. Try the [live demo](http://wordpress-seedbank-plugin.cyberbusking.org/) yourself.

Some key Features include:

* Customize numerous metadata fields to suit your specific community's needs.
* Import a comma-separated values file to create seed exchange postings in batches.
* Search, filter, and sort through seed exchanges with a detailed heads-up display of available postings.
* Available in multiple languages, including:
    * Swedish (`sv_SE`)
    * Italian (`it_IT`)
    * Hindi (`hi_IN`)

Want WP-SeedBank in your language? [Join our team of translators](https://www.transifex.com/signup/contributor/?next=/projects/p/wp-seedbank/)!

= Quick start guide =

Follow these steps to get your seed exchange website up and running quickly:

1. Install WordPress on your website, if you haven't already. (You can use [WordPress's Famous 5-Minute Install instructions](https://codex.wordpress.org/Installing_WordPress#Famous_5-Minute_Install).)
1. Install the WP-Seedbank plugin. (You can use [WordPress's Automatic Plugin Installation](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins) instructions, or the WordPress Beginner "[Step by Step Guide to Install a WordPress Plugin for Beginners](http://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/)" instructions.)
1. Enable user registration from your [WordPress General Settings screen](https://codex.wordpress.org/Settings_General_Screen) by checking the box that reads "Anyone can register." This allows the people in your community to make user accounts on your website and use the WP-Seedbank software.
    1. Set *New User Default Role* to *Contributor*. This enables the people in your community who have made user accounts to create seed exchange posts and submit them for your review to publish.
1. Share the Web address (URL) of your website with your community and invite them to join.
1. Periodically check your website for seed exchange posts created by members of your community, review, and publish them. (The same process that you use for [reviewing and publishing regular posts applies here](https://codex.wordpress.org/Writing_Posts).)
1. Optionally, you can promote members of your community who have created seed exchange posts in the past to the Author role, so that they no longer need your approval to publish seed exchange posts. Learn more about [WordPress's user roles and capabilities](https://codex.wordpress.org/Roles_and_Capabilities).

See also:

* [WP-SeedBank plugin: a mini user's guide](http://maymay.net/blog/2014/01/23/wp-seedbank-plugin-a-mini-users-guide/)

== Installation ==

1. Download the plugin file.
1. Unzip the file into your 'wp-content/plugins/' directory.
1. Go to your WordPress administration panel and activate the plugin.

== Screenshots ==

1. The new Seed Exchange request/offer screen. In addition to your post title and content, you use the "Seed Exchange Details" box to fill in the details of your seed exchange posting in a madlibs-style form. You can also use the "Scientific Names" box to categorize your seed according to its biological classification, if you know it.

2. The Batch Exchange page lets you upload a file to create many seed exchanges at once.

3. The "Seed Exchanges" screen gives you a heads-up display of all the seed exchange postings. You can click on the links in the column to filter your view; in this screenshot, clicking on "Swap" in the "Exchange Types" link will filter the list so it only displays seed swaps. You can also sort the list according to the Quantity, Seed Expiry Date, or Exchange Expiry Date by clicking on those column headers.

== Frequently Asked Questions ==

= Why doesn't my post for, say, Cauliflower show up in the WordPress search? =

The Seed Exchange posts you're creating use WordPress taxonomies, which WordPress doesn't include in search results by default. This means that if you're creating a Seed Exchange post for a given vegetable, but the only place the name of this vegetable appears is in the plugin's custom "Common Name" field (and not in the post's title or content), then WordPress can't find that posting based on a simple keyword search. If you'd like to make the WP-SeedBank's taxonomies searchable, too, we recommend the [Search Everything plugin](https://wordpress.org/plugins/search-everything/).

== Changelog ==

= Version 0.4.4 =

* [Bugfix](https://github.com/fabacab/wp-seedbank/issues/15): Correct typo in Scientific Name for parsley.
* Enhancement: [Add Scientific Name for chives](https://github.com/fabacab/wp-seedbank/issues/14) and [bitter melon](https://github.com/fabacab/wp-seedbank/issues/13).
    * If you don't already have these in your system but would like to add them from the built-in set provided by this plugin, go to your Plugin Administration Screen and de-activate this plugin, then reactive it.

= Version 0.4.3 =

* Feature: "My Seeds" link takes you to a filtered view of all Seed Exchange Posts.
* Translations for Hindi (`hi_IN`) are now available.
* Updated translations for Swedish and Italian.

= Version 0.4.2 =

* Feature: Add a new common name directly from the Seed Exchange Details box. Simply type the common name you're posting about and, if it already exists, it will automatically be filled in. If it doesn't exist, saving your post will add it to the list of available common names.
* Feature: Seed Exchanges listing page now has additional columns that allow you to filter and sort your view of the seed exchange postings.
* Feature: Admins can now edit the inventory units just like they can with the other fields such as Common Name.

= Version 0.4.1 =

* Feature: Taxonomies for Seed Exchange Details are now linked when they are displayed.
* Bugfix: Correctly load image and JavaScript assets on PHP 5.2.x installs.
* Translations for Swedish (`sv_SE`) and Italian (`it_IT`) are now available.
    * Activate your language translation by [configuring your WordPress to use your language](http://codex.wordpress.org/WordPress_in_Your_Language).
    * Want WP-SeedBank in your language? [Join our team of translators](https://www.transifex.com/signup/contributor/?next=/projects/p/wp-seedbank/)!

= Version 0.4 =

* Feature: The "Seed Genera" taxonomy has been completely removed, and replaced with a "Scientific Names" taxonomy.
    * The "Add Seed Exchange" screen now has a "Scientific Names" selection box where you can select the scientific name for the kind of seed you're making a post about. The scientific names are organized according to their hierarchical biological classification: Genus, then species, then subspecies or variety or cultivar Group. Unlike built-in WordPress categories, the user interface for selecting a scientific name only allows you to choose one per post.
    * **This update will delete all "Seed Genera" tags.** If you are using the "Seed Genera" taxonomy, you will need to reapply the genera you've entered in the new Scientific Names taxonomy.
* Feature: Cleaner editing interface; meta boxes that duplicated "Seed Exchange Details" fields have been removed.
* Bugfix: Exchange expiry date is now required. Leaving this blank caused your exchange post to be "Deleted" immediately.
* Bugfix: Perform database migration from very old 0.2.x versions.

= Version 0.3 =
* Complete internal rewrite to improve code quality, security, and maintainability.
* [Bugfix](https://github.com/fabacab/wp-seedbank/issues/6): Seed Exchange posts are now correctly linked to their respective category pages.
* Feature: Seed Exchange posts can now be composed in "visual" (WYSIWYG) mode.
* Feature: Show information entered into the Seed Exchange Details box on the post itself, without needing to write your own template. If not already active on your website, go to WordPress's "Settings -> Reading" screen, and choose whether to position the details above or below the post content in the new "Seedbank Settings" section.
* Feature: Contextual help offers step-by-step instructions for use. Click the "Help" button on any SeedBank page for help with that page.

= Version 0.2.3 =
* Simpler seed exchange editing screen for typical users.
* Manage comments (public replies to seed exchange posts) on the post edit screen itself.
* Allow admins to reassign seed exchange author.
* Seed exchange posts can now be viewed as their own archive category.
* Fix bug where singular post type name wasn't correctly set or displayed.

= Version 0.2.2 =
* Fix bug where permalink structure did not reflect seed exchange post types.

= Version 0.2.1 =
* Fix minor JavaScript user interface bugs.
* Remove unused activation code.

= Version 0.2 =
* Make "Swap" the default seed exchange type.
* Cleaner interface for viewing seed exchanges and importing from CSV files.
* More default plant common names.
* Clean internal code references.

= Version 0.1 =
* Initial public release.

== Credits ==

The WP-Seedbank plugin could not have been possible without the generosity of the Team Hummingbird Project volunteers at [Cleveland GiveCamp](http://clevelandgivecamp.org/). Special thanks to:

* The author, [Cyberbusking.org](http://Cyberbusking.org)
* Alex Redinger
* Kip Price

Maintaining this plugin is a volunteer, community effort, and a labor of love for the original development team. However, if you like it, please consider [donating to The Hummingbird Project](https://npo.justgive.org/nonprofits/donate.jsp?ein=45-3043080), or [contributing to my's Cyberbusking fund](http://Cyberbusking.org/). Your support makes continued development possible!
