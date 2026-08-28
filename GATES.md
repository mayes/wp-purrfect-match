# Gates: Purrfect Match public design lift

OWNS: GATES.md, .gitattributes, .distignore, purrfect-match.php, readme.txt, README.md, tools/verify-public-design.ps1, tools/verify-brand-contrast.php, templates/widget.php, assets/css/purrfect-match.css, assets/js/purrfect-match.js, includes/class-purrfect-match.php, preview/admin.html, preview/public.php, preview/screenshots/public-*.png

Scope: Deliver a polished, responsive visitor-facing adoption experience while preserving the WordPress, shortcode, REST, and Petfinder behavior of the existing plugin.

- [x] G1: The public design uses one restrained accent, non-black neutrals, legible contrast, labels above controls, and a strict single-column mobile fallback without banned generic-design patterns.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode design
  EXPECT: PUBLIC DESIGN VERIFICATION PASSED
  EVIDENCE: exit=0; shell=cmd.exe; cwd=<repository-root>; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=f056324a2ff3a21a399915524a0e62b4a9f90fcd1fe638839593ba09730164e7; output-bytes=35

- [x] G2: Production PHP and JavaScript parse successfully and the plugin's established shortcode, option, asset, REST, response, CSS, and data-hook contracts remain present.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode contracts
  EXPECT: PUBLIC CONTRACT VERIFICATION PASSED
  EVIDENCE: exit=0; shell=cmd.exe; cwd=<repository-root>; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=c39a45f66e03b4cf1605dfe27a8d65761b09c69a56e5b3b6c6baa069068819c2; output-bytes=37

- [x] G3: At desktop and mobile review widths, the live local preview has a clear first viewport, balanced adoption grid, readable controls, no horizontal overflow, and a true one-column phone layout.
  EVIDENCE: Browser QA on 2026-08-27 measured 3 columns, 44px selects, first-card y=556, and no positive horizontal overflow at 1265x800; 1 column, labels above 44px selects, first-card y=706, and no positive horizontal overflow at 390x844. Reviewed preview/screenshots/public-desktop-new.png and public-mobile-new.png at original resolution.

- [x] G4: Loading, populated, empty-filter, upstream-error, keyboard-focus, and reduced-motion states are complete and visually consistent in the browser.
  EVIDENCE: Browser fixtures verified 6 loading listitems, populated cards, empty state with clear action, error state with 2 fallback links plus retry, and an open story with scrollable copy. Keyboard Tab moved focus from the organization link to Shuffle with a 2px solid #211d19 focus ring. Emulated prefers-reduced-motion matched true with 1e-06s animation/transition duration and one iteration. Reviewed public-error-new.png and public-story-new.png.

- [x] G5: The final diff is confined to the visitor-facing design lift and its review/verification artifacts, with unrelated user changes preserved.
  EVIDENCE: Compared against the recorded dirty-worktree baseline: this pass wrote only OWNS paths. Pre-existing admin, Explorer, documentation, build, settings, bootstrap, and readme changes were left intact. git diff --check passed, and GATES/preview/tools are excluded from release archives.

- [x] G6: The CJPaws integration refinement resists host-theme typography and link-state overrides, removes the redundant same-site link, and keeps the filters balanced at the live page's medium and phone embed widths.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode design; powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode contracts
  EXPECT: PUBLIC DESIGN VERIFICATION PASSED; PUBLIC CONTRACT VERIFICATION PASSED
  EVIDENCE: Browser QA on 2026-08-27 measured a 709px widget with three filter fields on one row and the hidden-Breed variant in two equal columns; a 309px widget remained single-column with no horizontal overflow and the first pet beginning 564px below the widget top. The adoption CTA retained rgb(27, 23, 20) text on rgb(233, 51, 150) at hover with opacity 1. Version 1.7.1 supplies a fresh public-asset cache key. Reviewed public-cjpaws-refined-medium.png and public-cjpaws-refined-mobile.png at original resolution.
