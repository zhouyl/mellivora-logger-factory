#!/bin/bash

# Verify GitHub Actions Workflows
# This script verifies that all CI/CD workflows are properly configured and working

set -e

echo "🔍 Verifying GitHub Actions Workflows..."
echo "========================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    local status=$1
    local message=$2
    if [ "$status" = "OK" ]; then
        echo -e "${GREEN}✅ $message${NC}"
    elif [ "$status" = "WARNING" ]; then
        echo -e "${YELLOW}⚠️  $message${NC}"
    else
        echo -e "${RED}❌ $message${NC}"
    fi
}

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    print_status "ERROR" "Not in a git repository"
    exit 1
fi

print_status "OK" "Git repository detected"

# Check if .github/workflows directory exists
if [ ! -d ".github/workflows" ]; then
    print_status "ERROR" ".github/workflows directory not found"
    exit 1
fi

print_status "OK" ".github/workflows directory exists"

# Check required workflow files
workflows=("ci.yml" "coverage.yml" "quality.yml" "release.yml")
for workflow in "${workflows[@]}"; do
    if [ -f ".github/workflows/$workflow" ]; then
        print_status "OK" "Workflow file $workflow exists"
    else
        print_status "ERROR" "Workflow file $workflow is missing"
        exit 1
    fi
done

echo ""
echo "🧪 Running Local Tests..."
echo "========================="

# Run composer validation
echo "Validating composer.json..."
if composer validate --strict; then
    print_status "OK" "composer.json is valid"
else
    print_status "ERROR" "composer.json validation failed"
    exit 1
fi

# Check if dependencies are installed
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Run PHP syntax check
echo "Checking PHP syntax..."
if find . -name "*.php" ! -path "./vendor/*" -exec php -l {} \; | grep -v "No syntax errors detected" > /dev/null; then
    print_status "ERROR" "PHP syntax errors found"
    exit 1
else
    print_status "OK" "No PHP syntax errors"
fi

# Run tests
echo "Running test suite..."
if composer test > /dev/null 2>&1; then
    print_status "OK" "All tests pass"
else
    print_status "ERROR" "Some tests failed"
    exit 1
fi

# Run code style check
echo "Checking code style..."
if composer cs-check > /dev/null 2>&1; then
    print_status "OK" "Code style is compliant"
else
    print_status "WARNING" "Code style issues found (run 'composer cs-fix')"
fi

echo ""
echo "📋 Checking Documentation..."
echo "============================"

# Check required documentation files
docs=("README.md" "LICENSE" "docs/LARAVEL.md" "docs/TESTING.md")
for doc in "${docs[@]}"; do
    if [ -f "$doc" ]; then
        print_status "OK" "Documentation file $doc exists"
    else
        print_status "ERROR" "Documentation file $doc is missing"
        exit 1
    fi
done

# Check README sections
if grep -q "## Installation" README.md; then
    print_status "OK" "README has Installation section"
else
    print_status "ERROR" "README missing Installation section"
    exit 1
fi

if grep -q "## Usage" README.md; then
    print_status "OK" "README has Usage section"
else
    print_status "ERROR" "README missing Usage section"
    exit 1
fi

echo ""
echo "🔧 Checking Configuration Files..."
echo "=================================="

# Check configuration files
configs=("phpunit.xml.dist" ".php-cs-fixer.php" "composer.json")
for config in "${configs[@]}"; do
    if [ -f "$config" ]; then
        print_status "OK" "Configuration file $config exists"
    else
        print_status "ERROR" "Configuration file $config is missing"
        exit 1
    fi
done

echo ""
echo "🎉 All Workflow Verifications Passed!"
echo "====================================="
echo ""
echo "Your repository is ready for:"
echo "• Continuous Integration (CI)"
echo "• Code Coverage reporting"
echo "• Code Quality checks"
echo "• Automated releases"
echo ""
echo "Next steps:"
echo "1. Push your changes to trigger workflows"
echo "2. Check GitHub Actions tab for workflow status"
echo "3. Create a tag to trigger release workflow"
echo ""
