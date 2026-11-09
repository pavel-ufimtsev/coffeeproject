<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Resources\Blog\PostResource;
use App\Models\BlogPostRevision;
use App\Services\Blog\BlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevisionController extends BlogApiController
{
    /**
     * Revisions of a post, newest first, without their full text.
     */
    public function index(string $post): JsonResponse
    {
        $revisions = $this->findPost($post, withTrashed: true)->revisions()
            ->get(['id', 'author', 'note', 'title', 'created_at'])
            ->map(fn (BlogPostRevision $r) => [
                'id' => $r->id,
                'author' => $r->author,
                'note' => $r->note,
                'title' => $r->title,
                'created_at' => $r->created_at?->toAtomString(),
            ]);

        return response()->json(['data' => $revisions]);
    }

    public function show(string $post, int $revision): JsonResponse
    {
        $model = $this->findPost($post, withTrashed: true)->revisions()->whereKey($revision)->firstOrFail();

        return response()->json(['data' => [
            'id' => $model->id,
            'author' => $model->author,
            'note' => $model->note,
            'created_at' => $model->created_at?->toAtomString(),
            ...$model->only(BlogPostRevision::FIELDS),
        ]]);
    }

    public function restore(Request $request, BlogPostService $posts, string $post, int $revision): PostResource
    {
        $model = $this->findPost($post);
        $restored = $posts->restoreRevision($model, $model->revisions()->whereKey($revision)->firstOrFail(), $this->actor($request));
        $restored->load(['category', 'tags', 'cover', 'images', 'products']);

        return PostResource::make($restored)->full();
    }
}
