<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class ApiKeyCreate extends Command
{
    protected $signature = 'apikey:create
                            {name : The name for the API key}
                            {--rate-limit= : Optional rate limit (requests per minute)}';

    protected $description = 'Create a new API key';

    public function handle(): int
    {
        $name = $this->argument('name');
        $rateLimit = $this->option('rate-limit');

        $apiKey = ApiKey::generate($name, $rateLimit ? (int) $rateLimit : null);

        $this->info('API key created successfully!');
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $apiKey->id],
                ['Name', $apiKey->name],
                ['Key', $apiKey->key],
                ['Rate Limit', $apiKey->rate_limit ?? 'None'],
                ['Active', $apiKey->is_active ? 'Yes' : 'No'],
            ]
        );
        $this->newLine();
        $this->info('You can view this key anytime in the admin panel or by running apikey:list.');

        return Command::SUCCESS;
    }
}
