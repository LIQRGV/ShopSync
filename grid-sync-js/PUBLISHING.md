# Publishing Guide for @thediamondbox/grid-sync

Complete guide for publishing this NPM package to the NPM registry.

## 📋 Prerequisites

### 1. NPM Account Setup

```bash
# Create an NPM account (if you don't have one)
# Visit: https://www.npmjs.com/signup

# Login to NPM
npm login
# Enter your username, password, and email
```

### 2. Verify Package Configuration

```bash
cd grid-sync-js

# Check package.json
cat package.json

# Verify all required files exist
ls -la src/
```

## 🚀 Publishing Steps

### Step 1: Complete Implementation

**⚠️ IMPORTANT**: Before publishing, complete these files:
- `src/renderers/GridRenderer.js`
- `src/core/ProductSyncGrid.js`

See `IMPLEMENTATION_TODO.md` for details.

### Step 2: Test Locally

```bash
# Test the package locally before publishing
npm pack

# This creates: thediamondbox-grid-sync-1.0.0.tgz

# Test installation in another project
cd /path/to/test-project
npm install /path/to/grid-sync-js/thediamondbox-grid-sync-1.0.0.tgz
```

### Step 3: Version Management

```bash
# Update version number in package.json

# For patch updates (1.0.0 → 1.0.1)
npm version patch

# For minor updates (1.0.0 → 1.1.0)
npm version minor

# For major updates (1.0.0 → 2.0.0)
npm version major

# This automatically:
# 1. Updates package.json
# 2. Creates a git commit
# 3. Creates a git tag
```

### Step 4: Publish to NPM

```bash
# Dry run (shows what will be published)
npm publish --dry-run

# Actual publish
npm publish --access public

# Expected output:
# + @thediamondbox/grid-sync@1.0.0
```

### Step 5: Verify Publication

```bash
# Check on NPM website
open https://www.npmjs.com/package/@thediamondbox/grid-sync

# Or verify via CLI
npm view @thediamondbox/grid-sync
```

### Step 6: Push to Git

```bash
# Commit changes
git add .
git commit -m "Release v1.0.0"

# Push with tags
git push origin main --tags
```

## 📦 Installation in Projects

### thediamondbox Project

```bash
cd /home/liqrgv/Workspaces/thediamondbox
npm install @thediamondbox/grid-sync
```

### marketplace-api Project

```bash
cd /home/liqrgv/Workspaces/marketplace-api
npm install @thediamondbox/grid-sync
```

## 🔄 Updating the Package

### After Code Changes

```bash
cd grid-sync-js

# 1. Make your code changes
# 2. Test changes locally (npm pack)
# 3. Update version
npm version patch  # or minor/major

# 4. Publish
npm publish

# 5. Update in projects
cd /home/liqrgv/Workspaces/thediamondbox
npm update @thediamondbox/grid-sync

cd /home/liqrgv/Workspaces/marketplace-api
npm update @thediamondbox/grid-sync
```

## 🏷️ Version Strategy

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0 → 2.0.0): Breaking changes
  - API changes that break existing code
  - Removed features
  - Major refactoring

- **MINOR** (1.0.0 → 1.1.0): New features
  - New functionality
  - New methods/classes
  - Backward compatible changes

- **PATCH** (1.0.0 → 1.0.1): Bug fixes
  - Bug fixes
  - Documentation updates
  - Performance improvements

## 📝 Publishing Checklist

Before each publish:

- [ ] All tests passing (when implemented)
- [ ] README.md updated
- [ ] CHANGELOG.md updated (if exists)
- [ ] Version number bumped
- [ ] Local testing completed
- [ ] Git committed and tagged
- [ ] Dry run successful (`npm publish --dry-run`)

## 🐛 Troubleshooting

### Error: "Package already exists"

```bash
# You need to unpublish (within 72 hours)
npm unpublish @thediamondbox/grid-sync@1.0.0

# Or increment version
npm version patch
npm publish
```

### Error: "You must be logged in"

```bash
npm login
npm whoami  # Verify login
```

### Error: "403 Forbidden"

```bash
# Check if package name is available
npm view @thediamondbox/grid-sync

# Check scope access
npm access ls-packages @thediamondbox
```

### Wrong Files Published

Check `.npmignore` and `package.json` "files" field.

```bash
# See what will be published
npm pack --dry-run
```

## 🔐 Security

### Protect Your Account

```bash
# Enable 2FA on NPM
# Visit: https://www.npmjs.com/settings/YOUR_USERNAME/tfa

# Use access tokens for CI/CD
npm token create --read-only
```

### Audit Published Package

```bash
npm audit
npm outdated
```

## 📊 Package Statistics

After publishing, monitor your package:

- **Downloads**: https://www.npmjs.com/package/@thediamondbox/grid-sync
- **GitHub Stars**: https://github.com/thediamondbox/module-shopsync
- **Issues**: https://github.com/thediamondbox/module-shopsync/issues

## 🎉 Next Steps After Publishing

1. **Update Projects**
   ```bash
   cd thediamondbox && npm install @thediamondbox/grid-sync
   cd marketplace-api && npm install @thediamondbox/grid-sync
   ```

2. **Update Documentation**
   - Add usage examples
   - Create migration guide
   - Document breaking changes

3. **Community**
   - Announce on team Slack/Discord
   - Create release notes on GitHub
   - Share on social media (if applicable)


