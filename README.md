# Coach Masters Academy MENA — landing page, thank-you page and CMS

A self-contained lead-generation site: no database, no build step, no external services.
Upload the folder, open `/admin/`, set a password, and it is live.

## What is inside

| Path | What it is |
|---|---|
| `index.php` | The landing page |
| `thank-you/` | The page visitors see after sending the form (not indexed) |
| `admin/` | The CMS: edit all content, SEO, tracking, images, and manage leads |
| `submit.php` | Receives the form, saves the lead, sends notifications |
| `sitemap.php`, `robots.php`, `llms.php` | Served as `/sitemap.xml`, `/robots.txt`, `/llms.txt` |
| `inc/` | Shared code, default content (`defaults.php`) and the list of editable fields (`schema.php`) |
| `data/` | Saved content, leads and settings (protected from direct access) |
| `uploads/` | Images uploaded through the CMS |
| `assets/` | Styles, script, fonts and images |

## Requirements

- PHP 7.4 or newer (8.1+ recommended) with the standard `mbstring`, `fileinfo`, `json` and `session` extensions
- Apache or LiteSpeed with `.htaccess` enabled (any cPanel-style hosting). For nginx, see the note at the end.
- The `data/` and `uploads/` folders must be writable by PHP

## Install

1. Upload the contents of this folder to the web root (or a sub-folder) of the domain.
   If you deploy from Git, clone the repository there instead.
2. Open `https://your-domain/admin/`. The first visit shows **Create your admin account**.
3. Enter the setup key, then choose the email or username and the password (at least 10 characters) you will sign in with.

   **Where is the setup key?** Open `data/config.json.php` on the server (hosting file manager or FTP) and copy the value of `"setup_key"`.
   The file is created automatically the first time `/admin/` is opened, the key works once, and it is removed after the account is created.
   Do this straight after uploading.
4. Work through "Before you launch" on the admin Overview screen:
   - **Lead notification emails** — add every address that should receive new leads (Settings → Lead notifications), then press **Send test email**
   - **Site URL** — the final public address, used for the canonical tag, sitemap and sharing (SEO & sharing)
   - **Tracking** — GTM, GA4, Meta Pixel and Google Ads IDs (Tracking & pixels)
5. Send a test enquiry from the landing page and check it appears under Leads.

If the page is first uploaded to a staging address, turn off "Allow search engines to index the landing page"
under SEO & sharing, and turn it back on at launch.

## Signing in

- `/admin/` is the sign-in page: email or username plus password. Five wrong attempts lock sign-in for 15 minutes.
- Change the username or password under Settings → Sign-in details.
- Forgotten password: delete `data/config.json.php` on the server and reload `/admin/` to create new sign-in details. Content and leads are not affected.

## Working with Git

Saved content, leads, the admin account and uploaded images live in `data/` and `uploads/`, which are excluded from Git
(see `.gitignore`). Pulling a new version of the code therefore never overwrites what was edited in the CMS, and no lead
data or credentials ever reach the repository. Back those two folders up separately.

## How leads are handled

- Every enquiry is saved under **Leads** with its source (UTM parameters, gclid/fbclid, referrer), preferred cohort and message.
- Each lead has a status (New, Contacted, Qualified, Enrolled, Not proceeding) and internal notes.
- **Download all as CSV** exports everything; the file opens correctly in Excel, including Arabic names.
- **Who is emailed:** Settings → Lead notifications holds the list of recipients. Add or remove addresses at any time; each one receives every new lead, with the visitor's address as reply-to. "Send test email" checks delivery.
- Optional: a confirmation email to the visitor, and a webhook (JSON POST) for a CRM, Google Sheets, Zapier or Make.
- Email is sent with PHP `mail()`. Set "Send from" to an address on the site's own domain, and make sure the domain has SPF/DKIM, otherwise messages may land in spam.
- Spam protection: hidden honeypot field, signed time check and a per-visitor rate limit. No CAPTCHA, so real visitors are never slowed down.

## Conversion tracking

The lead event fires once, on the thank-you page, only after a real submission (a refresh does not count twice):

- `dataLayer` event `generate_lead` (with `form_location`, `cohort`, `lead_id`) for Google Tag Manager
- GA4 event `generate_lead`
- Meta Pixel `Lead`
- Google Ads conversion (when the conversion ID and label are filled in)

If tags are managed in GTM, fill in only the GTM ID to avoid double counting.

## Editing content

Everything on both pages is editable in the admin panel, grouped by section. Notes:

- **Dates & cohorts** — the hero, the form and the structured data always use the next upcoming cohort; past cohorts drop off automatically.
- **Images** — upload from any image field, or from Settings → Images. JPG, PNG, WebP, AVIF or GIF, up to 5 MB.
- **Restore the original content** — available at the bottom of every section.
- To add a completely new field or section, add it to `inc/schema.php` and `inc/defaults.php`, then output it in `index.php`.

## SEO, as delivered

- Meta title: `ICF Life Coach Certification | Coach Masters Academy MENA` (57 characters)
- Meta description: `ICF-accredited life coach certification for the Middle East & Gulf. 70 hours of live virtual training over 16 weeks with Lina Cherry, MCC. Register now.` (152 characters)
- One H1, semantic sections, descriptive image file names and alt text, canonical tag, Open Graph and X card tags with a 1200 × 630 sharing image
- Structured data: WebPage, EducationalOrganization, Person, Course with upcoming CourseInstances, and FAQPage — all generated from the CMS content
- `/sitemap.xml`, `/robots.txt` and `/llms.txt` generated automatically
- Self-hosted fonts, AVIF images with WebP fallback, no JavaScript framework

## Security notes

- The admin password is stored hashed; sign-in is rate-limited; sessions expire after two hours of inactivity.
- Both pages and every admin screen are responsive and were checked from 320 px to 768 px wide: no sideways scrolling, 16 px form fields (no zoom on iPhone), touch targets of at least 44 px.
- Files in `data/` begin with a PHP guard line and are also blocked by `.htaccess`, so leads cannot be downloaded directly.
- Uploaded files are checked by content type, renamed, and cannot run as code.
- Use HTTPS. Keep a regular backup of the `data/` and `uploads/` folders — they hold everything that changes.

## nginx

Add these rules to the server block (adjust the PHP handler to your setup):

```nginx
location ~ ^/(data|inc)/ { deny all; }
location = /sitemap.xml { rewrite ^ /sitemap.php last; }
location = /robots.txt  { rewrite ^ /robots.php last; }
location = /llms.txt    { rewrite ^ /llms.php last; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
```
