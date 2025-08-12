<?php

namespace Laravel\GitHubWebhooks\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class UpdateRepositoryCommand extends Command
{
    protected $signature = 'github-webhooks:update-repo
                          {repository : The repository name (e.g., owner/repo)}
                          {--branch=main : The branch to checkout}
                          {--clone-url= : The clone URL if repository needs to be cloned}
                          {--force : Force update even if there are local changes}';

    protected $description = 'Update local repository from GitHub webhook event';

    public function handle(): int
    {
        $repository = $this->argument('repository');
        $branch = $this->option('branch');
        $cloneUrl = $this->option('clone-url');
        $force = $this->option('force');

        $this->info("Updating repository: {$repository}");
        $this->info("Branch: {$branch}");

        $repositoryPath = storage_path("repositories/" . str_replace('/', '_', $repository));

        if (!is_dir($repositoryPath)) {
            return $this->cloneRepository($repository, $repositoryPath, $cloneUrl, $branch);
        } else {
            return $this->updateRepository($repository, $repositoryPath, $branch, $force);
        }
    }

    /**
     * Clone a new repository.
     */
    protected function cloneRepository(string $repository, string $repositoryPath, ?string $cloneUrl, string $branch): int
    {
        if (!$cloneUrl) {
            $this->error("Clone URL is required for new repositories. Use --clone-url option.");
            return self::FAILURE;
        }

        $this->info("Cloning repository...");

        // Create parent directory if it doesn't exist
        File::ensureDirectoryExists(dirname($repositoryPath));

        $command = "git clone --branch {$branch} {$cloneUrl} {$repositoryPath}";
        
        if ($this->option('verbose')) {
            $this->line("Executing: {$command}");
        }

        exec($command . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error("Failed to clone repository");
            $this->line("Output:");
            foreach ($output as $line) {
                $this->line("  " . $line);
            }
            
            Log::error("Failed to clone repository", [
                'repository' => $repository,
                'clone_url' => $cloneUrl,
                'branch' => $branch,
                'output' => $output,
                'return_code' => $returnCode
            ]);

            return self::FAILURE;
        }

        $this->info("✅ Repository cloned successfully to: {$repositoryPath}");
        
        Log::info("Repository cloned successfully", [
            'repository' => $repository,
            'path' => $repositoryPath,
            'branch' => $branch
        ]);

        return self::SUCCESS;
    }

    /**
     * Update an existing repository.
     */
    protected function updateRepository(string $repository, string $repositoryPath, string $branch, bool $force): int
    {
        $this->info("Updating existing repository...");

        $commands = [
            "cd {$repositoryPath}",
            "git fetch origin",
        ];

        // Check if there are local changes
        if (!$force) {
            exec("cd {$repositoryPath} && git status --porcelain", $statusOutput);
            if (!empty($statusOutput)) {
                $this->warn("Repository has local changes:");
                foreach ($statusOutput as $line) {
                    $this->line("  " . $line);
                }
                
                if (!$this->confirm("Do you want to discard local changes and continue?")) {
                    $this->info("Update cancelled.");
                    return self::SUCCESS;
                }
                $force = true;
            }
        }

        if ($force) {
            $commands[] = "git reset --hard origin/{$branch}";
        } else {
            $commands[] = "git merge origin/{$branch}";
        }

        $fullCommand = implode(" && ", $commands);
        
        if ($this->option('verbose')) {
            $this->line("Executing: {$fullCommand}");
        }

        exec($fullCommand . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error("Failed to update repository");
            $this->line("Output:");
            foreach ($output as $line) {
                $this->line("  " . $line);
            }

            Log::error("Failed to update repository", [
                'repository' => $repository,
                'branch' => $branch,
                'path' => $repositoryPath,
                'output' => $output,
                'return_code' => $returnCode
            ]);

            return self::FAILURE;
        }

        $this->info("✅ Repository updated successfully");

        // Show latest commit info
        exec("cd {$repositoryPath} && git log -1 --oneline", $commitOutput);
        if (!empty($commitOutput)) {
            $this->info("Latest commit: " . $commitOutput[0]);
        }

        Log::info("Repository updated successfully", [
            'repository' => $repository,
            'branch' => $branch,
            'path' => $repositoryPath,
            'latest_commit' => $commitOutput[0] ?? 'unknown'
        ]);

        return self::SUCCESS;
    }
}
