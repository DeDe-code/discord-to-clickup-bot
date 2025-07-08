# Git Commit Guidelines for Discord-to-ClickUp Bot

## ✅ SAFE TO COMMIT (These files should be in GitHub)

### Documentation

- ✅ `README.md` - Project documentation
- ✅ `project-summary.md` - Development timeline
- ✅ `project-diary.md` - Development log
- ✅ `backend/LARAVEL_QUICKSTART.md` - Setup guide
- ✅ `.env.example` - Environment template (no secrets)

### Application Code

- ✅ `backend/laravel-server/app/` - All PHP application code
- ✅ `backend/laravel-server/config/` - Laravel configuration
- ✅ `backend/laravel-server/database/migrations/` - Database schema
- ✅ `backend/laravel-server/routes/` - API routes
- ✅ `backend/laravel-server/composer.json` - PHP dependencies
- ✅ `backend/laravel-server/artisan` - Laravel CLI
- ✅ `frontend/src/` - Vue.js source code
- ✅ `frontend/package.json` - Frontend dependencies

### Configuration

- ✅ `.gitignore` files - Git ignore rules
- ✅ `backend/laravel-server/.env.example` - Laravel env template

## ❌ NEVER COMMIT (These contain secrets or are too large)

### Sensitive Files with API Keys/Tokens

- ❌ `backend/.env` - Contains API secrets
- ❌ `backend/laravel-server/.env` - Contains API secrets
- ❌ `backend/clickup_token.json` - OAuth token
- ❌ `backend/laravel-server/storage/app/clickup_token.json` - OAuth token
- ❌ `frontend/.env` - May contain secrets

### Database Files

- ❌ `backend/laravel-server/database/database.sqlite` - Contains data
- ❌ Any `*.sqlite` or `*.db` files

### Dependencies (Too Large, Auto-Generated)

- ❌ `backend/laravel-server/vendor/` - 84MB PHP packages
- ❌ `node_modules/` - Node.js packages (if present)

### Logs and Temporary Files

- ❌ `backend/laravel-server/storage/logs/*.log` - Runtime logs
- ❌ `*.log` files anywhere
- ❌ `backend/laravel-server/storage/framework/cache/` - Cache files

### Archives and Test Files

- ❌ `Ddiscord-bot-backend.zip` - Archive file
- ❌ `test_integration.php` - Temporary test file
- ❌ `package-lock.json` (root level) - Obsolete file

## 🔧 COMMANDS TO CLEAN UP

```bash
# Remove files that shouldn't be committed
git rm --cached Ddiscord-bot-backend.zip
git rm --cached test_integration.php
git rm --cached package-lock.json

# Add safe files
git add README.md
git add project-summary.md
git add project-diary.md
git add backend/LARAVEL_QUICKSTART.md
git add backend/.gitignore
git add backend/laravel-server/app/
git add backend/laravel-server/config/
git add backend/laravel-server/database/migrations/
git add backend/laravel-server/routes/
git add backend/laravel-server/composer.json
git add backend/laravel-server/.gitignore
git add backend/laravel-server/.env.example
git add frontend/src/
git add frontend/package.json
git add frontend/.gitignore

# Commit the changes
git commit -m "Add Discord-to-ClickUp bot implementation

- Pure PHP Discord bot using team-reflex/discord-php
- Laravel backend with API endpoints
- Vue.js frontend for monitoring
- Complete documentation and setup guides
- Fixed duplicate message handling
- Removed Node.js dependencies"
```

## 📋 SUMMARY

- **Total files to commit**: ~50-100 source code files
- **Files to exclude**: ~5000+ vendor files, logs, secrets
- **Sensitive files protected**: 5+ environment/token files
- **Archives excluded**: 1 zip file (likely large)
