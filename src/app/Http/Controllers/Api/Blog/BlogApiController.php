<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Models\ApiAgent;
use App\Models\BlogPost;
use App\Services\Blog\BlogActor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Shared helpers for the blog API: who is calling and finding posts by id or slug.
 */
abstract class BlogApiController extends Controller
{
    /** An internal address for redirects: starts with a slash, no host. */
    public const PATH_RULE = 'regex:#^/(?!/)[^\s]*$#';

    protected function actor(Request $request): BlogActor
    {
        /** @var ApiAgent $agent */
        $agent = $request->user();

        return BlogActor::agent($agent, $agent->currentAccessToken()->name);
    }

    protected function findPost(string $post, bool $withTrashed = false): BlogPost
    {
        $query = BlogPost::query()->when($withTrashed, fn ($q) => $q->withTrashed());

        return (ctype_digit($post) ? $query->whereKey((int) $post) : $query->where('slug', $post))->firstOrFail();
    }

    /**
     * Run a write and turn rule violations from the services into 422 answers.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    protected function attempt(callable $write, string $field = 'request'): mixed
    {
        try {
            return $write();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
