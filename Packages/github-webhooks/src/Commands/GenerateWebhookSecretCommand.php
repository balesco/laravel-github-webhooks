<?php

namespace Laravel\GitHubWebhooks\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class GenerateWebhookSecretCommand extends Command
{
    protected $signature = 'github-webhooks:generate-secret
                          {--length=32 : Length of the secret (minimum 16)}
                          {--show : Display the secret instead of updating .env}
                          {--force : Overwrite existing secret in .env}';

    protected $description = 'Generate a secure webhook secret for GitHub webhook verification';

    public function handle(): int
    {
        $length = max(16, (int) $this->option('length'));
        $show = $this->option('show');
        $force = $this->option('force');

        // Generate a cryptographically secure random string
        $secret = $this->generateSecureSecret($length);

        if ($show) {
            $this->displaySecret($secret);
            return self::SUCCESS;
        }

        return $this->updateEnvFile($secret, $force);
    }

    /**
     * Generate a cryptographically secure secret.
     */
    private function generateSecureSecret(int $length): string
    {
        // Use multiple entropy sources for maximum security
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}|;:,.<>?';
        
        // Generate random bytes and convert to base64, then mix with character set
        $randomBytes = random_bytes($length);
        $base64Secret = base64_encode($randomBytes);
        
        // Create a more complex secret by mixing different methods
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            if ($i < strlen($base64Secret)) {
                $secret .= $base64Secret[$i];
            } else {
                $secret .= $characters[random_int(0, strlen($characters) - 1)];
            }
        }

        // Ensure the secret meets complexity requirements
        return $this->ensureComplexity($secret, $length);
    }

    /**
     * Ensure the secret has good complexity.
     */
    private function ensureComplexity(string $secret, int $length): string
    {
        // Ensure we have at least one uppercase, lowercase, number, and special char
        $hasUpper = preg_match('/[A-Z]/', $secret);
        $hasLower = preg_match('/[a-z]/', $secret);
        $hasNumber = preg_match('/[0-9]/', $secret);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $secret);

        $secretArray = str_split($secret);

        if (!$hasUpper) {
            $secretArray[random_int(0, $length - 1)] = chr(random_int(65, 90)); // A-Z
        }
        if (!$hasLower) {
            $secretArray[random_int(0, $length - 1)] = chr(random_int(97, 122)); // a-z
        }
        if (!$hasNumber) {
            $secretArray[random_int(0, $length - 1)] = (string) random_int(0, 9);
        }
        if (!$hasSpecial) {
            $specials = '!@#$%^&*()-_=+';
            $secretArray[random_int(0, $length - 1)] = $specials[random_int(0, strlen($specials) - 1)];
        }

        return implode('', $secretArray);
    }

    /**
     * Display the secret with instructions.
     */
    private function displaySecret(string $secret): void
    {
        $this->info('Generated webhook secret:');
        $this->newLine();
        
        $this->line('<fg=green>' . $secret . '</>');
        
        $this->newLine();
        $this->info('To use this secret:');
        $this->line('1. Add it to your .env file:');
        $this->line('   <fg=yellow>GITHUB_WEBHOOK_SECRET=' . $secret . '</>');
        $this->newLine();
        $this->line('2. Configure it in your GitHub webhook settings:');
        $this->line('   - Go to your repository settings');
        $this->line('   - Navigate to Webhooks');
        $this->line('   - Edit your webhook');
        $this->line('   - Paste the secret in the "Secret" field');
        $this->newLine();
        
        $this->warn('⚠️  Keep this secret secure and do not share it publicly!');
    }

    /**
     * Update the .env file with the new secret.
     */
    private function updateEnvFile(string $secret, bool $force): int
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('.env file not found. Please create one first.');
            return self::FAILURE;
        }

        $envContent = File::get($envPath);
        $secretKey = 'GITHUB_WEBHOOK_SECRET';

        // Check if the key already exists
        if (preg_match("/^{$secretKey}=/m", $envContent)) {
            if (!$force) {
                $this->warn("GITHUB_WEBHOOK_SECRET already exists in .env file.");
                
                if ($this->confirm('Do you want to overwrite the existing secret?')) {
                    $force = true;
                } else {
                    $this->info('Operation cancelled. Use --force to overwrite without confirmation.');
                    return self::SUCCESS;
                }
            }

            // Replace existing value
            $pattern = "/^{$secretKey}=.*$/m";
            $replacement = "{$secretKey}={$secret}";
            $newContent = preg_replace($pattern, $replacement, $envContent);
            
        } else {
            // Add new key
            $newContent = $envContent . "\n# GitHub Webhook Secret\n{$secretKey}={$secret}\n";
        }

        if (File::put($envPath, $newContent)) {
            $this->info('✅ Webhook secret has been ' . (preg_match("/^{$secretKey}=/m", $envContent) ? 'updated' : 'added') . ' to .env file.');
            $this->newLine();
            
            $this->info('Next steps:');
            $this->line('1. Configure this secret in your GitHub webhook settings');
            $this->line('2. Restart your application to load the new environment variable');
            $this->newLine();
            
            $this->warn('⚠️  Remember to update your GitHub webhook configuration with this secret!');
            
            return self::SUCCESS;
        } else {
            $this->error('Failed to update .env file. Check file permissions.');
            return self::FAILURE;
        }
    }
}
