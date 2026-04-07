# 📧 Simple SES Bridge for Sendy

![Version](https://img.shields.io/badge/version-1.5.0-blue.svg) ![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-aaaaaa.svg) ![License](https://img.shields.io/badge/license-GPLv2-green.svg)

**Connect WordPress to Sendy with style.**
Create beautiful, responsive newsletters directly from your WordPress posts and send them via your Sendy installation (Amazon SES).

---

## ✨ Features

### 🎨 Visual Newsletter Builder
*   **Two Newsletter Formats — pick one per campaign:**
    *   **🗞️ The Roundup** — visual hero + 2-column story grid for your subscribers. Uses the "Custom Footer Text" highlighted box.
    *   **✉️ The Insider Brief** — personal greeting, lead paragraph, **centered hero story with featured image**, **centered story cards** for "What Else We're Seeing" (with thumbnails + Read More buttons), "Why this matters" callout, and collaboration CTA. The highlighted box above the footer shows the **About Us** heading + body (left-aligned), replacing the Custom Footer Text used by The Roundup. Built for media pitches & partner updates.
    *   Pick a format per-campaign on the Create Newsletter page (Design Settings → Newsletter Format).
    *   All Insider Brief copy is editable from *Settings → The Insider Brief — Template Texts*. Header and dark footer (logo, social, copyright, unsubscribe) are shared across both formats.
*   **Drag-and-Drop Post Selection:** Search and add posts with a single click.
*   **Article Excerpts:** Optional setting to show short article text before the "Read More" button.
*   **Live Preview:** See exactly what your email will look like as you build it.
*   **Banner Upload:** Add custom hero banners via WordPress Media Library.

### 📱 Mobile-First Responsive Design
*   **Smart Stacking:** Grid items automatically switch to **single-column layout** on mobile (< 600px).
*   **Auto-Height:** Cards adjust dynamically to fit content, removing awkward whitespace.
*   **Readable Typography:** Optimized font sizes and spacing for mobile reading.

### 🖼️ Professional Media Handling
*   **Integrated Banners:** Banner images fully integrated into Hero card with rounded corners.
*   **No-Crop Scaling:** Banners use `height: auto` to ensure 100% visibility.
*   **High-Res Thumbnails:** Uses 'Large' image size for retina displays.

### 📧 Advanced Campaign Management
*   **Auto-Fetched Lists:** Lists & segments are pulled automatically from Sendy (via `get-lists.php`) and shown as checkboxes on the Create Newsletter page — no manual List ID entry. Cached for 10 minutes with a one-click refresh link.
*   **Multi-List Support:** Send to multiple Sendy lists with checkboxes (all selected by default).
*   **Three Send Options:**
    *   **Save as Draft** - Create draft in Sendy for later editing
    *   **Send Immediately** - Send campaign right away
    *   **Schedule** - Set date/time for automatic sending (defaults to current time + 1 hour)
*   **Auto-Trigger Cron:** Optional setting to automatically trigger Sendy's processing script after sending (great for shared hosting).
*   **Test Email:** Preview campaigns in your inbox before sending to subscribers.
*   **Status Tracking:** View `Draft`, `Sent`, or `Scheduled` status in campaign list.
*   **Error Handling:** Failed campaigns show detailed error messages in dedicated column.
*   **One-Click Retry:** Instantly retry failed campaigns with a single button.
*   **Automatic Recovery:** Overdue scheduled campaigns are automatically detected and sent.

### 🎨 Customizable Footer
*   **Custom Text Area:** Add rich text (HTML/Links) in a **highlighted box** above the footer. Great for call-to-actions.
*   **Logo Upload:** Add your brand logo (automatically links to your website homepage).
*   **Copyright Text:** Customize copyright notice.
*   **Social Media Links:** Add Instagram, LinkedIn, X (Twitter), YouTube links.
*   **Read More Link:** Direct subscribers to your website or blog.

### 🏷️ Keywords
`Sendy` `Amazon SES` `Newsletter Builder` `Email Marketing` `WordPress Newsletter` `Drag and Drop` `Post to Email` `Responsive Email Template` `Email Automation` `Campaign Scheduler`

---

## 🚀 Installation

### 💻 Via WordPress Dashboard

1.  **Download** the latest plugin `.zip` file from the [Releases page](https://github.com/gunjanjaswal/Simple-SES-Bridge-for-Sendy/releases) or the `Code > Download ZIP` button on GitHub.
2.  Go to **Plugins > Add New** in your WordPress admin panel.
3.  Click **Upload Plugin** at the top.
4.  Choose the downloaded `.zip` file and click **Install Now**.
5.  Click **Activate Plugin**.

### 🔧 Via Git (For Developers)

1.  Open your terminal and navigate to your WordPress plugins directory:
    ```bash
    cd wp-content/plugins/
    ```
2.  Clone the repository:
    ```bash
    git clone https://github.com/gunjanjaswal/Simple-SES-Bridge-for-Sendy.git simple-ses-bridge-for-sendy
    ```
3.  Activate the plugin through the **Plugins** menu in WordPress.

### ⚙️ Final Setup (Both Methods)

4.  **Configure** your Sendy details in *Settings > Simple Sendy Bridge*:
    *   Sendy Installation URL
    *   API Key
    *   Brand ID (required for auto-fetching lists)

    Lists are fetched automatically from Sendy and appear as checkboxes on the Create Newsletter page — you no longer need to enter List IDs manually.

## 📖 Usage

1.  Navigate to **Simple Sendy Bridge > Create Newsletter**.
2.  **Select a Banner:** Choose an image from your Media Library.
3.  **Add Posts:** Search for your articles and click "Add".
    *   1st Post = **Hero**
    *   2nd+ Posts = **Grid**
4.  **Preview:** Check the preview column.
5.  **Send or Schedule:** Choose to Send Now, Save as Draft (in Sendy), or Schedule for later.

---

## 🔧 Troubleshooting

### Scheduled Campaigns Not Sending on Time?

**Why:** WordPress uses WP-Cron, which only triggers when someone visits your site. Low-traffic sites may experience delays.

**Automatic Recovery:**
- The plugin **automatically detects and sends** overdue campaigns when you visit the Campaigns page
- You'll see a green success notice: *"Overdue Campaign Sent: [Campaign Name] was automatically sent."*
- If auto-send fails, you'll see the error message with a **"Retry Send"** button

**Permanent Solution (Recommended for Production):**

1. **Disable WP-Cron** by adding this to `wp-config.php`:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. **Set up a real cron job** (via cPanel, Plesk, or server SSH):
   ```bash
   * * * * * wget -q -O - https://yourdomain.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```
   
   Or using `curl`:
   ```bash
   * * * * * curl https://yourdomain.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

### Campaign Failed to Send?

Failed campaigns will display:
- ❌ Red error notice at the top of the Campaigns page
- 📋 Exact error message in the "Error" column
- 🔄 **"Retry Send"** button for instant retry

**Common Errors:**
- **"Reply to email not passed"** - Fixed automatically in v1.0.0+
- **"Invalid API key"** - Check your Sendy settings
- **"List ID is required"** - Verify your default list ID in settings

### Campaign Stuck at "Preparing to send..." in Sendy?

**Why:** Sendy requires its own cron job to process and send campaigns. Without it, campaigns get queued but never send.

**Quick Fix (Manual Trigger):**
```
https://your-sendy-domain.com/scheduled.php?i=1
```
Replace `your-sendy-domain.com` with your actual Sendy URL. This manually triggers campaign processing.

**Permanent Fix (Set Up Sendy Cron):**

Add this cron job to your server:

**Via cPanel:**
1. Go to **cPanel > Cron Jobs**
2. Set to run every 5 minutes: `*/5 * * * *`
3. Command:
   ```bash
   php /home/yourusername/public_html/sendy/scheduled.php > /dev/null 2>&1
   ```

**Via SSH:**
```bash
crontab -e
```
Add this line:
```bash
*/5 * * * * php /path/to/sendy/scheduled.php > /dev/null 2>&1
```

This automatically processes queued campaigns every 5 minutes.



---
 
 ## 📋 Changelog

 ### v1.5.0
 *   **Feature:** "Add Posts" panel now supports **infinite scroll** — older posts load automatically as you scroll the results list (10 at a time).
 *   **Internal:** `sssb_search_posts` AJAX endpoint now accepts a `page` parameter and returns `{ posts, page, has_more }`.

 ### v1.4.1
 *   **Improvement:** "What Else We're Seeing" stories in The Insider Brief now use the same 2-column responsive grid as The Roundup.
 *   **Improvement:** "About Us" heading in The Insider Brief footer block is now centered.

 ### v1.4.0
 *   **Fix:** Bumped plugin version so the updated `script.js` is force-reloaded by browsers (cache bust). All 1.3.1 layout improvements now take effect.
 *   **Improvement:** "The Roundup" hero now also pulls in the post's featured image when no banner is uploaded.

 ### v1.3.1
 *   **Improvement:** "The Insider Brief" hero story is centered, pulls in the post's featured image, and uses a centered "Read More" button.
 *   **Improvement:** "What Else We're Seeing" stories now render as individual centered cards with featured images, centered titles & excerpts, and a centered "Read More" button (instead of a bulleted list).
 *   **Change:** In "The Insider Brief", the highlighted box above the dark footer now shows the **About Us** heading + body (replacing the Custom Footer Text used by The Roundup). Same box styling, but the heading is left-aligned.
 *   **UI:** Design Settings card moved to the top of the Create Newsletter page, with **Newsletter Format** as the first field above Banner Image.

 ### v1.3.0
 *   **Feature:** New **"The Insider Brief"** newsletter template — personal greeting + intro + hero story + "Why this matters" callout + collaboration CTA + About Us. Built for media pitches and partner updates.
 *   **Feature:** Original layout renamed to **"The Roundup"** (hero + grid). Pick a format per-campaign on the Create Newsletter page.
 *   **Feature:** All Insider Brief text blocks are editable from *Settings → The Insider Brief — Template Texts*.
 *   **Note:** Header, footer and Custom Footer Text continue to render across both formats.

 ### v1.2.0
 *   **Feature:** Active subscriber count shown next to each list on the Create Newsletter page.
 *   **Feature:** Lists are unchecked by default; the plugin remembers your last selection and pre-checks those lists next time.
 *   **Change:** Selected list IDs are persisted on every successful campaign (draft / send / schedule).

 ### v1.1.0
 *   **Feature:** Auto-fetch lists & segments directly from Sendy via `api/lists/get-lists.php` (requires Brand ID).
 *   **Feature:** "Choose your lists & segments" checkboxes populate automatically on the Create Newsletter page.
 *   **Feature:** Fetched lists cached for 10 minutes; "Refresh lists from Sendy" link forces a refresh.
 *   **Change:** Removed the manual "Saved Lists (Optional)" setting.
 *   **Change:** Replaced the manual List ID text input with the auto-populated list selector.

 ### v1.0.0
 *   **Initial Release**
 *   **Feature:** Visual Newsletter Builder with drag-and-drop.
 *   **Feature:** Responsive Grid Layout (Desktop/Mobile).
 *   **Feature:** Optional "Article Excerpts" feature in the drag-and-drop builder.
 *   **Feature:** Custom Footer Text context with highlighted box.
 *   **Feature:** Footer logo auto-link to homepage.
 *   **Fix:** Resolved "cURL error 28: SSL connection timeout".
 *   **Fix:** Disabled strict SSL verification for self-hosted Sendy compatibility.
 *   **Docs:** Updated GitHub installation documentation.
 
 ---
 
 ## 🤝 Support

Created by **Gunjan Jaswal**.
