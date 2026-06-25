# Workspace Rules for Sunday School Project

This file contains rules and guidelines that AI agents and developers MUST follow when editing this workspace.

## 1. Intelligent Search Standard
All search elements added to this project (inputs, selectors, autocomplete suggestions) MUST be intelligent.
- Standard search utility functions are centralized in [search_intelligent.js](file:///Users/peterfayez/Documents/Sunday%20School/sunday-school.rf.gd/js/search_intelligent.js).
- When implementing a search, include this JS file or copy the functions to the local page context.
- Score search queries using `getMatchScore(item, query, matchFields)` and sort results in descending order by `_score`.

## 2. Page & Styling Consistency
- Match standard styles (glassmorphism overlays, border-radius tokens, and Baloo Bhaijaan typography).
- Keep layouts fully responsive, especially for mobile device views.
