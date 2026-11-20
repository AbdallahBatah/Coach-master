<?php
/**
 * Describes every editable field for the admin panel. Adding a field here (and a default
 * in defaults.php) is all it takes to make something new editable.
 *
 * Field types: text, textarea, lines (one item per line), url, email, date, color, toggle,
 * image, code, select (with 'options'), list (repeatable rows with their own 'fields').
 */
$t = fn(string $label, string $help = '', array $extra = []) => ['type' => 'text', 'label' => $label, 'help' => $help] + $extra;
$ta = fn(string $label, string $help = '', array $extra = []) => ['type' => 'textarea', 'label' => $label, 'help' => $help] + $extra;
$on = ['type' => 'toggle', 'label' => 'Show this section on the page'];

return [
    'Landing page' => [
        'hero' => ['label' => 'Hero', 'intro' => 'The first screen visitors see, including the top form. Its field labels are shared with the bottom form and are edited under Contact & form.', 'fields' => [
            'tagline' => $t('Tagline above the headline'),
            'title' => $t('Headline (H1)', 'The main heading of the page. Keep the primary keyword in it.'),
            'text' => $ta('Introduction'),
            'facts' => ['type' => 'list', 'label' => 'Key facts', 'item' => 'fact', 'fields' => ['value' => $t('Value'), 'label' => $t('Label')]],
            'next_label' => $t('Label before the next cohort date', 'The date itself comes from the Dates section.'),
            'form_title' => $t('Form title'),
            'form_text' => $ta('Form introduction'),
            'form_button' => $t('Form button'),
            'show_path' => ['type' => 'toggle', 'label' => 'Show the Awareness → Clarity → Choice line', 'help' => 'Uses the first three steps of the Framework section.'],
        ]],
        'outcomes' => ['label' => 'What to expect', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'items' => ['type' => 'lines', 'label' => 'Outcomes', 'help' => 'One per line.'],
            'closing' => $ta('Closing statement'),
        ]],
        'program' => ['label' => 'Program', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'lead' => $ta('Lead paragraph'),
            'text' => $ta('Supporting paragraph'),
            'phases' => ['type' => 'list', 'label' => 'Phases', 'item' => 'phase', 'fields' => [
                'name' => $t('Name'), 'title' => $t('Title'), 'duration' => $t('Duration'), 'text' => $t('Short description'),
            ]],
            'cta_label' => $t('Link to the dates section', 'Leave empty to hide.'),
        ]],
        'framework' => ['label' => 'Framework', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'text' => $ta('Description'),
            'steps' => ['type' => 'list', 'label' => 'Steps', 'item' => 'step', 'fields' => [
                'name' => $t('Name'), 'question' => $t('Question'), 'text' => $ta('Description'),
            ]],
            'image' => ['type' => 'image', 'label' => 'Framework diagram'],
            'image_alt' => $t('Diagram alt text', 'Describe the image for screen readers and search engines.'),
            'image_caption' => $t('Diagram caption'),
            'video_url' => ['type' => 'url', 'label' => 'Video link', 'help' => 'YouTube or Vimeo link. Leave empty to hide the video.'],
            'video_title' => $t('Video title'),
        ]],
        'accreditation' => ['label' => 'Accreditation', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'lead' => $ta('Lead paragraph'),
            'text' => $ta('Supporting paragraph'),
            'competencies' => ['type' => 'lines', 'label' => 'Core competencies', 'help' => 'One per line.'],
            'points' => ['type' => 'lines', 'label' => 'Points beside the badges', 'help' => 'One per line.'],
            'badge_1' => ['type' => 'image', 'label' => 'Badge 1 (also shown in the hero)'],
            'badge_1_alt' => $t('Badge 1 alt text'),
            'badge_2' => ['type' => 'image', 'label' => 'Badge 2 (also shown in the hero)'],
            'badge_2_alt' => $t('Badge 2 alt text'),
        ]],
        'director' => ['label' => 'Regional director', 'fields' => [
            'enabled' => $on,
            'role' => $t('Role'),
            'name' => $t('Name'),
            'credential' => $t('Credential'),
            'bio' => $ta('Biography', 'Leave an empty line between paragraphs.', ['rows' => 10]),
            'photo' => ['type' => 'image', 'label' => 'Photo'],
            'photo_alt' => $t('Photo alt text'),
            'cta_label' => $t('Button label', 'Leave empty to hide.'),
        ]],
        'dates' => ['label' => 'Dates & cohorts', 'intro' => 'Cohorts disappear from the page automatically once their start date has passed. The hero and the form always show the next one.', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'text' => $ta('Introduction'),
            'items' => ['type' => 'list', 'label' => 'Cohorts', 'item' => 'cohort', 'fields' => [
                'start' => ['type' => 'date', 'label' => 'Start date'],
                'end' => ['type' => 'date', 'label' => 'End date'],
                'hours' => $t('Hours'),
                'duration' => $t('Duration'),
                'note' => $t('Note', 'Optional, e.g. "Limited seats".'),
            ]],
            'cta_label' => $t('Button label'),
            'empty_text' => $ta('Text when no cohort is scheduled'),
        ]],
        'testimonials' => ['label' => 'Testimonials', 'intro' => 'The first testimonial is shown large. Short quotes work best there.', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'items' => ['type' => 'list', 'label' => 'Testimonials', 'item' => 'testimonial', 'fields' => [
                'quote' => $ta('Quote'), 'name' => $t('Name'), 'country' => $t('Country'),
            ]],
        ]],
        'faq' => ['label' => 'FAQ', 'intro' => 'Questions and answers also feed the FAQ structured data and llms.txt.', 'fields' => [
            'enabled' => $on,
            'title' => $t('Title'),
            'items' => ['type' => 'list', 'label' => 'Questions', 'item' => 'question', 'fields' => [
                'q' => $t('Question'), 'a' => $ta('Answer'),
            ]],
        ]],
        'contact' => ['label' => 'Contact & form', 'fields' => [
            'title' => $t('Title'),
            'text' => $ta('Introduction'),
            'phone' => $t('Phone'),
            'email' => ['type' => 'email', 'label' => 'Email shown on the page'],
            'whatsapp_enabled' => ['type' => 'toggle', 'label' => 'Show WhatsApp buttons'],
            'whatsapp_number' => $t('WhatsApp number', 'International format, digits only, e.g. 96170864845.'),
            'whatsapp_message' => $t('Pre-filled WhatsApp message'),
            'whatsapp_label' => $t('WhatsApp button label'),
            'form_title' => $t('Form title'),
            'form_button' => $t('Form button'),
            'label_name' => $t('Label: name'),
            'label_email' => $t('Label: email'),
            'label_country' => $t('Label: country'),
            'label_phone' => $t('Label: phone'),
            'label_cohort' => $t('Label: cohort'),
            'label_message' => $t('Label: message'),
            'placeholder_message' => $t('Message placeholder'),
            'default_country' => $t('Default country', 'Two-letter code, e.g. LB, AE, SA, QA, KW, JO, EG.'),
            'privacy_note' => $t('Note under the button'),
            'sticky_label' => $t('Sticky button on mobile'),
        ]],
        'header' => ['label' => 'Header & menu', 'fields' => [
            'links' => ['type' => 'list', 'label' => 'Menu links', 'item' => 'link', 'fields' => [
                'label' => $t('Label'),
                'target' => $t('Target', '#program, #framework, #accreditation, #lina-cherry, #enrolment, #testimonials, #faq or #register.'),
            ]],
            'cta_label' => $t('Button label', 'Leave empty to hide.'),
        ]],
        'footer' => ['label' => 'Footer', 'fields' => [
            'text' => $ta('Short text under the logo'),
            'copyright' => $t('Copyright line', '{year} is replaced with the current year.'),
            'links' => ['type' => 'list', 'label' => 'Extra links', 'item' => 'link', 'fields' => [
                'label' => $t('Label'), 'url' => ['type' => 'url', 'label' => 'URL'],
            ]],
        ]],
    ],
    'Thank-you page' => [
        'thankyou' => ['label' => 'Thank-you page', 'intro' => 'Shown after a visitor sends the form. It is never indexed by search engines.', 'fields' => [
            'meta_title' => $t('Browser title'),
            'title' => $t('Headline', '{name} is replaced with the visitor\'s first name.'),
            'title_no_name' => $t('Headline when the name is unknown'),
            'text' => $ta('Message'),
            'steps_title' => $t('Steps title'),
            'steps' => ['type' => 'list', 'label' => 'What happens next', 'item' => 'step', 'fields' => [
                'title' => $t('Title'), 'text' => $ta('Description'),
            ]],
            'whatsapp_text' => $t('Text before the WhatsApp button'),
            'back_label' => $t('Back link label'),
            'show_dates' => ['type' => 'toggle', 'label' => 'Show upcoming cohort dates'],
        ]],
    ],
    'Search & tracking' => [
        'seo' => ['label' => 'SEO & sharing', 'fields' => [
            'title' => $t('Meta title', 'Aim for 50–60 characters with the main keyword first.', ['count' => 60]),
            'description' => $ta('Meta description', 'Aim for 140–155 characters. Say what the visitor gets and what to do next.', ['count' => 155, 'rows' => 3]),
            'site_url' => ['type' => 'url', 'label' => 'Site URL', 'help' => 'The final public address, e.g. https://coachmastersacademymena.com/ — used for the canonical tag, sitemap and sharing. Leave empty to detect it automatically.'],
            'site_name' => $t('Site name'),
            'indexable' => ['type' => 'toggle', 'label' => 'Allow search engines to index the landing page', 'help' => 'Turn off while the page is on a staging address.'],
            'og_title' => $t('Sharing title', 'Shown when the link is shared on WhatsApp, LinkedIn, Facebook and X.'),
            'og_description' => $ta('Sharing description', '', ['rows' => 2]),
            'og_image' => ['type' => 'image', 'label' => 'Sharing image', 'help' => '1200 × 630 px, JPG or PNG.'],
            'language' => $t('Language code', 'e.g. en or ar.'),
            'region' => $t('Region served', 'Used in structured data.'),
        ]],
        'tracking' => ['label' => 'Tracking & pixels', 'intro' => 'The lead event (generate_lead, Meta "Lead", Google Ads conversion) fires once on the thank-you page after a real submission. If you manage tags in Google Tag Manager, fill in only the GTM ID.', 'fields' => [
            'gtm_id' => $t('Google Tag Manager ID', 'Format: GTM-XXXXXXX'),
            'ga4_id' => $t('Google Analytics 4 ID', 'Format: G-XXXXXXXXXX'),
            'meta_pixel_id' => $t('Meta Pixel ID', 'Digits only.'),
            'google_ads_id' => $t('Google Ads conversion ID', 'Format: AW-XXXXXXXXX'),
            'google_ads_label' => $t('Google Ads conversion label'),
            'head_code' => ['type' => 'code', 'label' => 'Custom code in <head>', 'help' => 'For verification tags or other scripts. Added to every page as written.'],
            'body_code' => ['type' => 'code', 'label' => 'Custom code after <body>'],
        ]],
    ],
    'Settings' => [
        'brand' => ['label' => 'Logo & colours', 'fields' => [
            'logo' => ['type' => 'image', 'label' => 'Logo', 'help' => 'Shown on a white background.'],
            'logo_alt' => $t('Logo alt text'),
            'favicon' => ['type' => 'image', 'label' => 'Favicon', 'help' => 'Square PNG, at least 192 × 192 px.'],
            'color_navy' => ['type' => 'color', 'label' => 'Navy (backgrounds and headings)'],
            'color_sky' => ['type' => 'color', 'label' => 'Sky blue (accents)'],
            'color_orange' => ['type' => 'color', 'label' => 'Orange (accents)'],
            'color_button' => ['type' => 'color', 'label' => 'Button colour', 'help' => 'Keep it dark enough for white text to stay readable.'],
        ]],
        'notifications' => ['label' => 'Lead notifications', 'intro' => 'Every enquiry is saved under Leads. These settings decide who is emailed about it.', 'fields' => [
            'recipients' => ['type' => 'list', 'label' => 'Who receives new leads', 'item' => 'email address', 'require' => 'email', 'help' => 'Everyone listed here gets an email each time a visitor sends the form. Add as many addresses as you need.', 'fields' => [
                'email' => ['type' => 'email', 'label' => 'Email address'],
                'name' => $t('Name (optional)', 'Only to help you recognise the address.'),
            ]],
            'from_email' => ['type' => 'email', 'label' => 'Send from', 'help' => 'Use an address on this website\'s own domain so emails are not marked as spam. Leave empty for no-reply@your-domain.'],
            'from_name' => $t('Sender name'),
            'autoreply_enabled' => ['type' => 'toggle', 'label' => 'Send a confirmation email to the visitor'],
            'autoreply_subject' => $t('Confirmation subject'),
            'autoreply_body' => $ta('Confirmation message', '{name} and {first_name} are replaced with the visitor\'s name.', ['rows' => 9]),
            'webhook_url' => ['type' => 'url', 'label' => 'Webhook URL', 'help' => 'Optional. Each lead is also posted as JSON to this https address — use it to feed a CRM, Google Sheets, Zapier or Make.'],
        ]],
    ],
];
