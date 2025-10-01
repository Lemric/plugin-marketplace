#!/bin/bash

# Setup script for marketplace development

echo "🚀 Setting up LemricPluginBundle Marketplace..."

# Check requirements
command -v php >/dev/null 2>&1 || { echo "❌ PHP is required but not installed."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "❌ Composer is required but not installed."; exit 1; }

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✅ PHP version: $PHP_VERSION"

# Install dependencies
echo "📦 Installing Composer dependencies..."
composer install

# Create directories
echo "📁 Creating required directories..."
mkdir -p dist/{plugins,categories}
mkdir -p tmp

# Validate existing plugins
echo "🔍 Validating existing plugins..."
composer validate

# Build index
echo "🔨 Building marketplace index..."
composer build

# Generate stats
echo "📊 Generating statistics..."
composer stats

echo ""
echo "✅ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure GitHub Pages in repository settings"
echo "2. Set MARKETPLACE_URL environment variable"
echo "3. Push to trigger GitHub Actions"
echo ""
echo "Local commands:"
echo "  composer validate          - Validate all plugins"
echo "  composer build            - Build marketplace index"
echo "  composer stats            - Generate statistics"
echo "  composer validate-plugin  - Validate specific plugin"