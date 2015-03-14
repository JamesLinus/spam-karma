=== Spam Karma ===
Contributors: strider72
Tags: comment, spam, anti-spam
Tested up to: 4.1
Requires at least: 3.0
Stable tag: 2.4

A flexible and modular anti-spam plugin for WordPress

== Description ==

Spam Karma is meant to stop all forms of automated Blog spam effortlessly, while remaining as unobtrusive as possible to regular commenters. It is a modular system that itself includes various plugins, each of which examines a certain aspect of an incoming comment and assigns a positive or negative Karma score depending on whether that aspect seems "spammy" or not. (For example, one plugin looks at how long the poster was on the page before posting a comment. If they posted extremely quickly after loading the page, it's more likely that's a spambot.) All of the individual scores are added up, and if the comment's total is too low, it's blocked.

The strength of each plugin can be controlled by the Admin. For example, one plugin assigns a score depending on the age of the post being commented on, but if your blog tends to get legitimate comments on old posts, you might turn the strength of that down, or turn it off entirely.

A few plugins perform other functions, such as one that sends an email report to the site admin every week saying what's been blocked or how many comments are borderline. As you can see it's a fairly flexible system -- but basically each plugin either assigns karma or does something in response to the Karma assigned by other plugins.

== Installation ==

Upload the 'spam-karma' folder to the '/wp-content/plugins/' directory and activate it in Admin.  It is recommended that you visit the Settings page at least once upon first activation.

== Changelog ==

= 2.4 =
  Initial WP Repository version. Thanks to Dr Dave for his original work in creating this plugin.

== Screenshots ==

1. The Spam Karma settings page
