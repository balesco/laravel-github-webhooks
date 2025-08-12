<?php

namespace Laravel\GitHubWebhooks\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListRepositoriesCommand extends Command
{
    protected $signature = 'github-webhooks:list-repos
                          {--path= : Custom path to search for repositories}';

    protected $description = 'List all local repositories managed by GitHub webhooks';

    public function handle(): int
    {
        $basePath = $this->option('path') ?: config('github-webhooks.repository_storage_path');

        if (!is_dir($basePath)) {
            $this->info("No repositories directory found at: {$basePath}");
            return self::SUCCESS;
        }

        $repositories = [];
        $directories = File::directories($basePath);

        foreach ($directories as $dir) {
            $repositoryName = basename($dir);
            $gitDir = $dir . '/.git';

            if (is_dir($gitDir)) {
                $repoInfo = $this->getRepositoryInfo($dir);
                $repositories[] = [
                    'name' => str_replace('_', '/', $repositoryName),
                    'path' => $dir,
                    'branch' => $repoInfo['branch'],
                    'last_commit' => $repoInfo['last_commit'],
                    'status' => $repoInfo['status'],
                ];
            }
        }

        if (empty($repositories)) {
            $this->info("No Git repositories found in: {$basePath}");
            return self::SUCCESS;
        }

        $this->info("Found " . count($repositories) . " repository(ies):");
        $this->newLine();

        $headers = ['Repository', 'Current Branch', 'Last Commit', 'Status'];
        $rows = [];

        foreach ($repositories as $repo) {
            $rows[] = [
                $repo['name'],
                $repo['branch'],
                $repo['last_commit'],
                $repo['status'],
            ];
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }

    /**
     * Get information about a repository.
     */
    private function getRepositoryInfo(string $path): array
    {
        $info = [
            'branch' => 'unknown',
            'last_commit' => 'unknown',
            'status' => 'unknown',
        ];

        // Get current branch
        exec("cd {$path} && git branch --show-current 2>/dev/null", $branchOutput, $branchCode);
        if ($branchCode === 0 && !empty($branchOutput)) {
            $info['branch'] = $branchOutput[0];
        }

        // Get last commit
        exec("cd {$path} && git log -1 --oneline 2>/dev/null", $commitOutput, $commitCode);
        if ($commitCode === 0 && !empty($commitOutput)) {
            $info['last_commit'] = $commitOutput[0];
        }

        // Get status
        exec("cd {$path} && git status --porcelain 2>/dev/null", $statusOutput, $statusCode);
        if ($statusCode === 0) {
            if (empty($statusOutput)) {
                $info['status'] = '✅ Clean';
            } else {
                $info['status'] = '⚠️ Modified (' . count($statusOutput) . ' files)';
            }
        }

        return $info;
    }
}
