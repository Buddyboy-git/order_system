# Project Outline

## Overview
This document tracks the phases, status, and next steps for all major projects in the Vendor Data Collection system.

---

## Current Projects

### 1. Data Extraction (Python)
- **Scripts:** OCR, PDF-to-CSV, data cleaning
- **Status:** In production, ongoing improvements
- **Next:** Refine brand/description logic, automate batch runs

### 2. Data Import (PHP/MySQL)
- **Scripts:** CSV importers, admin tools
- **Status:** Stable, used for loading product tables
- **Next:** Add error logging, improve duplicate handling

### 3. Web Interface (PHP/XAMPP)
- **Location:** D:/xampp/htdocs/orders
- **Status:** Live, supports order entry and admin
- **Next:** UI/UX improvements, vendor dashboard

### 4. Data Management
- **Data:** CSVs, PDFs, test data
- **Status:** Centralized in /data/
- **Next:** Archive old files, document data sources

---

## Phase Tracker
| Project         | Phase         | Status      | Owner | Next Action                |
|----------------|---------------|------------|-------|----------------------------|
| Extraction     | Dev/Prod      | Active     | Mike  | Refine parsing, batch jobs |
| Import         | Prod          | Stable     | Mike  | Add logging                |
| Web Interface  | Prod          | Active     | Mike  | UI/UX improvements         |
| Data Mgmt      | Ongoing       | Active     | Mike  | Archive, document          |

---

## Notes
- All code and data should live in `D:/users/miket/order_system` (except live PHP in XAMPP)
- Update this outline as projects progress
- See README.md for structure and workflow
