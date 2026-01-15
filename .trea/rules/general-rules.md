# Project Rules
DO NOT use npm. Always use pnpm for package management.
DO NOT do any extra work that I haven't asked for.

## Naming Conventions
- Frontend: camelCase ONLY
- Backend/DB: snake_case only  
- Middleware auto-converts (CamelCaseResponse, ConvertCamelCase)
- Never manually convert
- Never use snake_case in frontend code

## Translations (Spatie Translatable)
- Backend returns simple translated strings for current locale
- Never send or expect nested objects like `{"title":{"en":"","ar":""}}`
- Always expect `{"title":"Translated value"}`

## Permissions & Seeding
- Run `php artisan db:seed --class=PermissionSeeder` after modifying PermissionSeeder.php

## UI Standards
- Use Tabler Icons exclusively
- No other icon libraries
