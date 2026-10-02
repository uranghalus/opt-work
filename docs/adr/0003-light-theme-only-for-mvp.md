# Light theme only for the MVP

DESIGN.md rules out dark mode for the MVP ("Don't ship dark mode … in the MVP UI"), and the `c716cfc` styling encodes that: the full Dispatch Board palette lives in a single `:root, .dark` selector in `resources/css/app.css`. The `.dark` half deliberately mirrors the light values, so the system-dark detection script and the `dark` class binding in `resources/views/app.blade.php` are inert — a dark-preference session still renders the committed light Dispatch Board instead of a half-themed mix. The inline `html` background stays `#eef1f4` for every appearance, so there is no dark flash before the CSS loads.

This decision was made explicit while resolving commit `c943901`, which shipped unresolved merge-conflict markers in `app.css` and `app.blade.php` and crashed the Tailwind/Lightning CSS parse (`Invalid declaration: \`======= …\``). The losing (baa8144-era) side carried the dark-capable styling; DESIGN.md decided for the light-only side.

## Considered Options

- **Dark-capable tokens (HEAD side of the conflict)** — separate `:root` block plus an `html.dark` background override. Rejected: DESIGN.md forbids shipping dark mode in the MVP UI, and that separate `:root` block lacked the Dispatch Board semantic variables (`--canvas`, `--surface-raised`, …) that the `@theme` mapping reads, so light sessions would render unset colors.
- **Drop the `.dark` selector entirely** — rejected: the blade appearance script would still add a `dark` class; with no rule coverage the variables would stay undefined instead of mirroring the light values.
- **`:root, .dark` mirroring light values (chosen)** — honors DESIGN.md while neutralizing the system-dark script.

## Consequences

- New UI must not add `dark:` variants or `.dark` overrides; dark-mode design work waits until DESIGN.md revisits it.
- The `appearance` prop and system-dark script remain in the layout but have no visual effect until a real dark theme exists.
- On future merges, DESIGN.md wins over whichever conflict side is older, and unresolved conflict markers must never be committed (they shipped in `c943901` and broke the build).
