---
created: 2026-10-22
tags: [research, design, design-system, reference, accessibility, wcag, tailwind, visual-language]
status: draft
---

# Jiron Dashboard — Visual Style Research & Feasibility for OptiWorks

> **Scope:** read-only design research. Identify the design vocabulary behind the "Jiron" time-tracker dashboard reference, verify every technical claim against primary sources, audit the current `opti-works` visual language, and give an ADOPT / ADAPT / SKIP verdict per design element — with a WCAG 2.2 contrast audit.
>
> **Status:** research/draft. **No application code was changed.** This file is the only write.
>
> **Method note:** the reference was delivered to this agent as a *written description*, not as an image. No image pixels were inspected. Every statement about the reference's appearance is therefore sourced to the task brief, and is marked `[SPEC]`. Hex values for the reference's saturated stops come from the brief; the brief's *light* gradient stops are qualitative ("light lavender", "light periwinkle", …) and are `[UNVERIFIED]` — for contrast computation the closest Tailwind default palette steps were substituted and are labelled as assumptions.
>
> **Evidence legend:**
> - **[CODE]** behavior verified in this repository (file:line).
> - **[DOC]** verified against official documentation (URL given).
> - **[SPEC]** appearance of the reference as described in the task brief; not independently verifiable.
> - **[CALC]** contrast ratio computed with the WCAG 2.x relative-luminance formula from W3C.
> - **[UNVERIFIED]** could not be confirmed from any available source.

---

## 1. Executive Summary

1. **The reference is a "2021-era playful-SaaS / glassmorphism" style, not a design system.** Its vocabulary maps to **Soft UI / neumorphism** (the soft-light shadow look), **glassmorphism** (translucent floating card, background blur), **pastel-gradient card** (the 4-hue kanban headers), and — for the overall shell — the **"floating app window over a saturated gradient"** pattern that preceded Apple's 2025 *Liquid Glass* and is now being re-litigated as *Liquid Glass on the web*. Sources in §3.
2. **The reference's source could not be found. [UNVERIFIED]** The web-search tool returned zero results for every query, and DuckDuckGo / Brave / Mojeek / Ecosia / Startpage / searx all refused or challenge-blocked the request; GitHub code+repo search for `jiron` + time tracker returned nothing relevant. **The identity of the "Jiron" designer or product is unverified and is not asserted anywhere in this report.** See §4.
3. **The current app already implements most of the reference's *structure*, in a deliberately different *palette*.** Grouped sidebar, segmented control, kanban/status columns, avatar stack, progress bars, circular icon buttons, white cards on a canvas — all already exist. The divergence is **colour and warmth**, not layout. §6.
4. **The reference's palette directly violates this project's own design authority.** `DESIGN.md:454` and `DESIGN_BRIEF.md:44` both name "purple / indigo SaaS gradients" as an explicit anti-goal, and `DESIGN_BRIEF.md:49` forbids Inter/Roboto/system-ui as the brand voice. Adopting the reference wholesale is a **design-authority change**, not an implementation task. §5.2, §7.
5. **The reference fails WCAG 2.2 in at least nine places.** White text on the light gradient stops measures 1.25:1–1.85:1 (needs 4.5:1, or 3:1 for large text); the hairline borders measure 1.23:1–1.24:1 against white (needs 3:1 as UI-component boundaries); the "today" column band measures 1.10:1. §8.
6. **Verdict: implementable, but only as an ADAPT.** Roughly 55 % of the reference's structure maps onto existing components; roughly 15 % of its surface treatment (gradients, floating shell, 4-hue family) conflicts with `DESIGN.md` and should not be adopted; the timeline/calendar grid is a large greenfield feature with no data model behind it yet.

---

## 2. Method & Source Constraints

| Action | Result |
| --- | --- |
| `websearch` tool — 5 queries (design vocabulary, "Jiron" + Dribbble/Behance/Mobbin, "Yaros Tolstykh", WCAG thresholds) | **All returned "No search results found"** — the search integration is non-functional in this environment. |
| DuckDuckGo (`html.`, `lite.`), Brave, Mojeek, Ecosia, Startpage, searx.be — via `webfetch` | Transport error, HTTP 403, HTTP 429, or JS/browser challenge. **No usable web index.** |
| Bing via `webfetch` | Returned HTTP 200 but with **semantically unrelated cached results** (Chinese Baidu/zhihu pages for an unrelated query). Treated as unusable. |
| GitHub MCP `search_repositories` / `search_code` for `jiron`, `"Jiron" time tracker` | 171 `jiron`-named repos and 1128 code hits, **none** a Jiron time-tracker UI. |
| Direct fetches of primary sources (w3.org, en.wikipedia.org, developer.apple.com, tailwindcss docs via Context7, GitHub API) | **Succeeded.** All citations below come from these. |
| Tailwind utility existence | Verified by **compiling against the installed `node_modules/tailwindcss@4.3.3`** (see §7.1), not by recollection. |

**Consequence:** this report is strong on *what can be built* (repo audit + official docs + computed contrast) and explicitly weak on *who made the reference*.

---

## 3. Design Vocabulary — What Established Names Does This Map To?

### 3.1 Mapping table

| Reference element | Established name | Why it maps | Source |
| --- | --- | --- | --- |
| Soft, light, protruding/dented elements; pastel low-contrast surfaces | **Neumorphism** ("commonly identified by a soft and light look … sometimes referred to as *soft UI*") | Term coined by Jason Kelly, 2019, as neo + skeuomorphism; a middle ground between skeuomorphism and flat design. | https://en.wikipedia.org/wiki/Neumorphism |
| Floating translucent app card, backdrop blur, thin outline, rounded edges | **Glassmorphism** | "semi-transparent panels, soft lighting, depth effects, and background blur"; term coined by Michal Malewicz, 2020; a successor/evolution of neumorphism. | https://en.wikipedia.org/wiki/Glassmorphism |
| Gradient page ground + inset floating rounded window + depth hierarchy | **Liquid Glass (on the web)** | Apple's 2025 design language: "a fluid, dynamic glass-like interface that reflects and refracts the background"; browsers "approximate the effect's refraction, translucency and lensing using techniques such as CSS `backdrop-filter`". The reference is the pre-Liquid-Glass ancestor of this same idea. | https://en.wikipedia.org/wiki/Liquid_Glass · https://developer.apple.com/design/ |
| 4 pastel→saturated gradient cards, one per column | **Pastel / gradient mesh card** (industry term, no single normative spec) | Each card is a two-stop linear gradient with white text; the "mesh" family is the same technique applied as a continuous field. | Descriptive — **[UNVERIFIED]** as a formalised spec |
| Equal-size rounded tiles of mixed content | **Bento grid** (industry term) | Modular mixed-size tiles. **Note:** the reference's kanban row and status row are *uniform* grids, not true bento (mixed-size). The term is used loosely in the industry. | Descriptive — **[UNVERIFIED]** as a formalised spec; the Wikipedia article on "Bento" is about Japanese lunch boxes and is **not** a usable citation for the UI term |
| Restrained, low-chrome, high-information density | **Linear-style dashboard** | 1px borders, tiny shadows, monochrome surfaces, information-first. The reference's *status cards* half is closer to this than to the gradient half. | Descriptive |
| — | **Brutalism** | **Does not apply.** No raw borders, no stark type, no unstyled system defaults. | n/a |

### 3.2 Net characterisation

The reference is **not** a coherent design system — it is a **composite of three trends**: neumorphic/soft-UI geometry (huge radii, soft shadows), glassmorphic chrome (floating translucent shell, backdrop blur), and playful-SaaS colour (pastel gradients, avatar stacks, "Done ✅" gamified badges). The left half (sidebar + status cards) is close to a **Linear / Noteflow** workspace; the top half (gradient shell + 4-hue kanban) is a **Dribbble-grade 2021 "playful dashboard"** render.

**Critically:** the reference shows the same three trends the project has already *rejected in writing* — see §5.2.

---

## 4. Source of the Reference — NOT FOUND

> **Finding: the "Jiron" time-tracker dashboard reference could not be traced to a designer, product, Dribbble shot, Behance project, or Mobbin pattern.**

What was attempted and why it failed is in §2. Additional reasoning:

- The brief's contents (product name "Jiron", nav items `Time Tracker / Projects / Reports / Tags / Team / Clients`, user "Yaros Tolstykh", dates in August 2025) are **specific enough to be searchable**, but no index was reachable.
- The name "Yaros Tolstykh" is a plausible Ukrainian developer name, but **no repository, npm package, or product by that name was confirmed** via the GitHub API. Treat the attribution as **[UNVERIFIED]**.

**Practical impact:** we do **not** have the companion screens (login, settings, empty states, mobile). Any redesign informed by this reference is therefore working from **one 1024×768 marketing shot** with no state coverage — which is itself a reason to keep the current design system's own state matrix (`DESIGN_BRIEF.md:350-356`) as the source of truth for states.

**Action for the next session:** if a machine with working web search is available, search Dribbble/Behance/Mobbin for `Jiron` and for the literal strings `UI Designers` / `Front-end Developers` / `Python Developers` in a time-tracker context — those card titles are the highest-signal query. **Do not assume a source exists.**

---

## 5. Project Audit — the ACTUAL current visual language

### 5.1 Stack & versions (verified)

| Thing | Value | Evidence |
| --- | --- | --- |
| Tailwind CSS | **4.3.3 installed** (declared `tailwindcss: ^4.0.0` at `package.json:46`; `@tailwindcss/vite: ^4.1.11` at `package.json:30`) | `package.json:30,46`; `node_modules/tailwindcss/package.json` → `4.3.3`; `node_modules/@tailwindcss/vite/package.json` → `4.3.3` |
| Tailwind config | **No `tailwind.config.js`.** v4 CSS-first: all tokens in `resources/css/app.css` `@theme` block | `resources/css/app.css:10-130`; `components.json:6-12` → `"tailwind": {"config": ""}` |
| Component library | **shadcn/ui, `new-york` style, `cssVariables: true`, icon library `lucide`** | `components.json:3-11`; `resources/js/components/ui/*` (27 primitives) |
| Icons | `lucide-react ^0.475.0` | `package.json:40` |
| Motion | `gsap ^3.13.0` (`package.json:37`) + `@gsap/react ^2.1.2` (`:14`); `tw-animate-css ^1.4.0` | `package.json:14,37` |
| Other | `class-variance-authority`, `clsx`, `tailwind-merge ^3.0.1` (`:45`), `sonner` (toasts, `:44`), Radix primitives, `@inertiajs/react ^3.0.0` (`:15`) | `package.json:2-49` |
| Fonts | **Source Sans 3** (UI/body) + **Source Code Pro** (mono/identifiers), loaded via `@laravel/vite-plugin/fonts` `bunny()` (Bunny Fonts = privacy-friendly Google Fonts mirror) **and** a duplicate Google Fonts `<link>` in `app.blade.php` | `vite.config.ts:15-21`; `resources/views/app.blade.php:44-48`; `resources/css/app.css:11-17` |
| Inertia | v3, `createInertiaApp` with a `layout` resolver + per-page `.layout` metadata object | `resources/js/app.tsx:12-31` |

### 5.2 The design authority in this repo (this is the binding constraint)

`DESIGN.md` and `DESIGN_BRIEF.md` are **explicit, settled, and they forbid most of what the reference is made of**:

| Project rule | Location | Effect on the reference |
| --- | --- | --- |
| "**Don't** use purple or indigo SaaS gradients, neon dark mode, glow edges, or decorative washes." | `DESIGN.md:454` | **Blocks** the 4-hue gradient family + gradient page ground. |
| "Purple / indigo SaaS gradients, neon dark mode, glow edges — *Wrong for facility ops*" | `DESIGN_BRIEF.md:44` | Same, stated as a pre-code "What to avoid". |
| "**Don't** use Inter, Roboto, system-ui, display serifs, or handwritten faces as the brand voice." | `DESIGN.md:457`; `DESIGN_BRIEF.md:49,116` | **Blocks** the reference's geometric-sans + indigo palette. |
| "Gamified badges, confetti, playful illustration" → avoid | `DESIGN_BRIEF.md:46`; `DESIGN.md:458` | **Blocks** the amber "star" and the "Success ✅✅✅" badge row. |
| "Pill-shaped primary CTAs" → avoid; pills for badges/dots only | `DESIGN_BRIEF.md:50,146,348`; `DESIGN.md:338,458` | **Blocks** pill-shaped segmented-control *buttons* and pill CTAs. |
| "Pastel status pills without labels" → avoid | `DESIGN_BRIEF.md:48`; `DESIGN.md:456` | **Blocks** colour-only status encoding. |
| Signal Scarcity Rule: "Signal colors … never become page decoration." | `DESIGN.md:245` | **Blocks** decorative gradient headers on kanban columns. |
| Pastel Pair Rule: a signal colour as a background tint must use the paired `*-subtle` token with the full-strength token as foreground, **and always include a text label** | `DESIGN.md:249` | **Blocks** white-on-saturated-gradient headers. |
| Two-Strata Rule: navigation on the ink-teal `panel` stratum, work on the canvas | `DESIGN.md:251` | The reference's **white sidebar** conflicts; the project's dark panel is deliberate. |

> **Bottom line:** any adoption of the reference's surface treatment is a **change to `DESIGN.md` / `DESIGN_BRIEF.md`**, and per the repo's own working rules that requires an explicit user decision — not an implementer's choice. The AGENTS.md rule "JANGAN mengambil keputusan final atas Open Question tanpa konfirmasi user" applies.

### 5.3 Current tokens (from `resources/css/app.css`)

**Surfaces & ink** — `--canvas #EEF1F4` (app background), `--surface #FFFFFF` (cards), `--surface-raised #F7F9FB`, `--surface-sunken #E8ECF1` (board columns), `--ink #15202B`, `--ink-muted #5B6B7C`, `--ink-subtle #8494A7`, `--border #D5DCE5`, `--border-strong #A8B4C4` (`app.css:164-171`).

**Brand** — `--brand #0C6B58` (execution teal, primary actions), `--brand-hover #095445`, `--brand-strong #12A383`, `--brand-deep #084C3E`, `--brand-soft #DCEFE9`, `--on-brand #FFFFFF` (`app.css:173-178`).

**Signals** — `--info #2A5F8F` / `#E4EDF6`, `--warning #B86E00` / `#F7ECDA`, `--danger #C0392B` / `#F7E2DF`, `--escalation #7A1F3D` / `#F2E2E8`, `--success #1F7A4C` / `#DFEFE6`, `--owner-urgent #8A4B12` / `#F3E5D6` (`app.css:179-193`).

**Strata** — `--panel #0D1B22` (dark nav panel), `--panel-raised #12262F`, `--panel-ink #E7EFF4`, `--panel-ink-muted #9DB0BC`, `--console #0C332D` (Hak Akses page) (`app.css:198-225`).

**Radius (project overrides Tailwind's v4 defaults)** — `--radius-sm .25rem (4px)`, `--radius-md .5rem (8px)`, `--radius-lg .75rem (12px)`, `--radius-xl 1rem (16px)`, `--radius-2xl 1.25rem (20px)` (`app.css:19-23`). Tailwind's own `3xl` (24px) and `4xl` (32px) are **not** overridden (`node_modules/tailwindcss/theme.css`).

**Shadows** — `--shadow-card: 0 1px 2px rgba(20,33,43,.05)`, `--shadow-raised`, `--shadow-popover`, `--shadow-modal` (`app.css:29-32`). **There is no "large floating panel" shadow token.**

**Dark theme** — fully authored, not inverted (`.dark` block, `app.css:246-336`). ADR-0003 ("light theme only") is **superseded**: `DESIGN_BRIEF.md:13` records "A5 superseded (2026-10-05, user decision): both themes ship in the MVP". Any new visual work must therefore target **both** themes.

### 5.4 What already exists (mapped to the reference)

| Reference element | Current implementation | Status |
| --- | --- | --- |
| Floating white app card, huge radius | **Not present.** Shell is full-bleed: `<div class="flex min-h-screen w-full bg-background">` + `SidebarProvider` (`app-shell.tsx:18,24`) | **Missing** |
| White sidebar | **Opposite:** dark `panel` stratum, `bg-[#0B151C]` hardcoded (`app-sidebar.tsx:244`) | **Deliberately different** |
| Grouped sidebar sections w/ labels | `SidebarGroupLabel` per group, uppercase micro-caps `text-[0.6875rem]! tracking-[0.08em]`; groups = Operasional · Data Master · Hak Akses · Sistem (`app-sidebar.tsx:166-191`; `nav-items.tsx:177-285`) | **Already matches** |
| Active nav item | 3px `bg-brand-strong` left marker + `data-[active=true]:bg-brand-strong/15` + bold label; icon keeps group hue (`app-sidebar.tsx:87,135-140`) | **Already matches (different form)** |
| Circular avatar + name in sidebar footer | `NavUser` + `Avatar`/`AvatarFallback` w/ initials (`nav-user.tsx`, `app-sidebar.tsx:277`) | **Already matches** |
| Top bar w/ circular icon buttons + bell dot | `size-9 rounded-lg` ghost buttons, bell with `bg-danger` 8px dot + `ring-2 ring-surface` (`app-sidebar-header.tsx:35-54,57-68`) | **Already matches (rounded-lg, not round)** |
| Segmented control (Month/Day/Week) | **Exact match already shipped:** `role="group"` pill container `rounded-lg bg-surface-sunken p-0.5`, active chip `bg-surface text-ink shadow-card` (`dashboard.tsx:565-585`) | **Already matches** |
| White status cards, kebab menu, list rows | `ModuleCard` (`rounded-xl border bg-card`), `StatusBadge`, `CategoryBadge` (`module-card.tsx`, `status-badge.tsx`) | **Already matches** |
| Progress bar | `h-1.5 rounded-full bg-surface-sunken` + `bg-brand` fill (`dashboard.tsx:651-657`) | **Already matches** |
| Avatar stack | `AvatarStack` exists but renders **one** avatar, not an overlapping stack (`dashboard.tsx:521-529`). `DESIGN_BRIEF.md:328` specifies "max 4 + `+n` with ring in `surface`" | **Partially built** |
| Kanban / board view | **Does not exist.** `work-orders/index.tsx` is a `shadcn` `<Table>`; grep for `kanban|board` across `resources/js` returns **no** board component | **Missing (greenfield)** |
| Timeline / calendar grid + current-time indicator | **Does not exist.** No route, no controller, no data model | **Missing (large)** |
| Initials avatars (2-letter, tinted) | `useInitials` hook + `AvatarFallback` w/ `bg-brand-soft text-brand` (`app-sidebar-header.tsx:130`) | **Already matches** |
| Gradient accents | Teal-only, on the logo orb / CTA / module icon squares: `bg-linear-to-tr from-info to-brand` etc. (`dashboard.tsx:234-298`, `app-sidebar.tsx:217`, `app-logo.tsx:58`) | **Already matches (teal family)** |
| Backdrop blur | `backdrop-blur-md` on the sticky top bar and mobile bottom bar (`app-sidebar-header.tsx:149`, `bottom-nav.tsx:104`) | **Already matches** |
| `prefers-reduced-motion` handling | Global guard in `app.css:433-442`; per-component `motion-reduce:` variants | **Already matches** |
| Icon-only "..." overflow menus | Radix `DropdownMenu` available (`components/ui/dropdown-menu.tsx`) | **Available** |

**Read this as:** the reference's *anatomy* is ~70 % already present in OptiWorks. What is genuinely new is the **gradient page ground + floating shell**, the **4-hue gradient family**, and the **timeline grid**.

---

## 6. Reference-vs-Project delta, element by element

### 6.1 Palette & type

| | Reference **[SPEC]** | OptiWorks **[CODE]** | Delta |
| --- | --- | --- | --- |
| Primary | indigo `#6366F1` | teal `#0C6B58` | **Wholesale different hue.** |
| Accents | violet `#8B5CF6`, amber `#F59E0B`, magenta `#EC4899`, blue `#3B82F6` | info `#2A5F8F`, warning `#B86E00`, danger `#C0392B`, success `#1F7A4C`, escalation `#7A1F3D`, owner-urgent `#8A4B12` | **No purple/violet/magenta token exists at all.** |
| Neutrals | white surfaces, `#F8FAFC`/`#F1F5F9` fills, `#0F172A` text, `#64748B` secondary | `#FFFFFF` / `#F7F9FB` / `#E8ECF1`, `#15202B`, `#5B6B7C` | **Near-identical structure, different hue temperature** (project is cooler/greyer). |
| Type | geometric sans (Inter/Poppins-like) | **Source Sans 3** (humanist) + Source Code Pro | **Different class of face.** |
| Radius | 28px shell, 16px cards, 12px blocks | 4/8/12/16/20px + 24/32px available | **Reference is ~1.75× rounder.** |
| Elevation | large soft drop shadow under the floating card | hairline + 1–2px whisper shadow | **Different depth model.** |

### 6.2 Structural parity summary

**Already matched (no work):** grouped sidebar with section labels; active-item affordance; top-bar icon cluster with notification dot; segmented pill control; white surface cards; kebab overflow menus; progress bars; initials avatars; avatar stack (partially); backdrop-blur chrome; large-radius floats.

**Missing but buildable from existing primitives:** floating shell card + gradient ground; overlapping avatar stack; bottom-edge progress strip; board/kanban view.

**Missing and expensive:** timeline/calendar grid with time gutter + current-time indicator (needs new data model, route, controller, and — per `AGENTS.md` — an MVP scope decision: Daily Work and Work Data are **Non-Goals** until built).

---

## 7. Technical verification against the installed stack

### 7.1 Tailwind utilities — compiled against `node_modules/tailwindcss@4.3.3`

Rather than trusting recollection, the candidate utilities were compiled with the installed package's own `compile()` API. **All of the following emit real CSS in this project:**

| Utility | Emits | Note |
| --- | --- | --- |
| `bg-linear-to-br` | ✔ | v4 name for `linear-gradient(to bottom right, …)`. https://tailwindcss.com/docs/background-image |
| `bg-gradient-to-br` | ✔ | **Legacy alias still ships in 4.3.3.** Both work; the repo mixes them today (`app-header.tsx:207` uses `bg-gradient-*`, `dashboard.tsx:234` uses `bg-linear-*`). **Standardise on `bg-linear-*` for new code.** https://tailwindcss.com/docs/upgrade-guide |
| `bg-linear-65` | ✔ | arbitrary angle. |
| `bg-radial` | ✔ | `radial-gradient(…)`. |
| `from-*` / `via-*` / `to-*`, `via-30%`, `from-10%` | ✔ | gradient stop colours + positions. https://tailwindcss.com/docs/background-image |
| `rounded-4xl` | ✔ | **2rem = 32px** (project does not override 4xl) → the reference's 28px shell radius is `rounded-4xl` or `rounded-[28px]`. |
| `text-shadow-sm` | ✔ | v4.1+ text-shadow scale (`node_modules/tailwindcss/theme.css`). |
| `shadow-xs`, `inset-shadow-sm` | ✔ | v4 renamed shadow scale. |
| `backdrop-blur-md` | ✔ | `backdrop-filter: blur(12px)`. https://tailwindcss.com/docs/backdrop-filter-blur |
| `text-balance`, `tabular-nums` | ✔ | already in use in `dashboard.tsx:959,621`. |
| `@utility` directive | ✔ | v4 replacement for `@layer utilities` for custom utilities. https://tailwindcss.com/docs/adding-custom-styles |
| `--color-*` in `@theme` | ✔ | how new design tokens are registered in v4 CSS-first config. https://tailwindcss.com/docs/colors |

**Not available in v4.3.3:** there is no `mask-*` utility namespace (`--mask*` is absent from `node_modules/tailwindcss/theme.css`); the repo already uses arbitrary `[mask:…]` properties in `SpotlightCard.tsx` for that. There is also no built-in scrollbar utility — the project styles scrollbars by hand in `app.css:400-431` (both `scrollbar-color` and `::-webkit-scrollbar`), which is the correct approach.

### 7.2 The "floating shell over a gradient" — browser reality check

- A **real** refraction/glass effect is not achievable in a browser: "browsers cannot access the pixels rendered behind an element, implementations approximate the effect's refraction, translucency and lensing using techniques such as CSS `backdrop-filter`, SVG displacement filters, and WebGL and WebGPU shaders." https://en.wikipedia.org/wiki/Liquid_Glass
- The reference's specific stack — an opaque white rounded card floating over a saturated gradient — does **not** need `backdrop-filter` at all; it is plain `background` + `border-radius` + `box-shadow`. That is trivially implementable.
- If real glass were wanted, `backdrop-blur-*` + `bg-white/60` + `border-white/70` is the established pattern and **the project already uses it** (`app-header.tsx:81,136,285`). But note Apple's own post-launch correction: they "reduced default transparency" and "increased opacity in navigation bars and other user interface chrome" after legibility complaints. https://en.wikipedia.org/wiki/Liquid_Glass

### 7.3 Font loading

Two loaders coexist and both must be reconciled before any font change:
- `vite.config.ts:14-21` → `bunny('Source Sans 3', {weights:[400,500,600,700]})` and `bunny('Source Code Pro', {weights:[400,500,600]})` via `@laravel/vite-plugin/fonts`.
- `resources/views/app.blade.php:37-46` → a **Google Fonts** `<link>` for the same two families.

`app.css:11-14` declares `--font-sans: 'Source Sans 3', ui-sans-serif, system-ui, sans-serif`. **Do not add a third family** (e.g. Inter) — `DESIGN.md:457` forbids it and `DESIGN_BRIEF.md:116` lists "Inter, Plus Jakarta Sans, Space Grotesk, DM Sans" as explicitly not-to-use.

### 7.4 Contrast computation method

Computed with the WCAG relative-luminance formula reproduced in W3C's Understanding docs: `L = 0.2126R + 0.7152G + 0.0722B` on linearised sRGB, then `ratio = (L1+0.05)/(L2+0.05)`. Source: https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html#dfn-relative-luminance

---

## 8. Accessibility audit (WCAG 2.2)

### 8.1 The thresholds being applied

| Criterion | Requirement | Source |
| --- | --- | --- |
| **1.4.3 Contrast (Minimum)** (AA) | ≥ **4.5:1** for normal text; ≥ **3:1** for large text (≥18pt, or ≥14pt bold ⇒ ≈24px / ≈18.5px). Ratios are **thresholds, not rounded** — 4.499:1 fails. | https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html |
| **1.4.11 Non-text Contrast** (AA) | ≥ **3:1** against adjacent colours for UI-component boundaries/state indicators and for graphical objects needed to understand content. | https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html |
| **1.4.1 Use of Color** (A) | Colour must not be the only means of conveying information. If two colours differ in **lightness** with ≥3:1 relative-luminance difference, that counts as an additional distinction; hue-only differences need a redundant cue. | https://www.w3.org/WAI/WCAG22/Understanding/use-of-color.html |
| Gradient rule (1.4.11) | For a gradient, take the **least contrasting area**; if the object is still understandable without it, it passes. | https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html#dfn-gradients |
| Known failure | **F83** — "Failure … due to specifying background images that do not provide sufficient contrast with foreground text". | https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html#failures |

### 8.2 Reference failures — every one, with a concrete fix

> Light-stop hexes are the closest Tailwind default steps (an **assumption**; the brief only said "light lavender / light periwinkle / light yellow / light pink"). Saturated stops are from the brief.

| # | Reference element | Measured | Required | Fix |
| --- | --- | --- | --- | --- |
| A1 | White title on **light** violet stop `#C4B5FD` | **1.85:1** | 4.5:1 | Darken the light stop to ≤ `#7C3AED` (5.70:1), or use dark ink text on the light half. |
| A2 | White title on **light** blue stop `#93C5FD` | **1.80:1** | 4.5:1 | Darken to ≤ `#2563EB` (5.17:1), or dark text on light half. |
| A3 | White title on **light** amber stop `#FCD34D` | **1.44:1** | 4.5:1 | Darken to `#B45309`-class amber, or dark text on light half. |
| A4 | White title on **light** pink stop `#F9A8D4` | **1.81:1** | 4.5:1 | Darken to ≤ `#DB2777` (4.60:1), or dark text. |
| A5 | White body text on **saturated** violet `#8B5CF6` | **4.23:1** | 4.5:1 (normal) / 3:1 (large) | Passes only for ≥24px bold; small body copy needs `#7C3AED` (5.70:1). |
| A6 | White body text on saturated blue `#3B82F6` | **3.68:1** | 4.5:1 / 3:1 | Large-text only. Use `#2563EB` for body. |
| A7 | White body text on saturated amber `#F59E0B` | **2.15:1** | 4.5:1 | **Fails even for large text.** Use `#B45309`/`#92400E`. |
| A8 | White body text on saturated magenta `#EC4899` | **3.53:1** | 4.5:1 / 3:1 | Large-text only. Use `#DB2777` (4.60:1) for body. |
| A9 | White translucent body text (`white/75`) on amber `#F59E0B` | **1.93:1** | 4.5:1 | Never put sub-100 % white on a saturated gradient. Use a solid token. |
| A10 | Amber star icon `#F59E0B` on white card | **2.15:1** | 3:1 (non-text) | Fails 1.4.11. Use `warning #B86E00` (≈3.99:1) or add a dark outline. |
| A11 | White initials on orange avatar `#F97316` | **2.80:1** | 4.5:1 / 3:1 | Fails both. Use `#C2410C`-class orange. |
| A12 | Hairline border `#E2E8F0` vs white card | **1.23:1** | 3:1 | The border is the *only* thing identifying the card's boundary → fails 1.4.11. Use `border-strong` and/or rely on a shadow. |
| A13 | "Today" column band `#F1F5F9` vs white grid | **1.10:1** | 3:1 | The band is the only cue for "today" → fails 1.4.11 **and** 1.4.1 (colour-only). Add a bold date + a 3:1 rule, and an `aria-current`-style text cue. |
| A14 | "Today" band `#F1F5F9` vs grid line `#E2E8F0` | **1.13:1** | 3:1 | Band is invisible against the grid. Give the band a ≥3:1 edge. |
| A15 | White card on the **light** end of the page gradient `#A5B4FC` | **1.99:1** | 3:1 if the card edge is the only boundary cue | The floating card's silhouette against a light gradient corner is weak. Add a 1px ≥3:1 border or darken the gradient's light stop. |
| A16 | Status dots / "Done" check badges on white, colour-only | n/a | 1.4.1 | A green/red dot alone is colour-only meaning. Pair with a label or icon (W3C's own star-rating example: yellow fill on white = 1.2:1 = fail). |
| A17 | Avatar initials colour-coded per person (purple "UI", orange "FD", pink "PD") | n/a | 1.4.1 | The *colour* is decorative here (identity is the initials), so this is acceptable — but the **text** inside must still meet 1.4.3 (see A11). |

### 8.3 For contrast: what OptiWorks already does *better*

| Pair | Ratio | Verdict |
| --- | --- | --- |
| `--ink #15202B` on white | **16.49:1** | PASS |
| `--ink-muted #5B6B7C` on white | **5.47:1** | PASS |
| `--ink #15202B` on `--surface-sunken #E8ECF1` | **13.90:1** | PASS |
| `--ink-muted #5B6B7C` on `--surface-sunken` | **4.61:1** | PASS |
| white on `--brand #0C6B58` | **6.44:1** | PASS |
| white on `--info #2A5F8F` | **6.71:1** | PASS |
| white on `--success #1F7A4C` | **5.32:1** | PASS |
| white on `--danger #C0392B` | **5.44:1** | PASS |
| white on `--escalation #7A1F3D` | **10.04:1** | PASS |
| white on `--owner-urgent #8A4B12` | **6.78:1** | PASS |
| white on `--mod-master #2F6FB5` | **5.17:1** | PASS |
| `--brand #0C6B58` on `--brand-soft #DCEFE9` | **5.39:1** | PASS |
| `--info #2A5F8F` on `--info-subtle #E4EDF6` | **5.67:1** | PASS |
| `--escalation #7A1F3D` on `--escalation-subtle #F2E2E8` | **8.04:1** | PASS |
| `--panel-ink #E7EFF4` on `--panel #0D1B22` | **15.08:1** | PASS |
| `--panel-ink-muted #9DB0BC` on `--panel` | **7.83:1** | PASS |
| `--brand-strong #12A383` on `--panel` | **5.51:1** | PASS |
| white on `--brand-strong #12A383` | **3.19:1** | **Large-text only** — do not put body copy on `brand-strong`. |
| white on `--warning #B86E00` | **3.99:1** | **Large-text only** — same warning as the reference's amber. |
| `--warning #B86E00` on `--warning-subtle #F7ECDA` | **3.41:1** | **Large-text only** (badge = 12px → would fail). |
| `--danger #C0392B` on `--danger-subtle #F7E2DF` | **4.38:1** | Marginally **under** 4.5:1 for 12px badge text. |
| `--success #1F7A4C` on `--success-subtle #DFEFE6` | **4.47:1** | Marginally **under** 4.5:1. |
| `--ink-subtle #8494A7` on `--surface-sunken` | **2.61:1** | FAIL — do not use `ink-subtle` on `surface-sunken`. |
| `--border #D5DCE5` vs `--surface #FFFFFF` | **1.38:1** | FAIL as a *sole* boundary cue (1.4.11). Fine only as a divider where the card also has a shadow. |
| `--border-strong #A8B4C4` vs white | **2.10:1** | Still under 3:1 — a "strong" border is **not** sufficient alone for a 1.4.11 boundary cue. |

> **Actionable finding, independent of the reference:** two of the project's own badge pairs (`danger` on `danger-subtle` = 4.38:1, `success` on `success-subtle` = 4.47:1) fall just under 4.5:1 for 12px text, and `--border-strong` is still under the 3:1 non-text threshold. These are small token tweaks that would fix real AA gaps in the *current* app.

---

## 9. Feasibility verdict per design element

| # | Reference element | Verdict | One-line reason |
| --- | --- | --- | --- |
| 1 | **Gradient page background + floating rounded app-shell card** | **ADAPT** | Technically trivial (`bg-linear-to-br` on `body`/`html` + `rounded-4xl` + one large shadow token), but it collides with `DESIGN.md:454` "no purple/indigo SaaS gradients"; if adopted, use the **teal** family (`from-brand-soft` → `to-brand`) and add a real `--shadow-float` token. |
| 2 | **Icon-badge active nav item in sidebar** | **ADAPT** | The project already marks the active item (3px teal left rail + tinted chip + bold label — `app-sidebar.tsx:87,135-140`); a *filled circular icon badge* would need ≥3:1 icon/background contrast and a redundant text cue, and would fight the Two-Strata Rule. Keep the current treatment. |
| 3 | **Grouped sidebar sections with labels** | **ADOPT AS-IS** | Already shipped: `SidebarGroupLabel` with uppercase micro-caps across Operasional · Data Master · Hak Akses · Sistem (`app-sidebar.tsx:175`, `nav-items.tsx:177-285`). Zero work. |
| 4 | **Segmented control pill group (Month/Day/Week)** | **ADOPT AS-IS** | Already shipped in `dashboard.tsx:565-585` — same `bg-surface-sunken` container, same active-chip treatment, same `role="group"` + `aria-pressed`. Zero work. |
| 5 | **Pastel gradient kanban column headers (4-hue family)** | **SKIP the palette / ADAPT the idea** | `DESIGN.md:245` (Signal Scarcity) + `:249` (Pastel Pair) forbid it, and the project's four status hues are semantic, not decorative. If a header gradient is wanted, use **one** token pair per column (e.g. `from-info to-info` at full strength, white text = 6.71:1) and keep the 8px status dot + count + ⋮. |
| 6 | **White status cards with initials avatars + icon-count toolbar + star** | **ADAPT** | Cards/avatars/toolbar already exist (`ModuleCard`, `Avatar`, `StatusBadge`, Radix `DropdownMenu`); the **amber star is SKIP** — `DESIGN_BRIEF.md:46` bans gamified badges, and `#F59E0B` on white is 2.15:1 (fails 1.4.11). Replace with a `DeadlineRail`/priority token. |
| 7 | **Timeline/calendar grid with time gutter + current-time indicator** | **SKIP for MVP / DEFER** | No route, no controller, no data model; `work_planning`/`schedule_wd` are not yet built, and Daily Work / Work Data are Non-Goals per `AGENTS.md`. `DESIGN_BRIEF.md:270` already allows "Timeline" to be disabled in MVP. Highest cost, lowest MVP value. |
| 8 | **Circular icon buttons with light-gray fills in a top bar** | **ADAPT** | The cluster exists (`app-sidebar-header.tsx:35-68`) but is `rounded-lg` ghost. Switching to `rounded-full bg-surface-raised` is a 3-line change; keep ≥36px (ideally 40px) targets and the existing `aria-label`s (the bell already announces the unread count). |
| 9 | **Circular overlapping avatar stacks** | **ADOPT** | Specified but not built (`DESIGN_BRIEF.md:328`); `dashboard.tsx:521-529` renders a *single* avatar. Implement max-4 + `+n` with a `surface` ring, plus `aria-label="Ditugaskan ke {n} karyawan"` per `DESIGN_BRIEF.md:375`. |
| 10 | **Progress bars as strips on card bottom edges** | **ADAPT** | Progress bars exist (`dashboard.tsx:651-657`); a flush bottom-edge strip is a small variant. Keep `DESIGN.md`'s continuous/segmented rule and the status-token colouring, and add `role="progressbar"` + `aria-valuenow` per `DESIGN_BRIEF.md:374`. |
| 11 | **Colour palette + font pairing** | **SKIP** | Directly forbidden by `DESIGN.md:454,457` and `DESIGN_BRIEF.md:44,49,116`; and the reference's own palette fails WCAG in 8 places (§8.2). Source Sans 3 is the brand voice; the reference's geometric sans is not. |

**Summary: 2 ADOPT AS-IS, 6 ADAPT, 3 SKIP/DEFER.** ~55 % of the reference maps onto existing components with no code change; the rest is either a token-level change or out of MVP scope.

---

## 10. Recommended next steps (if the reference is to be pursued)

1. **Decide the authority question first.** The reference's palette/geometry conflicts with `DESIGN.md` and `DESIGN_BRIEF.md`. A user decision is required; do not "just implement it". A reasonable middle path: **adopt the reference's *geometry and density* (bigger radii, floating shell, avatar stacks, timeline-shaped board) while keeping the project's *colour, type, and signal semantics*.**
2. **If a floating shell is adopted**, add one token: `--shadow-float: 0 32px 64px -24px rgba(21,32,43,.28)`, set the shell to `rounded-4xl` (32px, already in Tailwind 4.3.3), and give the card a ≥3:1 edge against the gradient (see A15). Keep `bg-surface` opaque — a translucent shell is what produced Apple's legibility backlash.
3. **Never copy the 4-hue gradient family.** If column headers need colour, use the project's own status tokens at full strength with white text (all ≥5.17:1) and keep the "Signal Scarcity" rule intact.
4. **Fix the two real AA gaps already in the app** (§8.3): darken `danger` on `danger-subtle` and `success` on `success-subtle` by ~2–3 % to clear 4.5:1 at 12px, and stop relying on `border-strong` as a sole boundary cue.
5. **Standardise the gradient utility** on `bg-linear-*` (both spellings work in 4.3.3, but the repo currently mixes them).
6. **Reconcile the two font loaders** (`bunny()` in `vite.config.ts` and the Google Fonts `<link>` in `app.blade.php`) before touching typography.
7. **Re-verify the reference's origin** on a machine with working web search before citing it anywhere else (this report asserts no source).

---

## 11. Open Questions

| # | Question | Why it matters | Default if undecided |
| --- | --- | --- | --- |
| Q1 | Does the user want to **supersede** `DESIGN.md`'s anti-goal list, or only borrow the reference's geometry? | Determines whether the 4-hue palette is even on the table. | Borrow geometry only; keep the palette. |
| Q2 | Is the **floating shell card** wanted on the work surfaces, or only on a marketing/auth surface? | A floating shell on a dense WMS board costs usable area and fights the 1400px max-width + sticky-top-bar model (`DESIGN.md:289`). | Reserve for auth/marketing only. |
| Q3 | Should the **board/kanban** view be built now? | It is the reference's most transferable idea and is already in `DESIGN.md:296` as the signature view — but no component exists yet. | Yes, as its own slice (`.scratch/wms-mvp/issues/11-layout-consistency-show-pages.md` is the natural neighbour). |
| Q4 | Is a **timeline/calendar** view in MVP scope? | `DESIGN.md:294` allows "Timeline · Kalender" to be disabled; the data model is not built. | Deferred. |
| Q5 | Do we need the **overlapping avatar stack** now? | Cheap, specified, and improves both board and list density. | Yes — small, self-contained slice. |
| Q6 | Should the **timeline "current time indicator"** be adopted when/if the timeline is built? | It is a genuinely good pattern; needs a 3:1 line + a text/time cue for 1.4.1/1.4.11. | Adopt at that time, with `--brand` (6.44:1 on white). |

---

## 12. Sources

**Primary — accessibility**
- W3C, *Understanding SC 1.4.3: Contrast (Minimum)* — https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html
- W3C, *Understanding SC 1.4.11: Non-text Contrast* — https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html
- W3C, *Understanding SC 1.4.1: Use of Color* — https://www.w3.org/WAI/WCAG22/Understanding/use-of-color.html

**Primary — framework**
- Tailwind CSS v4 — background images: https://tailwindcss.com/docs/background-image
- Tailwind CSS v4 — backdrop filter: https://tailwindcss.com/docs/backdrop-filter-blur
- Tailwind CSS v4 — custom styles / `@utility`: https://tailwindcss.com/docs/adding-custom-styles
- Tailwind CSS v4 — upgrade guide (incl. `bg-gradient-*` → `bg-linear-*`): https://tailwindcss.com/docs/upgrade-guide
- Tailwind CSS v4 — theme colors: https://tailwindcss.com/docs/colors
- Tailwind CSS v4 release notes: https://tailwindcss.com/blog/tailwindcss-v4
- Apple Developer — Design (Liquid Glass): https://developer.apple.com/design/

**Reference (tertiary, used for vocabulary only)**
- Wikipedia — Neumorphism: https://en.wikipedia.org/wiki/Neumorphism
- Wikipedia — Glassmorphism: https://en.wikipedia.org/wiki/Glassmorphism
- Wikipedia — Liquid Glass: https://en.wikipedia.org/wiki/Liquid_Glass

**In-repo**
- `DESIGN.md`, `DESIGN_BRIEF.md`, `resources/css/app.css`, `vite.config.ts`, `components.json`, `package.json`, `resources/views/app.blade.php`
- `resources/js/components/`: `app-shell.tsx`, `app-sidebar.tsx`, `app-sidebar-header.tsx`, `app-header.tsx`, `nav-items.tsx`, `page-header.tsx`, `module-card.tsx`, `status-badge.tsx`, `category-badge.tsx`, `deadline-rail.tsx`, `ui/*`
- `resources/js/pages/dashboard.tsx`, `resources/js/pages/work-orders/index.tsx`
- `resources/js/hooks/use-appearance.tsx`, `resources/js/hooks/use-initials.tsx`
- `docs/adr/0004-hak-akses-surface.md`

> **Superseded, cited for history only:** `docs/adr/0003-light-theme-only-for-mvp.md` states that dark mode was excluded from the MVP. That ADR is **no longer in force** — `DESIGN_BRIEF.md:13` records "A5 superseded (2026-10-05, user decision): both themes ship in the MVP", and `resources/css/app.css:246-336` now contains a fully authored, separately-tuned dark theme. Any design work must target **both** themes.

**Not found / unverified**
- The origin of the "Jiron" time-tracker dashboard — **unverified**, see §4.
- The exact hex of the reference's light gradient stops — **unverified** (approximated from the closest Tailwind default steps).
