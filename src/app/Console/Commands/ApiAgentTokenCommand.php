<?php

namespace App\Console\Commands;

use App\Models\ApiAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

#[Signature('api:agent-token {agent? : Agent name, e.g. blog-editor} {--name= : Token name (default: the agent name)} {--list : List agents and their tokens} {--revoke= : Revoke the token with this id}')]
#[Description('Issue, list or revoke API tokens for AI agents')]
class ApiAgentTokenCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('list')) {
            $this->table(['Agent', 'Active', 'Token id', 'Token name', 'Last used', 'Created'], ApiAgent::with('tokens')->get()->flatMap(
                fn (ApiAgent $agent) => $agent->tokens->isEmpty()
                    ? [[$agent->name, $agent->is_active ? 'yes' : 'no', '-', '-', '-', '-']]
                    : $agent->tokens->map(fn (PersonalAccessToken $t) => [
                        $agent->name, $agent->is_active ? 'yes' : 'no', $t->id, $t->name, $t->last_used_at?->diffForHumans() ?? 'never', $t->created_at?->toDateString(),
                    ])->all()
            ));

            return self::SUCCESS;
        }

        if ($id = $this->option('revoke')) {
            $deleted = PersonalAccessToken::whereKey($id)->where('tokenable_type', ApiAgent::class)->delete();
            $deleted ? $this->components->info("Token {$id} revoked.") : $this->components->error("No agent token with id {$id}.");

            return $deleted ? self::SUCCESS : self::FAILURE;
        }

        $name = (string) $this->argument('agent');
        if ($name === '') {
            $this->components->error('Give an agent name, e.g. php artisan api:agent-token blog-editor');

            return self::FAILURE;
        }

        $agent = ApiAgent::firstOrCreate(['name' => $name], ['description' => 'Created from the command line']);
        $token = $agent->createToken($this->option('name') ?: $name, [ApiAgent::BLOG_ABILITY]);

        $this->components->info("Token for {$agent->name}. It is shown only once; store it as a secret.");
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
