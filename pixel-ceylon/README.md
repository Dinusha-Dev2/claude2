# Pixel Ceylon theme

Custom WordPress theme for pixelceylontours.com. It renders the site's hand-built
sections directly, with no page builder, and looks the same as the original
Astra + Elementor build.

## Layout

| Path | What it holds |
| --- | --- |
| `inc/routes.php` | Which sections each page shows, in order, keyed by page slug |
| `parts/sections/*.html` | One file per section (HTML, CSS and JS together) |
| `parts/head/*.html` | Font and icon `<link>` tags a section loads from `<head>` |
| `inc/trip-planner.php` | The `[pixel_ceylon_trip_planner]` shortcode and its enquiry email handler |
| `assets/css/base.css` | Document defaults the sections were designed on |
| `assets/js/scroll-top.js` | Scroll-to-top button |

## Editing

- **Change a section:** edit its file in `parts/sections/`.
- **Add a page:** create the page in WordPress, then add its slug and sections
  to `inc/routes.php`. Any page that is not listed falls back to its normal
  WordPress content inside the site header and footer.
- **Trip Planner email:** messages go out through `wp_mail()`, so whichever
  SMTP plugin is configured (GoSMTP) delivers them. No mail credentials live in
  the theme.
