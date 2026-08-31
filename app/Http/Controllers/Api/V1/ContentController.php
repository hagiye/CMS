<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\ContentNodeResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\LinkResource;
use App\Http\Resources\SearchResultResource;
use App\Models\Bookmark;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Support\LocalePreference;
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
            'type' => ['sometimes', 'string', 'in:edition,section,chapter,article,page'],
            'locale' => self::LOCALE_RULE,
            'include' => ['sometimes', 'string', 'in:children'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = ContentNode::published()
            ->when($validated['type'] ?? null, fn ($builder, $type) => $builder->where('type', $type))
            ->with('translations')
            ->orderBy('parent_id')
            ->orderBy('position')
            ->orderBy('id');

        if (($validated['include'] ?? null) === 'children') {
            $query->with([
                'publicChildren',
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
        $node = ContentNode::published()->with([
            'translations',
            'publicChildren',
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
        $locale = LocalePreference::normalize($validated['locale'] ?? config('app.locale'));
        $fallbackLocales = LocalePreference::fallbacks($locale);

        $nodes = ContentTranslation::search($term)
            ->whereIn('locale', $fallbackLocales)
            ->query(fn ($builder) => $builder
                ->preferredForLocales($fallbackLocales)
                ->whereHas('node', fn ($nodes) => $nodes->published())
                ->with(['node.translations', 'node.publicParent']))
            ->paginate($validated['per_page'] ?? 25);

        $nodes->setCollection(
            $nodes->getCollection()
                ->map(function (ContentTranslation $translation): ?ContentNode {
                    $node = $translation->node;
                    $node?->setRelation('searchTranslation', $translation);

                    return $node;
                })
                ->filter()
                ->values(),
        );
        $nodes->withQueryString();

        return SearchResultResource::collection($nodes);
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
        $bookmark->load('node.translations');

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
        $bookmarks = $request->user()
            ->bookmarks()
            ->whereHas('node', fn ($builder) => $builder->published())
            ->with([
                'node' => fn ($builder) => $builder
                    ->published()
                    ->with('translations'),
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
