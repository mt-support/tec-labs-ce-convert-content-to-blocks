=== The Events Calendar: Community Events Extension: Convert Submitted Content to Blocks ===
Contributors: theeventscalendar
Donate link: https://evnt.is/29
Tags: events, calendar
Requires at least: 6.0.0
Tested up to: 6.2.2
Requires PHP: 8.0
Stable tag: 2.5.0
License: GPL version 3 or any later version
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Convert the event content submitted through Community Events to block editor format.

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and Markdown parsed.

== Installation ==

Install and activate like any other plugin!

* You can upload the plugin zip file via the *Plugins ‣ Add New* screen
* You can unzip the plugin and then upload to your plugin directory (typically _wp-content/plugins_) via FTP
* Once it has been installed or uploaded, simply visit the main plugin list and activate it

== Frequently Asked Questions ==

= Where can I find more extensions? =

Please visit our [extension library](https://theeventscalendar.com/extensions/) to learn about our complete range of extensions for The Events Calendar and its associated plugins.

= What if I experience problems? =

We're always interested in your feedback and our [Help Desk](https://support.theeventscalendar.com/) are the best place to flag any issues. Do note, however, that the degree of support we provide for extensions like this one tends to be very limited.

== Changelog ==

= [2.5.0] TBD =

* Enhancement - Moved the code to the extension template.

= [2.4.0] 2023-07-06 =

* Enhancement - Moved the code to its own plugin.
* Enhancement - Added a hard coded option to define a cutoff date before which events content is not converted.
* Fix - Made sure that the post content update only happens on Community Events submissions.

= [2.3.1] 2023-06-27 =

* Fix - Adjusted the return value type of the `tec_ce_remove_blocks_on_edit()`` function.

= [2.3.0] 2023-06-23 =

* Enhancement - Added a way to handle events that were created before the snippet and already have block editor markup.
* Enhancement - Handle multiple organizers.

= [2.2.0] 2023-06-21 =

* Enhancement - Grab custom fields automatically.

= [2.1.0] 2023-06-21 =

* Fix - Make sure organizer shows up in the block.
* Fix - Remove checking meta update success to proceed.
* Enhancement - Used saved options when checking for submission source.
* Enhancement - Add event price block.
* Enhancement - Add blocks for custom fields. (Hard coded.)

= [1.0.0] 2020-06-20 =

* Initial release