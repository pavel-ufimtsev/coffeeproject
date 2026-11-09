<?php

namespace App\Services\Blog;

use App\Models\ApiAgent;
use App\Models\User;

/**
 * Who changed the blog: a person in the admin or an agent with a token.
 * Recorded on revisions, images and in the log.
 */
final readonly class BlogActor
{
    public function __construct(public string $name, public ?int $userId = null, public bool $isAgent = false) {}

    public static function user(User $user): self
    {
        return new self($user->name, $user->id);
    }

    public static function agent(ApiAgent $agent, ?string $tokenName = null): self
    {
        return new self('agent: '.$agent->name.($tokenName && $tokenName !== $agent->name ? " ({$tokenName})" : ''), isAgent: true);
    }
}
