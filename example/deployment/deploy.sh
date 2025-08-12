#!/bin/bash

# Script de déploiement automatique - Exemple
# Ce script est exécuté par AutoDeploymentHandler

set -e  # Arrêter en cas d'erreur

# Variables
REPO_NAME="$1"
BRANCH="$2"
ENVIRONMENT="$3"
WORK_DIR="$4"

echo "🚀 Starting deployment for $REPO_NAME ($BRANCH) to $ENVIRONMENT"

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Fonction de rollback
rollback() {
    log_error "Deployment failed! Rolling back..."
    
    if [ -f "/var/www/html/backup/previous_version.tar.gz" ]; then
        cd /var/www/html
        tar -xzf backup/previous_version.tar.gz
        sudo systemctl reload php8.2-fpm
        log_info "Rollback completed"
    else
        log_error "No backup found for rollback"
    fi
    
    exit 1
}

# Piège pour capturer les erreurs
trap rollback ERR

# 1. Sauvegarde de la version actuelle
log_info "Creating backup of current version..."
if [ -d "/var/www/html" ]; then
    mkdir -p /var/www/html/backup
    tar -czf "/var/www/html/backup/previous_version.tar.gz" -C /var/www/html --exclude=backup .
fi

# 2. Synchronisation des fichiers
log_info "Syncing files to production server..."
rsync -avz --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/framework/views' \
    "$WORK_DIR/" /var/www/html/

# 3. Installation des dépendances
log_info "Installing dependencies..."
cd /var/www/html

if [ "$ENVIRONMENT" = "production" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    composer install --no-interaction
fi

# 4. Configuration Laravel
log_info "Configuring Laravel..."

# Créer les liens symboliques
php artisan storage:link --force

# Cache des configurations
if [ "$ENVIRONMENT" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# 5. Migration de la base de données
log_info "Running database migrations..."
php artisan migrate --force

# 6. Optimisations
log_info "Running optimizations..."
php artisan optimize

# 7. Redémarrage des services
log_info "Restarting services..."
php artisan queue:restart
sudo systemctl reload php8.2-fpm

# 8. Test de santé
log_info "Running health check..."
if command -v curl &> /dev/null; then
    if curl -f -s "https://$(hostname)/health-check" > /dev/null; then
        log_info "Health check passed ✅"
    else
        log_error "Health check failed ❌"
        rollback
    fi
else
    log_warn "curl not available, skipping health check"
fi

# 9. Nettoyage
log_info "Cleaning up..."
rm -rf /var/www/html/backup/previous_version.tar.gz

# 10. Notification de succès
log_info "Deployment completed successfully! 🎉"

# Optionnel: Envoyer une notification
if [ ! -z "$SLACK_WEBHOOK_URL" ]; then
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"✅ Deployment successful for $REPO_NAME ($BRANCH) to $ENVIRONMENT\"}" \
        "$SLACK_WEBHOOK_URL"
fi

exit 0
