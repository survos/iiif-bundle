# HANDOFF — diva.js viewer + folio IIIF manifests

Status as of 2026-06-17. Picks up the diva.js 7.2.6 integration work.

## Done & committed (mono `main`)

diva.js 7 viewer is fully working in-app (verified in zm `/iiif/debug`):

- `b6425220` diva.js → 7.2.6 (ESM, OSD peer-dep shim, event relay)
- `85adfd2c` light surface so diva's dark chrome/icons show
- `7621cd67` kit: `survos_stimulus()` Twig helper + UX naming guard
- `f2e2e824` iiif: standard `@survos/iiif-bundle` naming + `/iiif/debug` viewer
- `8240bdd5` / `8d3671c7` diva mounts a **flex child of a non-flex wrapper**
  (the only mount shape where Stimulus/Turbo don't crash Elm AND the toolbar
  buttons work — see the controller comments for the full why)

Naming convention (now documented in `bu/AGENTS.md`): everything keys off the
composer package name `@survos/iiif-bundle`; never strip `-bundle`; no controller
`name` overrides; `composer.json` needs top-level `"keywords": ["symfony-ux"]`.

## The remaining task: folio manifests don't render in diva

**Problem.** diva 7's decoder REJECTS valid Presentation-3 manifests whose image
bodies have no IIIF Image API `service` — reports "Invalid IIIF response body".
Confirmed via A/B test: identical manifest, add a `service` → parses; remove it →
rejected. Even the IIIF cookbook's recipe 0001 (plain image) fails. Filed upstream:
**https://github.com/DDMAL/diva.js/issues/562** (offered a PR to make `service`
optional + fall back to a plain-image OSD source). If the author takes that PR,
this whole task evaporates — folio's plain manifests would just work.

**Our fix (independent of upstream): give the manifest a real IIIF service via imgproxy.**
imgproxy already speaks IIIF Image API v3 — `ImgproxyUrlBuilder::iiifBase($srcUrl)`
returns a signed `https://imgproxy.survos.com/iiif3/{base64src}` base. The endpoint
is enabled (an unsigned `/iiif3/.../info.json` returns 403, not 404). Appending
`/info.json` or `/full/max/0/default.jpg` gives the Image API responses diva needs.

### What's left to wire (in `folio-bundle` `FolioController::iiifManifest`)

`ImgproxyUrlBuilder $imgproxy` is **already injected** into the constructor. Only the
image-body build (around lines 321-327, the `'body' => [...]` array) still needs to
use it. Replace the plain body with a service-backed one:

```php
$body = [
    'id'     => $img['url'],
    'type'   => 'Image',
    'format' => $img['format'],
    'height' => $img['height'],
    'width'  => $img['width'],
];
try {
    $iiifBase = $this->imgproxy->iiifBase($img['url']);
    $body['id']      = $iiifBase.'/full/max/0/default.jpg';
    $body['service'] = [[
        'id'      => $iiifBase,
        'type'    => 'ImageService3',
        'profile' => 'level2',   // verify imgproxy's supported level
    ]];
} catch (\Throwable) {
    // imgproxy host not configured — leave the plain body (valid IIIF; diva won't render it)
}
// ...then use $body in the annotation items[]
```

### Verify

1. `/iiif/debug?manifest=https://zm.wip/folio/mus/cleveland/obj/121621/manifest.json`
   should render in diva (it currently shows "Invalid IIIF response body").
2. Confirm imgproxy actually serves `{iiifBase}/info.json` (200, IIIF JSON) and a
   tile (`/full/max/0/default.jpg`). If 403, the signing/`iiifBase` is off; if the
   info.json is malformed, check imgproxy's IIIF level / config.
3. Sanity-check canvas `width`/`height`: today they're placeholders (1000×1000 when
   the page row lacks real dims). diva uses the service's info.json for tiling, but
   big mismatches can look wrong — consider pulling real dims from imgproxy `/info`.

### Open questions

- imgproxy IIIF **level** (0/1/2?) → sets the `profile` and what region/size diva
  can request. Test before trusting `level2`.
- Whether to do this in `FolioController` or move manifest-building into a service
  (iiif-bundle `ManifestBuilder` already exists and is the cleaner home).

## Other loose ends

- `folio:migrate` not run for `mus/walters` in zm → its manifest 500s ("Folio file
  not found"). Data, not code. cleveland (121621) has data and 200s.
- zm app changes (uncommitted in zm's own repo, intentional): `controllers.json`
  `@survos/iiif-bundle` entry, importmap `diva.js@7.2.6`, `survos/storage-bundle`
  required (fixes a missing `FlysystemBundle` on cache rebuild).
- Publish order when releasing: **kit-bundle** must ship with/before iiif-bundle
  (iiif templates call `survos_stimulus`, which lives in kit).
- Docs PR open: DDMAL/diva.simssa.ca#1 (events + ESM/OpenSeadragon notes), from
  issue #561.
