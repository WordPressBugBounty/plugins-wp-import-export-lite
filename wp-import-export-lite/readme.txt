=== WP Import Export Lite ===
Contributors: vjinfotech
Tags: export, import, migrate, csv, schedule
Donate link: https://1.envato.market/1krom
Requires at least: 4.4
Tested up to: 7.1
Requires PHP: 5.6
Stable tag: 3.9.35
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete Import & Export solution for Posts, Pages, Custom Posts, Users, Taxonomies, Comments, and more.

== Description ==

WordPress Import Export Plugin is an easy, fast, and advanced tool to import and export your WordPress site data.

WP Import Export gives you the ability to export your site data into multiple file formats and import those files into any WordPress site. All types of Posts, Pages, Custom Post Types, Taxonomies, Comments, and Users can be imported or exported with just a few clicks. It is an effective way to manage and migrate data across multiple WordPress installations.

= Main Features =

- Pause, resume, and stop active imports and exports
- Background processing for large imports and exports
- Scheduled import & export (Available in Pro)
- Powerful data filtering options
- Drag-and-drop field mapping
- Add-on integrations for popular WordPress plugins
- Support for multiple file formats
- Detailed import process logs

= Pause, Resume & Stop =
- Imports and exports can be paused, resumed, and stopped at any stage.
- Supports pause and resume functionality with background processing.
- Cancel or stop any processing task whenever needed.

= Background Import & Export =
- Run import and export tasks asynchronously in the background.
- Background jobs can be monitored, paused, resumed, and stopped.
- Process multiple import and export tasks concurrently.
- Convert standard import/export jobs into background processes with ease.

= Powerful Filters =
- Apply precise filter rules during export (e.g., export posts where Post ID is greater than 50).
- Advanced filtering rules for uploaded source data during import.

= Field Management =
- Rearrange and edit fields quickly before exporting.
- Intuitive drag-and-drop field mapping for incoming import data.

= Multiple File Formats =
- Full support for both import and export across multiple formats.
- Supports ZIP archives.
- Supports CSV, XLS, XLSX, JSON, TXT, ODS, and XML file formats.

= Scheduled Import & Export (Pro) =
- Set up automatic cron-scheduled imports and exports.
- Centralized schedule management interface.
- Runs reliably alongside background processing.

= Import Process Log =
- Comprehensive process and execution logs.
- Step-by-step logging for each individual record.

= Supported Add-Ons =
- Yoast SEO Import & Export
- ACF & ACF Pro Import & Export
- WPML Import & Export
- Polylang Import & Export
- WooCommerce Import & Export (Pro)
- Schedule Import & Export (Pro)
- Attributes Import & Export
- Google Drive Import (Pro)
- Microsoft OneDrive Import (Pro)
- Dropbox Import (Pro)
- FTP / SFTP Import (Pro)
- Background Import & Export

= WP Import Export Professional Edition =
[youtube https://www.youtube.com/watch?v=GZfjyFz1HzM]

= Export Premium Add-Ons =
- ACF / ACF Pro Export
- Scheduled Export
- WooCommerce Export
- WPML Export
- Attributes Export
- Polylang Export

= Import Premium Add-Ons =
- ACF / ACF Pro Import
- Dropbox File Import
- Upload From FTP/SFTP
- Google Drive File Import
- Microsoft OneDrive Import
- Scheduled Import
- WooCommerce Import
- WPML Import
- Attributes Import
- Polylang Import

[Upgrade to the Pro edition of WP Import Export](https://1.envato.market/1krom)

= Live Demo =
[Try the WP Import Export Live Demo](https://demo.vjinfotech.com/wp-import-export/)

= Documentation =
[Documentation and Video Tutorials](https://plugins.vjinfotech.com/wordpress-import-export/documentation/)

== Installation ==

= Automatic Installation =

Automatic installation is the easiest way to install the WP Import Export plugin.

1. Log in to your WordPress admin dashboard.
2. Navigate to **Plugins -> Add New Plugin**.
3. Click **Upload Plugin** at the top.
4. Select the plugin ZIP file and click **Install Now**.
5. Once installed, click **Activate Plugin**.

= Manual Installation =

1. Unzip the downloaded plugin archive.
2. Upload the `wp-import-export-lite` folder to the `/wp-content/plugins/` directory on your web server via SFTP/FTP.
3. Log in to your WordPress dashboard, navigate to **Plugins -> Installed Plugins**, and activate **WP Import Export Lite**.

== Frequently Asked Questions ==

= Does this plugin support PHP 8.x? =
Yes, WP Import Export Lite is fully compatible with PHP 5.6 through PHP 8.5+.

= Can I import data in the background? =
Yes, you can enable background processing to allow large imports or exports to run asynchronously without timing out your browser.

= Where can I get support or view documentation? =
Documentation and tutorials are available at [VJInfotech Documentation](https://plugins.vjinfotech.com/wordpress-import-export/documentation/).

== Screenshots ==
1. Plugin Comparison
2. Export
3. Import
4. Import Process
5. Import Summary
6. Manage Export
7. Extension

== Changelog ==

= 3.9.35 =
* Security: Hardened multisite user import authorization by evaluating capabilities across all network subsites (including archived, spam, and deleted sites).

= 3.9.34 =
* Security: Enhanced directory containment and file validation during export processing.
* Security: Hardened capability and role checks during user import on Multisite installations.
* Security: Strengthened function execution controls in SafeFunction helper.
* Security: Enhanced data sanitization and output escaping in admin interface.
* Fix: Minor UI refinements and stability improvements.

= 3.9.33 =
* Security: Comprehensive security update improving input sanitization, file containment, and permission checks across import and export workflows.
* Fix: Updated deprecated utf8_encode() for PHP 8.2+ compatibility.
* Fix: Maintenance update and compatibility verification with latest WordPress and PHP versions.
* Fix: Minor UI refinements and stability fixes.

== Upgrade Notice ==

= 3.9.35 =
Recommended security and maintenance update addressing multisite user import authorization across network subsites.