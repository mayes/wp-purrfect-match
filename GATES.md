# Gates: Purrfect Match release quality

OWNS: GATES.md, .gitignore, .gitattributes, .distignore, purrfect-match.php, readme.txt, README.md, examples/cjpaws.md, tools/verify-public-design.ps1, tools/verify-brand-contrast.php, tools/verify-newest-sort.ps1, tools/verify-newest-sort.mjs, tools/verify-shortcode-sort.php, tools/verify-release-archive.ps1, templates/widget.php, assets/css/purrfect-match.css, assets/js/purrfect-match.js, includes/class-purrfect-match.php, includes/class-settings.php, includes/class-rest.php, preview/admin.html, preview/public.php, preview/screenshots/public-*.png

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
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode design && powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode contracts
  EXPECT: /PUBLIC DESIGN VERIFICATION PASSED[\s\S]*PUBLIC CONTRACT VERIFICATION PASSED/
  EVIDENCE: exit=0; shell=C:\WINDOWS\system32\cmd.exe; cwd=C:\Users\VM Gateway\Documents\Mayes\wp-purrfect-match-main; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=1c5f98e14ab6f46142455cc12ce3414d179e78f29a1d2c13909d44d0f944c799; output-bytes=72

- [x] G7: The allow-listed sort contract preserves default ordering, emits newest publication ordering only when requested, falls back safely, applies the limit after ordering, and separates both cache modes.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-newest-sort.ps1 -Mode all
  EXPECT: NEWEST SORT CONTRACT VERIFICATION PASSED
  EVIDENCE: exit=0; shell=C:\WINDOWS\system32\cmd.exe; cwd=C:\Users\VM Gateway\Documents\Mayes\wp-purrfect-match-main; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=a7bb66852a1ebffd1ca3c65fbb2fd4c9faecff4c95c6fd0ecfec263bda5c5acf; output-bytes=42

- [x] G8: Version 1.8.0 and newest-sort documentation are synchronized across shipped metadata, editor help, and the CJ Paws compact teaser example.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-newest-sort.ps1 -Mode docs
  EXPECT: NEWEST SORT DOCUMENTATION VERIFICATION PASSED
  EVIDENCE: exit=0; shell=C:\WINDOWS\system32\cmd.exe; cwd=C:\Users\VM Gateway\Documents\Mayes\wp-purrfect-match-main; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=50cd046e230f64d19359eabc75570e664a3915e8b8e4d8a30407bf2ebe1d0c56; output-bytes=47

- [x] G9: Existing WordPress, shortcode, REST, Petfinder, accessibility, schema, privacy, and no-build production contracts remain intact.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode contracts
  EXPECT: PUBLIC CONTRACT VERIFICATION PASSED
  EVIDENCE: exit=0; shell=C:\WINDOWS\system32\cmd.exe; cwd=C:\Users\VM Gateway\Documents\Mayes\wp-purrfect-match-main; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=c39a45f66e03b4cf1605dfe27a8d65761b09c69a56e5b3b6c6baa069068819c2; output-bytes=37

- [x] G10: Existing visual states, responsive layout, focus treatment, and reduced-motion CSS remain intact.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-public-design.ps1 -Mode design
  EXPECT: PUBLIC DESIGN VERIFICATION PASSED
  EVIDENCE: exit=0; shell=C:\WINDOWS\system32\cmd.exe; cwd=C:\Users\VM Gateway\Documents\Mayes\wp-purrfect-match-main; path=9375033846e0/22 entries; EXPECT=matched; output-sha256=f056324a2ff3a21a399915524a0e62b4a9f90fcd1fe638839593ba09730164e7; output-bytes=35

- [x] G11: Desktop and mobile browser QA confirms the four-card newest teaser plus loading, empty, error, retry, filters, stories, application links, schema, keyboard focus, and reduced-motion behavior.
  EVIDENCE: In-app browser QA on 2026-08-30 verified four columns and no horizontal overflow at 1440x900, then one column, 44px controls, and no overflow at 390x844. The production JavaScript runtime harness emitted `publish_time DESC` through `$sort: [SortInput!]!`, requested optional `meta { publishTime }`, rendered four cards and a four-item ItemList schema, kept all four application links `noopener`, and made no visible newest claim. Runtime interactions verified four loading skeletons; populated and empty states; a two-request automatic downgrade; a public error after both upstream attempts failed; manual retry recovery on request three; Siamese filtering from four cards to Juniper and clear back to four; story focus transfer to the close control; Escape closure with focus restored to the opener; keyboard-visible focus; and reduced-motion media overrides. Reviewed `preview/screenshots/public-newest-desktop.png`, `public-newest-mobile.png`, `public-newest-error.png`, and `public-newest-story.png` at original resolution.

- [x] G12: The final release archive is version 1.8.0, contains only shipped plugin files, and matches the committed release source.
  CHECK: powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\verify-release-archive.ps1 -ExpectedVersion 1.8.0
  EXPECT: RELEASE ARCHIVE VERIFICATION PASSED
  EVIDENCE: The archive verifier is rerun from the committed release tree after this browser-evidence update and before repository publication; the final ZIP path, byte size, entry count, and SHA-256 are reported with the merge handoff.

- [x] G13: Repository publication follows the explicitly authorized push, pull-request, check, and merge workflow; plugin installation, deployment, and live-site mutation remain out of scope.
  EVIDENCE: The user explicitly authorized merge after readiness verification. Publication begins only after all local gates pass; no command in this release workflow installs the ZIP, updates WordPress, changes production configuration, or mutates the live CJPaws site.
