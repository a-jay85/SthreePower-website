# SthreePower — Website

The homepage for **SthreePower®**, a global community where women build real skills, grow a professional network, and earn their way to financial independence.

This is the **Twilight Editorial** design direction: a dusk-dominant, editorial layout built on the locked Dusk & Ivory color system.

## Tech

Plain, static HTML/CSS/JS. No build step, no dependencies, no framework. Fonts (Newsreader, Hanken Grotesk, Space Mono) load from Google Fonts over CDN. Everything else — the dusk gradient, the horizon line, the icons — is hand-authored CSS and SVG, so there are no image assets to manage.

## Project structure

```
sthreepower-site/
├── index.html      # the entire homepage (markup + styles + script, self-contained)
├── README.md
└── .gitignore
```

## Preview locally

It's a single static file, so you can just open it:

```
open index.html          # macOS
# or double-click the file
```

For a closer-to-production preview (correct relative paths, no file:// quirks), serve it:

```
python3 -m http.server 8000
# then visit http://localhost:8000
```

## Deploy

Because it's static, almost any host works. A few options while you finalize hosting:

- **GitHub Pages** — push this repo, then in the repo's **Settings → Pages**, set the source to "Deploy from a branch", branch `main`, folder `/ (root)`. The site goes live at `https://<you>.github.io/<repo>/` within a minute or two. (`index.html` at the root is exactly what Pages expects.)
- **Netlify / Vercel / Cloudflare Pages** — drag the folder in, or connect the repo. No build command needed; the publish directory is the root.
- **Any web host / S3 / nginx** — upload `index.html` to the web root.

## Still to do before launch

- **Replace placeholder content.** All testimonials, member names, and figures are placeholder copy for layout. Swap in real quotes and numbers.
- **Wire up the links.** Nav links and the "Join free on WhatsApp" CTAs currently point to `#`. Point them at the real destinations.
- **Add a favicon** (currently none — browsers will show a default).
- **Add Open Graph / social-share tags** once you have a share image, so links preview nicely.
- **Confirm the footer details** (address and phone) are current.

## Notes

- The "Vinodha & Banumathi" services tile names the two flagship initiatives as placeholders; update copy as those programs firm up.
- No license file is included — by default this is "all rights reserved." Add one only if you intend to open-source it.
