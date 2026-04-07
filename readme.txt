=== Simple SES Bridge for Sendy ===
Contributors: gunjanjaswal
Tags: sendy, newsletter, email, amazon-ses, marketing
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://buymeacoffee.com/gunjanjaswal

Create beautiful, responsive newsletters for Sendy with a visual builder. Hero + Grid layout, scheduling, and multi-list support.

== Description ==

**Simple SES Bridge for Sendy** transforms your WordPress dashboard into a professional newsletter creation studio. Forget manual HTML coding—simply search for your posts, drag them into a stunning layout, and send deeply integrated campaigns via Sendy (Amazon SES).

### 🚀 Key Features

*   **🎨 Visual Newsletter Builder:** Drag-and-drop workflow to build emails in seconds.
*   **📱 Fully Responsive Layout:** 
    *   **Hero Section:** Highlights your feature story with a robust, integrated banner.
    *   **Grid System:** Responsive design that displays **2 columns on Desktop** and stacks to a **single column on Mobile** for perfect readability.
    *   **Auto-Height Cards:** Eliminates ugly whitespace on diverse screen sizes (Desktop & Mobile).
*   **🔍 Instant Post Search:** AJAX-powered search lets you find and add any post from your library instantly.
*   **🖼️ Smart Image Handling:** 
    *   Automatic usage of 'Large' thumbnails for crisp quality.
    *   **No-Crop Banners:** Banners display fully (`height: auto`) without cutting off text or faces.
*   **📅 Advanced Scheduling:** 
    *   Schedule campaigns to send at a specific future time.
    *   **Status Tracking:** Clear admin columns showing "Scheduled", "Sent", or "Draft" status.
    *   **Auto-Trigger Cron:** Optional setting to automatically trigger Sendy's processing script after sending (great for shared hosting).
    *   **Error Handling:** Failed campaigns display error messages with one-click retry.
    *   **Auto-Recovery:** Overdue campaigns automatically send when you visit the admin page.
*   **✍️ Custom Footer Area:** 
    *   Add your own text/HTML above the footer (e.g., "Connect with us").
    *   Displays in a **highlighted box** for better visibility.
    *   Supports HTML links and auto-converts newlines to line breaks.
*   **✨ Polished UI:** 
    *   "Read More" buttons for consistent calls to action.
    *   Equal-height cards on desktop for a symmetrical, professional look.
    *   **Admin UI:** Clean, managed interface with clear "Status" and "Scheduled For" columns.
*   **🔒 Secure & Lightweight:** Built with WordPress best practices, ensuring security and minimal performance impact.

### 🔑 Keywords & Tags
Newsletter, Sendy, Amazon SES, Email Marketing, Post to Email, Visual Builder, Drag and Drop, Responsive Email, Newsletter Automation, Blogger Tool, Email Campaign, SES Bridge, WordPress to Sendy, Automated Newsletter, Email Designer.


== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/simple-ses-bridge-for-sendy` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to **Settings > Simple Sendy Bridge** to configure your Sendy options (URL, API Key, Brand ID). Lists are fetched automatically from Sendy — no need to enter List IDs manually.
4.  Go to **Simple Sendy Bridge > Create Newsletter** to start building!

== Frequently Asked Questions ==

= Why didn't my scheduled campaign send on time? =

WordPress uses WP-Cron, which only runs when someone visits your site. If your site has low traffic, scheduled campaigns may be delayed.

**Automatic Recovery:**
The plugin automatically detects and sends overdue campaigns when you visit the Campaigns page. You'll see a success notice confirming the send.

**For Production Sites (Recommended):**
Set up a real cron job to ensure campaigns send exactly on time:
1. Add to `wp-config.php`: `define('DISABLE_WP_CRON', true);`
2. Set up a system cron job (via cPanel or server) to run every minute:
   `* * * * * wget -q -O - https://yourdomain.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1`

= What if a campaign fails to send? =

Failed campaigns will show:
- A red error notice at the top of the Campaigns page
- The exact error message in the "Error" column
- A "Retry Send" button to try again

Common errors include missing Reply-To email (now fixed automatically) or invalid Sendy API credentials.

= Campaign stuck at "Preparing to send..." in Sendy? =

This means Sendy's cron job isn't running. Sendy needs its own cron to process campaigns.

**Quick Fix (Manual Trigger):**
Visit: `https://your-sendy-domain.com/scheduled.php?i=1`

**Permanent Fix (Set Up Sendy Cron):**
Add this cron job to your server (via cPanel or SSH):
`*/5 * * * * php /path/to/sendy/scheduled.php > /dev/null 2>&1`

This runs every 5 minutes to automatically process queued campaigns.



== Screenshots ==

1.  **Newsletter Builder:** Search for posts and see them appear instantly.
2.  **Campaign Settings:** Configure subject, sender, and scheduling options.
3.  **Responsive Email:** See how the layout adapts perfectly from Desktop to Mobile.

== Changelog ==

= 1.1.0 =
*   Feature: Lists & segments are now fetched automatically from Sendy using the `get-lists.php` API (requires Brand ID).
*   Feature: "Choose your lists & segments" checkboxes are populated directly on the Create Newsletter page.
*   Feature: Fetched lists are cached for 10 minutes; use the "Refresh lists from Sendy" link to force an update.
*   Change: Removed the manual "Saved Lists (Optional)" setting — no more copy/pasting list names and IDs.
*   Change: The "List ID" text input on the newsletter builder has been replaced by the auto-populated list selector.

= 1.0.0 =
*   Initial Release.
*   Feature: Visual Newsletter Builder with drag-and-drop post selection.
*   Feature: Hero banner with customizable image upload.
*   Feature: Two layout options: List view and Grid (2-column) view.
*   Feature: Responsive email design with mobile-optimized stacking.
*   Feature: Multi-list support with checkboxes (all lists selected by default).
*   Feature: Sendy API Integration (Draft/Send/Schedule campaigns).
*   Feature: Schedule campaigns with datetime picker (defaults to current time + 1 hour).
*   Feature: "Auto-Trigger Cron" setting to automatically hit `scheduled.php` after sending (supports `?i=BRAND_ID`).
*   Feature: Custom Admin Columns showing campaign status and scheduled time.
*   Feature: Error Display Column for failed campaigns with detailed error messages.
*   Feature: One-Click Retry button for failed campaigns.
*   Feature: Automatic detection and sending of overdue scheduled campaigns.
*   Feature: Test email functionality to preview campaigns before sending.
*   Feature: Customizable footer with logo (auto-links to site home), copyright, social media links.
*   Feature: Custom Footer Text with HTML support in a highlighted box.
*   Feature: Optional "Article Excerpts" support in layout.
*   Fix: Properly parse pipe-separated list format (List Name|List ID) to extract IDs.
*   Fix: Schedule datetime picker with proper z-index for calendar visibility.
*   Fix: Reply-to email correctly passed for all campaign types.
*   Fix: Resolved "cURL error 28: SSL connection timeout".
*   Fix: Disabled strict SSL verification for better compatibility with self-hosted Sendy instances.
*   Security: All inputs properly sanitized and validated.
*   Security: Using wp_safe_redirect() for secure redirects.
*   Security: Timezone-aware datetime formatting with wp_date().

