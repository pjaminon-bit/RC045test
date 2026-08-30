# PhotoSwipe provenance

This directory vendors **PhotoSwipe 5.4.4** without local source modifications.

Upstream source:
- Repository: `https://github.com/dimsemenov/PhotoSwipe`
- Tag: `v5.4.4`
- Tag commit: `fd85184b450f451bc4aa2697f6d0a79304d13473`
- License: MIT

Vendored files are copied from that tag and are pinned by their Git blob SHA-1 (the same content identifier GitHub exposes for the upstream files):

| Vendored file | Upstream path | Git blob SHA-1 |
| --- | --- | --- |
| `LICENSE` | `LICENSE` | `5e0ff4d6c825895d919e888b6985caef745bbb74` |
| `photoswipe-lightbox.esm.min.js` | `dist/photoswipe-lightbox.esm.min.js` | `cac7e4e0f8b8bed99b14273c544652f5c208808e` |
| `photoswipe.css` | `dist/photoswipe.css` | `686dfc36a68aa72bb5bd94da49b391b76a29ba9b` |
| `photoswipe.esm.min.js` | `dist/photoswipe.esm.min.js` | `cc924b79afa73872c466467d64da07bfe0d0953d` |

`tests/audit-vendor-provenance.php` recalculates the Git blob identifier from the checked-in bytes and fails CI if any vendored file changes without an explicit provenance update.

When upgrading PhotoSwipe, verify the new official tag, replace the vendored files from upstream, update the tag commit and blob identifiers above, retain the upstream license, and run the full regression and supply-chain workflows.