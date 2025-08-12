<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\GitHubWebhooks\Models\GitHubWebhook;
use Laravel\GitHubWebhooks\GitHubWebhooks;

class WebhookTestController extends Controller
{
    /**
     * Display webhook statistics.
     */
    public function index()
    {
        $stats = [
            'total' => GitHubWebhook::count(),
            'processed' => GitHubWebhook::processed()->count(),
            'unprocessed' => GitHubWebhook::unprocessed()->count(),
            'by_event' => GitHubWebhook::selectRaw('event_type, count(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type'),
            'recent' => GitHubWebhook::orderByDesc('created_at')->limit(10)->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Test the webhook handler manually.
     */
    public function test(Request $request)
    {
        // Exemple de payload de test
        $testPayload = [
            'action' => 'opened',
            'number' => 999,
            'pull_request' => [
                'title' => 'Test PR from controller',
                'user' => ['login' => 'testuser'],
                'number' => 999,
            ],
            'repository' => [
                'full_name' => 'test/repository'
            ]
        ];

        // Tester manuellement un handler
        GitHubWebhooks::on('test_event', function ($event, $payload, $request) {
            return [
                'message' => 'Test handler executed successfully!',
                'event' => $event,
                'payload_keys' => array_keys($payload),
                'timestamp' => now()->toISOString(),
            ];
        });

        $result = GitHubWebhooks::handle('test_event', $testPayload, $request);

        return response()->json([
            'success' => true,
            'result' => $result,
            'test_payload' => $testPayload,
        ]);
    }
}
