# Image selection from provider metadata

Prefer `IiifUrl::preferredImage($images, $info, $width)` when a provider advertises image derivatives. Normalize entries to `url`, `format` (MIME type), and optional `width`. JPEG URLs are returned unchanged; the helper selects the smallest suitable image or the largest available. Without an advertised JPEG, it delegates to `fromInfo`.

Use `IiifUrl::fromInfo($info, $width)` when constructing a request from Image API 2/3 service information. It considers advertised sizes, service limits and resizing capability. `dimensions($info)` validates the source canvas for coordinate mapping. Unknown API versions fail explicitly.

LOC advertises JPEG derivatives that work where its `/full/max/` request returns 400. Do not assume one collection's accepted size syntax works across all services. Existing `imageUrl` callers remain compatible; migrate them when service metadata is available.
