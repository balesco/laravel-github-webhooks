# Installation Rapide - Laravel GitHub Webhooks

Ce guide vous permet de mettre en place rapidement le package avec des exemples fonctionnels.

## 🚀 Installation Express (5 minutes)

### 1. Installation du package

```bash
# Cloner le projet ou ajouter le package
composer require laravel/github-webhooks

# Publier la configuration et les migrations
php artisan vendor:publish --tag=github-webhooks-config
php artisan vendor:publish --tag=github-webhooks-migrations

# Exécuter les migrations
php artisan migrate
```

### 2. Configuration rapide

```bash
# Générer un secret webhook
php artisan github-webhooks:generate-secret

# Valider la configuration
php artisan github-webhooks:validate
```

### 3. Copiez un handler d'exemple

```bash
# Copier les handlers d'exemple
cp example/handlers/AutoDeploymentHandler.php app/Webhooks/Handlers/
cp example/handlers/SlackNotificationHandler.php app/Webhooks/Handlers/

# Ou utiliser les handlers intégrés
# Ils sont déjà configurés dans config/github-webhooks.php
```

### 4. Configuration GitHub

1. Allez dans **Settings** → **Webhooks** de votre repository
2. Cliquez **Add webhook**
3. Configurez :
   - **Payload URL**: `https://votre-domaine.com/webhooks/github`
   - **Content type**: `application/json`
   - **Secret**: Le secret généré à l'étape 2
   - **Events**: Cochez les événements désirés

### 5. Test

```bash
# Faire un commit pour tester
git add .
git commit -m "Test webhook"
git push

# Vérifier les logs
php artisan github-webhooks:list
```

## 📋 Configurations Prêtes à l'Emploi

### Configuration Slack (Optionnel)

```bash
# Ajouter dans .env
SLACK_NOTIFICATIONS_ENABLED=true
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
```

### Configuration Déploiement Auto (Optionnel)

```php
// Dans config/github-webhooks.php
'deployment_configs' => [
    'votre-org/votre-repo' => [
        'branches' => [
            'main' => [
                'environment' => 'production',
                'deploy_commands' => [
                    'cd /var/www/html && git pull',
                    'cd /var/www/html && composer install --no-dev',
                    'cd /var/www/html && php artisan migrate --force',
                ],
            ],
        ],
    ],
],
```

## 🎯 Use Cases Populaires

### 1. Notification Simple
- ✅ Handler: `NotificationHandler` (déjà dans le package)
- ✅ Events: `push`, `pull_request`, `issues`
- ✅ Setup: 2 minutes

### 2. Déploiement Auto
- ✅ Handler: `AutoDeploymentHandler` (exemple fourni)
- ✅ Events: `push`, `release`
- ✅ Setup: 10 minutes

### 3. Synchronisation Repository
- ✅ Handler: `RepositoryUpdateHandler` (dans le package)
- ✅ Events: `push`, `pull_request` (merged)
- ✅ Setup: 5 minutes

### 4. Intégration Slack/Discord
- ✅ Handler: `SlackNotificationHandler` (exemple fourni)
- ✅ Events: tous
- ✅ Setup: 5 minutes

## 🔧 Commandes Utiles

```bash
# Gestion des secrets
php artisan github-webhooks:generate-secret
php artisan github-webhooks:generate-secret --show

# Validation
php artisan github-webhooks:validate
php artisan github-webhooks:validate --url=https://votre-domaine.com/webhooks/github

# Gestion des webhooks
php artisan github-webhooks:list
php artisan github-webhooks:list --event=push --unprocessed
php artisan github-webhooks:reprocess 123

# Gestion des repositories
php artisan github-webhooks:list-repos
php artisan github-webhooks:update-repo owner/repo --branch=main
```

## 📱 Exemples de Handlers

### Handler Simple (Logging)
```php
public function handle(string $event, array $payload, Request $request): mixed
{
    Log::info("GitHub {$event} reçu", [
        'repository' => $payload['repository']['full_name'] ?? 'unknown',
    ]);
    
    return ['logged' => true];
}
```

### Handler Slack
```php
public function handle(string $event, array $payload, Request $request): mixed
{
    if ($event === 'push') {
        Http::post(config('slack.webhook_url'), [
            'text' => "🚀 Push sur {$payload['repository']['full_name']}"
        ]);
    }
    
    return ['notified' => true];
}
```

### Handler Déploiement
```php
public function handle(string $event, array $payload, Request $request): mixed
{
    if ($event === 'push' && str_contains($payload['ref'], 'main')) {
        Artisan::call('deploy:production');
        return ['deployed' => true];
    }
    
    return ['skipped' => true];
}
```

## 🚨 Dépannage Express

### Webhook non reçu
```bash
# Vérifier la route
php artisan route:list | grep webhook

# Vérifier les logs
tail -f storage/logs/laravel.log

# Tester avec ngrok
ngrok http 8000
```

### Erreur de signature
```bash
# Regénérer le secret
php artisan github-webhooks:generate-secret --force

# Vérifier la configuration
php artisan github-webhooks:validate
```

### Handlers non exécutés
```bash
# Vérifier la configuration
php artisan config:clear
php artisan config:cache

# Vérifier les handlers
php artisan github-webhooks:list --unprocessed
```

---

🎉 **C'est tout !** Votre système de webhooks GitHub est maintenant opérationnel.

Pour des configurations avancées, consultez le README principal et les exemples dans le dossier `example/`.
