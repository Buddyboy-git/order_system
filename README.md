# Vendor Data Collection

This repository centralizes all scripts, data, and documentation for vendor data extraction, transformation, and loading.

## Structure

- `python/` — All Python scripts and modules
- `php/` — All PHP scripts (source of truth, not live server)
- `data/` — CSVs, PDFs, and sample/test data
- `docs/` — Documentation, project outlines, specs
- `sql/` — SQL schema, migration, or query files

## Workflow

- Edit and version-control everything here.
- Only copy/sync PHP files to `D:/xampp/htdocs/orders` when you want to update the live server.
- Keep all data and code organized by type.

## Getting Started

1. Clone or copy this repo to your working directory.
2. Use the provided folder structure for all new scripts and data.
3. See `/docs/` for project outlines and phase tracking.

## Syncing to XAMPP

- Edit PHP in `php/`, then copy to `D:/xampp/htdocs/orders` as needed.
- Use a batch or PowerShell script for automation (see `/docs/sync_instructions.md`).

---

For questions or to add new phases, update `/docs/project-outline.md`.
