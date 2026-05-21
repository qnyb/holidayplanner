#!/bin/sh

GREEN="\033[0;32m"
BLUE="\033[0;34m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
NC="\033[0m" # No Color

log() {
  echo "${BLUE}▶ $1${NC}"
}

success() {
  echo "${GREEN}✔ $1${NC}"
}

warn() {
  echo "${YELLOW}⚠ $1${NC}"
}

error() {
  echo "${RED}✖ $1${NC}"
}

echo ""
echo "=========================================="
echo "   🚀 Laravel Deploy & Worker Bootstrap"
echo "=========================================="
echo ""

# Artisan kontrol
log "Checking Laravel installation..."

if [ ! -f "$APP_BASE_DIR/artisan" ]; then
  error "artisan not found in $APP_BASE_DIR"
  exit 1
fi

success "Laravel project found"

cd "$APP_BASE_DIR" || exit 1


# Composer scripts
log "Running Composer post-autoload-dump..."
composer run-script post-autoload-dump
success "Autoload & package discovery done"

log "Running Composer post-update-cmd..."
composer run-script post-update-cmd || warn "post-update-cmd skipped"
success "Assets publish completed"


# Storage
log "Linking storage..."
php artisan storage:link || warn "Storage already linked"
success "Storage ready"


# Migrations
log "Running database migrations..."
php artisan migrate --force
success "Database migrated"


# Seeder
log "Running database seeders..."
php artisan db:seed --force || warn "Seeders skipped"
success "Seed completed"


# Optimization
log "Optimizing application..."
php artisan optimize
success "Optimization completed"


# Queue / Horizon
log "Restarting queue workers..."
php artisan horizon:terminate || true
php artisan horizon:purge || true
php artisan queue:restart || true
success "Workers restarted"


# Supervisor
echo ""
log "Checking Supervisor..."

if pgrep -x supervisord >/dev/null 2>&1; then
  warn "Supervisor is already running"
  exit 0
fi

log "Starting Supervisor..."
/usr/bin/supervisord -c /etc/supervisor/conf.d/laravel.conf

success "Supervisor started"


echo ""
echo "=========================================="
echo "   ✅ Deployment Completed Successfully"
echo "=========================================="
echo ""