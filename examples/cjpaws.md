# CJ Paws — configuration reference

This is the exact configuration for **CJ Paws** ([cjpaws.org](https://cjpaws.org),
Petfinder organization **FL1629**). The plugin ships with *generic* defaults so
other shelters don't inherit these values — this file keeps the CJ Paws setup
recorded and easy to re-apply.

## Option A — Settings page

Go to **Settings → Purrfect Match** and enter:

| Setting                | Value                          |
| ---------------------- | ------------------------------ |
| Petfinder organization ID | `FL1629`                    |
| Animal type            | Cats                           |
| Adoption status        | Adoptable                      |
| Maximum pets to load   | `100`                          |
| Pets per page          | `24`                           |
| Columns (desktop)      | `3`                            |
| Hide breed             | Off (show breed + breed filter) |
| Heading                | `Find your purr-fect match`    |
| Eyebrow label          | `Adoptable Cats`               |
| Subheading             | `Filter by breed, size, and age.` |
| Brand color            | `#e93396`                      |
| Organization name      | `CJ Paws`                      |
| Organization website   | `https://cjpaws.org`           |
| Adopt-a-Pet shelter URL | `https://www.adoptapet.com/shelter/152978-cjpaws-rescue-st-petersburg-florida` |
| Petfinder member URL   | `https://www.petfinder.com/member/us/fl/st-petersburg/cjpaws-inc-fl1629/` |

Then add `[purrfect_match]` to any page or post.

## Option B — Self-contained shortcode

This renders the CJ Paws widget regardless of the saved settings (handy for a
specific page, or if the site is shared):

```text
[purrfect_match
  organization="FL1629"
  type="cat"
  status="adoptable"
  limit="100"
  per_page="24"
  columns="3"
  hide_breed="false"
  title="Find your purr-fect match"
  eyebrow="Adoptable Cats"
  subtitle="Filter by breed, size, and age."
  brand="#e93396"
  org_name="CJ Paws"
  org_website="https://cjpaws.org"
  adoptapet_url="https://www.adoptapet.com/shelter/152978-cjpaws-rescue-st-petersburg-florida"
  petfinder_member_url="https://www.petfinder.com/member/us/fl/st-petersburg/cjpaws-inc-fl1629/"]
```

## Notes

- `FL1629` is CJ Paws' Petfinder display ID; the plugin resolves it to the
  internal organization UUID automatically.
- The original Petfinder pet-scroller embed used `hideBreed="true"`, but the
  custom "Find your purr-fect match" UI features breed (name + filter), so this
  configuration shows it (`hide_breed="false"`).
- Advanced endpoint settings (GraphQL endpoint, photo CDN, Petfinder base URL)
  are left at their defaults.
