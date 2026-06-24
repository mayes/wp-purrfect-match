# Developer tools

Utilities for working on the Purrfect Match plugin. **Nothing here ships with
the plugin** or runs at request time — these are local/manual aids only.

## `petfinder-graphql-explorer.html`

A standalone, dependency-free GraphQL playground for Petfinder's public widget
endpoint (`psl.petfinder.com/graphql`). Open it in a browser and run queries
live.

**Why a browser tool?** The endpoint sits behind Akamai bot protection that
returns `403 Access Denied` to server-side / scripted requests (curl, PHP,
CI). Real browsers pass, and the endpoint allows cross-origin requests, so
exploration has to happen client-side — exactly like the widget itself.

### Usage

1. Open `petfinder-graphql-explorer.html` in your browser.
2. Pick a preset (or write your own query) and press **Run** (`⌘/Ctrl+Enter`).
3. To scope `SearchAnimal` to a shelter, run **GetOrganization** first and copy
   the returned `organizationId` (UUID) into the SearchAnimal variables under
   `filters.organization_id`.

Preset groups:

- **Known operations** — `GetOrganization`, `SearchAnimal`, `AllAnimalAttributes`
  (the same operations the public widget uses).
- **Schema introspection** — root query fields, all types, one type's fields.
  Production servers often disable introspection; if so you'll get an
  "introspection is not allowed" error.
- **Field probes** — the fallback when introspection is off. Add candidate
  fields and read the `errors` array: `Cannot query field "sex" on type …`
  means the field does **not** exist; no error means it does. This is how to
  confirm whether a per-animal `sex`/`gender` field exists (and therefore
  whether the Gender filter can be added back to the plugin).

### CORS / fallback

If opening via `file://` triggers a CORS or network error, either serve the
file from a local web server, or paste the query into the DevTools console
while on a `petfinder.com` page (same trusted origin, no CORS concerns).
