#!/bin/bash
# Script untuk install @thediamondbox/grid-sync dari GitHub subdirectory
# Workaround karena NPM tidak support subdirectory installation

REPO_URL="git@github.com:The-Diamond-Box/stock-sync.git"
BRANCH="feature/unified-grid-sync-package"
SUBDIR="grid-sync-js"
TEMP_DIR=$(mktemp -d)

echo "📦 Installing @thediamondbox/grid-sync from GitHub..."
echo "Repository: $REPO_URL"
echo "Branch: $BRANCH"
echo "Subdirectory: $SUBDIR"
echo ""

# Clone repository
echo "⬇️  Cloning repository..."
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$TEMP_DIR" || {
    echo "❌ Failed to clone repository"
    exit 1
}

# Navigate to subdirectory
cd "$TEMP_DIR/$SUBDIR" || {
    echo "❌ Subdirectory $SUBDIR not found"
    exit 1
}

# Create tarball
echo "📦 Creating package tarball..."
npm pack || {
    echo "❌ Failed to create package"
    exit 1
}

# Find the created tarball
TARBALL=$(ls thediamondbox-grid-sync-*.tgz 2>/dev/null | head -n 1)

if [ -z "$TARBALL" ]; then
    echo "❌ Tarball not found"
    exit 1
fi

# Move tarball to original directory
echo "✅ Package created: $TARBALL"
mv "$TARBALL" "$OLDPWD/"

# Cleanup
cd "$OLDPWD"
rm -rf "$TEMP_DIR"

echo ""
echo "✅ Done! Now you can install with:"
echo "   npm install ./$TARBALL"
echo ""
echo "Or add to package.json:"
echo '   "@thediamondbox/grid-sync": "file:./'$TARBALL'"'
