=== Plugin Name ===
Contributors: idealien
Donate link: http://idealienstudios.com/
Tags: rideshare, CPT, custom post type, gravity forms
Requires at least: 3.2
Tested up to: 3.5
Stable tag: 0.2.1

A custom post type for you &amp; your community to get to where you want to go using less gas and having more fun together.

== Description ==

The Idealien Rideshare plugin creates a custom post type which enables you and your community to get to where you want to go using less gas and having more fun on road trips. It has been designed towards both event and city-based rideshares so that drivers and passengers in the same city can co-ordinate their travel to whatever festival, concert, workshop, WordCamp, etc they are attending elsewhere.

The admin form is easy for adding / editing rideshares for those who have access to your sites' admin console. To make the process as easy for your extended community, this plugin also provides an exceptional Gravity Form to let anyone submit their rideshare request via the front-end of your website.

**Features**

*   All drop-down data elements (event, city and rideshare type) are setup as taxonomies which you can easily add / remove the plugin defaults to suit your situation.
*   From the front-end with Gravity Forms, users can add a city or event which will add to those taxonomies – your rideshare options grow without content wranglers having to do anything.
*   Multiple shortcodes / options to display the rideshare list and form. Designed to support those who have no desire / capability to modify their themes but also extensible for those who do.
*   v0.2 - Adds ability to set shortcode parameters via querystring (list only rideshares for a particular city or event or type)
*   v0.2 - Adds ability to notify registered users via email about contact requests.

== Installation ==

1. Upload the entire `idealien-rideshare` folder into the `/wp-content/plugins/` directory
1. Activate the plugin through the Plugins menu in WordPress.
1. Place the shortcode **[ridesharelist]** wherever you want the rideshares to display.
1. Place the shortcode **[ridesharelist style=event]** to have a separate table per event.
1. To take advantage of the front-end submission form, you must have [Gravity Forms](http://gravityforms.com/ "Forms for WordPress Just Don't Get Any Easier") v1.5 or higher installed PLUS the [Gravity Forms Custom Post Type enhancement plugin](http//wordpress.org/extend/plugins/gravity-forms-custom-post-types/). Import the form from `/idealien-rideshare/form/` and insert it as you would with any other GF.

== Frequently Asked Questions ==

= How can I suggest a feature or get help with the plugin? =

Visit the [Idealien Rideshare FAQ](http//rideshare.idealienstudios.com/faq/)

== Screenshots ==

1. The custom post type in the admin console.
2. The Gravity Form in editor view.
3. The Gravity Form on the website.
4. The full rideshare table using [ridesharelist] shortcode.
5. The event based breakdown table using [ridesharelist style=event] shortcode.

== Changelog ==

= 0.1 =
* First version fit for public consumption.

= 0.2 =
* Filters for the ridesharelist shortcode
* Dynamic filtering via the querystring

= 0.2.1 =
* Revisions to the contact form / buddypress / email options for follow-up to rideshare
* Revised file names on XML of forms to match version numbering

== Upgrade Notice ==

Switching from v0.1 to v0.2+ you will need to import a new copy of the form that comes with the plugin - v0.2-rideshare-gravityforms.xml

== Planned Future features ==

* Include the form to be imported upon first activation of the plugin.
* Have the plugin delete post type &amp; taxonomy data during uninstallation. For now, delete any terms &amp; rideshares before you uninstall to keep your database clean.
