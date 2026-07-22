# Workspace Rules & Technical Documentation for Sunday School Project

This file contains rules and guidelines that AI agents and developers MUST follow when editing this workspace.

## 1. Intelligent Search Standard
All search elements added to this project (inputs, selectors, autocomplete suggestions) MUST be intelligent.
- Standard search utility functions are centralized in [search_intelligent.js](file:///Users/peterfayez/Documents/Sunday%20School/sunday-school.rf.gd/js/search_intelligent.js).
- When implementing a search, include this JS file or copy the functions to the local page context.
- Score search queries using `getMatchScore(item, query, matchFields)` and sort results in descending order by `_score`.

## 2. Page & Styling Consistency
- Match standard styles (glassmorphism overlays, border-radius tokens, and Baloo Bhaijaan typography).
- Keep layouts fully responsive, especially for mobile device views.

## 3. Mandatory Absolute Root API Path
- All frontend fetch calls to `api.php` from subdirectories (e.g. `/user/login/`, `/uncle/trip/`) MUST use the absolute root URL `/api.php` instead of relative `api.php`.

## 4. WhatsApp OTP & Password Recovery Architecture
- Full documentation for WhatsApp OTP verification and password recovery is maintained in [WHATSAPP_BOT_DOCUMENTATION.md](file:///Users/peterfayez/Documents/Sunday%20School/sunday-school.rf.gd/WHATSAPP_BOT_DOCUMENTATION.md).
- **Server Bot Script Updates (Replit)**: The WhatsApp verification bot runs on Replit and has a Replit AI Agent. When updates to the WhatsApp bot script (`src/lib/whatsapp.ts`) are needed, AI agents can generate a prompt for the Replit Agent, or the user can manually navigate to the Replit file location to replace the script code.
