<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\ContentNodeResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\LinkResource;
use App\Models\Bookmark;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    private const LOCALE_RULE = ['sometimes', 'string', 'max:5', 'regex:/^[A-Za-z]{2}(?:-[A-Za-z]{2})?$/'];

    /**
     * GET /api/v1/nodes?type=section&locale=en&include=children
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:section,chapter,article,page'],
            'locale' => self::LOCALE_RULE,
            'include' => ['sometimes', 'string', 'in:children'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $locale = $validated['locale'] ?? 'en';
        $query = ContentNode::published()
            ->when($validated['type'] ?? null, fn ($builder, $type) => $builder->where('type', $type))
            ->with(['translations' => fn ($builder) => $builder->where('locale', $locale)])
            ->orderBy('position');

        if (($validated['include'] ?? null) === 'children') {
            $query->with([
                'children' => fn ($builder) => $builder
                    ->published()
                    ->with(['translations' => fn ($translations) => $translations->where('locale', $locale)]),
            ]);
        }

        $nodes = $query->paginate($validated['per_page'] ?? 25)->withQueryString();

        return ContentNodeResource::collection($nodes);
    }

    /**
     * GET /api/v1/nodes/{slug}?locale=en
     */
    public function show(string $slug, Request $request)
    {
        $validated = $request->validate([
            'locale' => self::LOCALE_RULE,
        ]);
        $locale = $validated['locale'] ?? 'en';

        $node = ContentNode::published()->with([
            'translations' => fn ($builder) => $builder->where('locale', $locale),
            'children' => fn ($builder) => $builder
                ->published()
                ->with(['translations' => fn ($translations) => $translations->where('locale', $locale)]),
        ])->where('slug', $slug)->firstOrFail();

        return new ContentNodeResource($node);
    }

    /**
     * GET /api/v1/search?q=assembly&locale=en&page=1
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'locale' => self::LOCALE_RULE,
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $term = trim($validated['q']);
        $locale = $validated['locale'] ?? 'en';

        $ids = ContentTranslation::query()
            ->where('locale', $locale)
            ->whereHas('node', fn ($builder) => $builder->published())
            ->where(function ($query) use ($term) {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('body', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->pluck('content_node_id')
            ->unique()
            ->values()
            ->all();

        $query = ContentNode::published()
            ->whereIn('id', $ids)
            ->with(['translations' => fn ($builder) => $builder->where('locale', $locale)]);

        if ($ids !== []) {
            $whenClauses = [];
            $bindings = [];

            foreach ($ids as $position => $id) {
                $whenClauses[] = 'WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $position;
            }

            $bindings[] = count($ids);
            $query->orderByRaw(
                'CASE content_nodes.id '.implode(' ', $whenClauses).' ELSE ? END',
                $bindings,
            );
        }

        $nodes = $query->paginate($validated['per_page'] ?? 25)->withQueryString();

        return ContentNodeResource::collection($nodes);
    }

    /**
     * GET /api/v1/nodes/{slug}/links
     */
    public function links(string $slug)
    {
        $node = ContentNode::published()->where('slug', $slug)->firstOrFail();

        return LinkResource::collection($node->links()->orderBy('id')->get());
    }

    /**
     * GET /api/v1/nodes/{slug}/documents
     */
    public function documents(string $slug)
    {
        $node = ContentNode::published()->where('slug', $slug)->firstOrFail();

        return DocumentResource::collection($node->documents()->orderBy('id')->get());
    }

    /**
     * POST /api/v1/bookmarks
     */
    public function bookmark(Request $request)
    {
        $validated = $request->validate([
            'content_node_id' => ['required', 'integer'],
            'locale' => self::LOCALE_RULE,
        ]);

        $node = ContentNode::published()->findOrFail($validated['content_node_id']);

        $bookmark = Bookmark::firstOrCreate([
            'user_id' => $request->user()->id,
            'content_node_id' => $node->id,
        ]);

        $status = $bookmark->wasRecentlyCreated ? 201 : 200;
        $message = $bookmark->wasRecentlyCreated ? 'Bookmark created.' : 'Bookmark already exists.';
        $locale = $validated['locale'] ?? 'en';
        $bookmark->load(['node.translations' => fn ($builder) => $builder->where('locale', $locale)]);

        return (new BookmarkResource($bookmark))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($status);
    }

    /**
     * GET /api/v1/bookmarks
     */
    public function myBookmarks(Request $request)
    {
        $validated = $request->validate([
            'locale' => self::LOCALE_RULE,
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $locale = $validated['locale'] ?? 'en';

        $bookmarks = $request->user()
            ->bookmarks()
            ->whereHas('node', fn ($builder) => $builder->published())
            ->with([
                'node' => fn ($builder) => $builder
                    ->published()
                    ->with(['translations' => fn ($translations) => $translations->where('locale', $locale)]),
            ])
            ->latest('id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return BookmarkResource::collection($bookmarks);
    }

    /**
     * DELETE /api/v1/bookmarks/{contentNode}
     */
    public function destroyBookmark(int $contentNode, Request $request)
    {
        $bookmark = $request->user()
            ->bookmarks()
            ->where('content_node_id', $contentNode)
            ->whereHas('node', fn ($builder) => $builder->published())
            ->firstOrFail();

        $bookmark->delete();

        return response()->noContent();
    }
}
