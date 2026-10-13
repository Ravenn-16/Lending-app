# Philippine address reference snapshot

Source: [PSGC Cloud v2](https://psgc.cloud/api-docs/v2), retrieved October 5, 2026. This is a third-party reference snapshot, not a claim of the latest official PSA release. Review against newer PSA releases when updating geographic names or boundaries. No borrower information is sent to the provider.

The bundled `philippine-addresses.json` contains 82 provinces plus 20 area choices, 1,642 city/municipality or special-area entries and 42,027 barangays. Laravel serves these local lists; no external requests are made during form use or validation.

Source collections: `/api/v2/provinces`, `/api/v2/cities-municipalities`, and `/api/v2/regions/{code}/barangays` for each region returned by `/api/v2/regions`.

Normalization:

- Preserve ten-digit PSGC codes as strings and trim surrounding name whitespace.
- Match provinces and cities using hierarchical code prefixes, rather than provider text parent names, which are inconsistent for some independent cities and NCR records.
- Group NCR under `NCR` (Metro Manila). Fold Manila sub-municipality barangays into City of Manila to keep the requested three-level form.
- List cities without a province-code parent as `CITY-{code}` independent city areas. These area IDs are application grouping keys, not official province codes.
- Group source special geographic area entries under `BARMM-SGA`. Names reflect the source snapshot.
- Sort options by name, check unique barangay codes, require every barangay to map to an existing city, and require every selectable city to have barangays.

When updating the snapshot, preserve stored borrower codes and check renamed, merged, or retired areas before deployment. Existing display addresses are not rewritten automatically. The migration only adds nullable code columns to borrower records.
