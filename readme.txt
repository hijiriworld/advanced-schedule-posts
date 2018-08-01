=== Advanced Schedule Posts ===
Contributors: hijiri
Tags: schedule, post, admin
Requires at least: 3.5.0
Tested up to: 4.7.2
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Advanced Schedule Posts plugin allows to you to set the datetime of expiration and the schedule which overwrites the another post.

== Description ==

Allows to you to set the datetime of expiration and the schedule which overwrites the another post.

<strong>Datetime of Expiration</strong>

When it becomes the datetime of expiration, the status of post is changed to 'draft'.

<strong>Overwrite the another post</strong>

When the scheduled post(A) is published by wp cron, the new post(A) overwrite the another post(B) and the old post(B) is changed to draft.<br>
The slug of the new post(A) is changed to the slug of the old post(B).<br>
The slug of the old post(B) is changed to what added suffix(Ymd).

This Plugin published on <a href="https://github.com/hijiriworld/advanced-schedule-posts">GitHub.</a>

== Installation ==

1. Upload 'advanced-schedule-posts' folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Screenshots ==

1. Setting - datetime of expiration
2. Setting - overwrite the another post
3. List
4. Admin Setting

== Changelog ==

= 1.2.1 =

* Change the display position of this plugin's input-box into the Publish's meta-box.
* Change the width of input-box and select-box to 100%.
* Sort the overwrite post list by parameter of 'menu_order'( Respect for Intuitive Custom Post Order plugin). Defaults to 'post_date'.

= 1.2.0 =

* Select function that you want to enable.
* Enable Custom Post Types with parameter 'public' => 'false'.

= 1.1.6.1 =

* Bug Fix: for v1.1.6.

= 1.1.6 =

* When overwrite the another post, it change the `post_id` that is included in the 'ACF Post Object Field'.

= 1.1.5 =

* Bug Fix: Improved Tag disappears in the editor.

= 1.1.4.1 =

* Bug Fix: Unsetting with Quick Edit For v4.7.2.

= 1.1.4 =

* Bug Fix: Unsetting with Quick Edit.

= 1.1.3 =

* Select Post Types that you want to enable.

= 1.1.2 =

* When overwrite the another post, it change the `post_id` that is included in the menu objects.

= 1.1.1 =

* Do action improved.( !is_admin() )

= 1.1.0 =

* Overwrite function was changed from 'wp-cron.php' into 'init action hook'.

= 1.0.0 =

* Initial Release.
