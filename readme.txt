=== Advanced Schedule Posts ===
Contributors: hijiri
Tags: schedule, post, admin
Requires at least: 3.5.0
Tested up to: 4.1.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to set datetime of expiration and to set schedule which overwrites the another post.

== Description ==

Advanced Schedule Posts plugin allows the user to set datetime of expiration and to set schedule which overwrites the another post.

Datetime of Expiration:
When it becomes the datetime of expiration, the post is changed to draft.

Overwrite the another post:
When the scheduled post(A) is published by wp cron, the new post(A) overwrite the another post(B) and the old post(B) is changed to draft.
The slug of the new post(A) is changed to the slug of the old post(B).
The slug of the old post(B) is changed to what added suffix(Ymd).

== Installation ==

1. Upload 'advanced-schedule-posts' folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. setting - datetime of expiration
2. setting - overwrite the another post
3. list

== Changelog ==

= 1.1.1 =

Do action improved.( !is_admin() )

= 1.1.0 =

Overwrite function was changed from 'wp-cron.php' into 'init action hook'.

= 1.0.0 =

Initial Release.