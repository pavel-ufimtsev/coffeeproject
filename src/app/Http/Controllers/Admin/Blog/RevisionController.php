<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\User;
use App\Services\Blog\BlogActor;
use App\Services\Blog\BlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevisionController extends Controller
{
    /**
     * One revision's full text, for the editor's compare view.
     */
    public function show(BlogPost $post, BlogPostRevision $revision): JsonResponse
    {
        return response()->json([
            ...$revision->only(['id', 'author', 'note', ...BlogPostRevision::FIELDS]),
            'created_at' => $revision->created_at?->toDateTimeString(),
        ]);
    }

    /**
     * Bring back the text of an earlier revision as a new revision.
     */
    public function restore(Request $request, BlogPost $post, BlogPostRevision $revision, BlogPostService $posts): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $posts->restoreRevision($post, $revision, BlogActor::user($user));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Revision restored.')]);

        return back();
    }
}
