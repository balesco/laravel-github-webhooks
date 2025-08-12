# Laravel GitHub Webhooks Package

Un package Laravel complet pour gérer les webhooks GitHub avec stockage en base de données, validation de signature et gestion d'événements.

## Installation

1. Ajoutez le package à votre `composer.json` local :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./Packages/github-webhooks"
        }
    ],
    "require": {
        "laravel/github-webhooks": "*"
    }
}
```

2. Installez le package :

```bash
composer require laravel/github-webhooks
```

3. Publiez la configuration et les migrations :

```bash
php artisan vendor:publish --tag=github-webhooks-config
php artisan vendor:publish --tag=github-webhooks-migrations
```

4. Exécutez les migrations :

```bash
php artisan migrate
```

## Configuration

### Variables d'environnement

Ajoutez ces variables à votre fichier `.env` :

```env
# Secret pour valider les webhooks GitHub (optionnel mais recommandé)
GITHUB_WEBHOOK_SECRET=your-webhook-secret

# Préfixe de route (par défaut: webhooks)
GITHUB_WEBHOOK_ROUTE_PREFIX=webhooks

# Stocker les webhooks en base de données (par défaut: true)
GITHUB_WEBHOOK_STORE=true
```

### Configuration GitHub

1. Allez dans les paramètres de votre repository GitHub
2. Cliquez sur "Webhooks" dans le menu de gauche
3. Cliquez sur "Add webhook"
4. Configurez :
   - **Payload URL**: `https://votre-domaine.com/webhooks/github`
   - **Content type**: `application/json`
   - **Secret**: Votre secret (même que `GITHUB_WEBHOOK_SECRET`)
   - **Events**: Sélectionnez les événements que vous voulez recevoir

## Utilisation

### Handlers personnalisés

Créez des handlers pour traiter les événements GitHub :

```php
<?php

namespace App\Webhooks\Handlers;

use Illuminate\Http\Request;
use Laravel\GitHubWebhooks\Contracts\WebhookHandler;

class DeploymentHandler implements WebhookHandler
{
    public function handle(string $event, array $payload, Request $request): mixed
    {
        if ($event === 'push') {
            $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
            
            if ($branch === 'main') {
                // Déclencher un déploiement
                $this->triggerDeployment($payload);
            }
        }

        return ['deployed' => true];
    }

    private function triggerDeployment(array $payload): void
    {
        // Votre logique de déploiement
    }
}
```

### Enregistrement des handlers

Dans `config/github-webhooks.php` :

```php
'handlers' => [
    'push' => [
        App\Webhooks\Handlers\DeploymentHandler::class,
    ],
    'pull_request' => [
        App\Webhooks\Handlers\PullRequestHandler::class,
    ],
    // Handler pour tous les événements
    '*' => [
        App\Webhooks\Handlers\LogAllEventsHandler::class,
    ],
],
```

### Utilisation programmatique

```php
use Laravel\GitHubWebhooks\GitHubWebhooks;

// Enregistrer un handler à la volée
GitHubWebhooks::on('push', function ($event, $payload, $request) {
    // Traiter l'événement push
    return ['status' => 'processed'];
});

// Enregistrer un handler pour plusieurs événements
GitHubWebhooks::on(['push', 'pull_request'], new MyCustomHandler());
```

### Écouter les événements

```php
use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;

// Dans votre EventServiceProvider
protected $listen = [
    GitHubWebhookReceived::class => [
        App\Listeners\HandleGitHubWebhook::class,
    ],
];
```

```php
<?php

namespace App\Listeners;

use Laravel\GitHubWebhooks\Events\GitHubWebhookReceived;

class HandleGitHubWebhook
{
    public function handle(GitHubWebhookReceived $event): void
    {
        $eventType = $event->eventType;
        $payload = $event->payload;
        $deliveryId = $event->deliveryId;

        // Votre logique personnalisée
    }
}
```

## Commandes Artisan

### Lister les webhooks

```bash
# Lister tous les webhooks
php artisan github-webhooks:list

# Filtrer par type d'événement
php artisan github-webhooks:list --event=push

# Voir seulement les webhooks non traités
php artisan github-webhooks:list --unprocessed

# Limiter le nombre de résultats
php artisan github-webhooks:list --limit=5
```

### Reprocesser un webhook

```bash
php artisan github-webhooks:reprocess 123
```

## Modèle de données

Le package stocke les webhooks dans la table `git_hub_webhooks` avec les champs :

- `id` : ID auto-incrémenté
- `event_type` : Type d'événement GitHub (push, pull_request, etc.)
- `delivery_id` : ID de livraison GitHub
- `payload` : Données JSON du webhook
- `headers` : En-têtes HTTP
- `processed_at` : Timestamp de traitement
- `created_at` / `updated_at` : Timestamps Laravel

### Utilisation du modèle

```php
use Laravel\GitHubWebhooks\Models\GitHubWebhook;

// Récupérer tous les webhooks push non traités
$webhooks = GitHubWebhook::eventType('push')
    ->unprocessed()
    ->get();

// Marquer comme traité
$webhook->update(['processed_at' => now()]);
```

## Sécurité

### Validation de signature

Le package valide automatiquement les signatures GitHub si vous configurez un secret. Cette validation :

- Utilise HMAC-SHA256
- Compare de manière sécurisée avec `hash_equals()`
- Retourne une erreur 401 si la signature est invalide

### Middleware recommandé

Ajoutez des middlewares dans la configuration :

```php
'middleware' => [
    'throttle:60,1', // Limite de débit
    'api',           // Middleware API
],
```

## Événements GitHub supportés

Le package peut traiter tous les événements GitHub, incluant :

- `push` : Commits poussés
- `pull_request` : Pull requests (ouverte, fermée, synchronisée, etc.)
- `issues` : Issues (ouverte, fermée, étiquetée, etc.)
- `release` : Releases
- `deployment` : Déploiements
- `workflow_run` : Exécutions GitHub Actions
- Et bien d'autres...

## Dépannage

### Vérification des logs

Les webhooks sont loggés automatiquement. Vérifiez vos logs Laravel :

```bash
tail -f storage/logs/laravel.log
```

### Test des webhooks

Utilisez ngrok pour tester localement :

```bash
ngrok http 8000
```

Puis configurez l'URL webhook GitHub avec l'URL ngrok.

### Webhook non reçu

1. Vérifiez que l'URL est accessible depuis GitHub
2. Vérifiez la configuration du secret
3. Consultez l'onglet "Recent Deliveries" dans GitHub
4. Vérifiez les logs d'erreur

## Contribution

Ce package est conçu pour être extensible. Vous pouvez :

- Créer des handlers personnalisés
- Étendre le modèle GitHubWebhook
- Ajouter des middlewares personnalisés
- Contribuer avec de nouvelles fonctionnalités

## Licence

MIT
