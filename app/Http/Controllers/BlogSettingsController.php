<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BlogSettingsController extends Controller
{
    private string $connection = 'mysql-sklep';

    public function show()
    {
        $blogPosts = $this->getBlogPosts();
        $categoryOptions = $this->getCategoryOptions();
        $assignments = $this->getAssignments($blogPosts, $categoryOptions);

        return view('settings.blog', [
            'assignments' => $assignments,
            'blogPosts' => $blogPosts,
            'categoryOptions' => $categoryOptions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'blog_post_id' => 'required|integer|exists:mysql-sklep.blog_posts,id',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:mysql-sklep.categories,id',
        ]);

        $categoryIds = collect($validated['category_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $rows = $categoryIds
            ->map(fn ($categoryId) => [
                'blog_post_id' => (int) $validated['blog_post_id'],
                'category_id' => $categoryId,
            ])
            ->all();

        DB::connection($this->connection)
            ->table('blog_product_categories')
            ->insertOrIgnore($rows);

        return redirect()
            ->route('settings.blog.show')
            ->with('success', 'Powiązania bloga z kategoriami zostały dodane.');
    }

    public function update(Request $request, int $blogPostId)
    {
        $this->abortIfBlogPostMissing($blogPostId);

        $validated = $request->validate([
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:mysql-sklep.categories,id',
        ]);

        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::connection($this->connection)->transaction(function () use ($blogPostId, $categoryIds) {
            DB::connection($this->connection)
                ->table('blog_product_categories')
                ->where('blog_post_id', $blogPostId)
                ->delete();

            if ($categoryIds->isEmpty()) {
                return;
            }

            DB::connection($this->connection)
                ->table('blog_product_categories')
                ->insert($categoryIds->map(fn ($categoryId) => [
                    'blog_post_id' => $blogPostId,
                    'category_id' => $categoryId,
                ])->all());
        });

        return redirect()
            ->route('settings.blog.show')
            ->with('success', 'Powiązania wpisu blogowego zostały zaktualizowane.');
    }

    public function destroy(int $blogPostId)
    {
        $this->abortIfBlogPostMissing($blogPostId);

        $deleted = DB::connection($this->connection)
            ->table('blog_product_categories')
            ->where('blog_post_id', $blogPostId)
            ->delete();

        if ($deleted === 0) {
            return redirect()
                ->route('settings.blog.show')
                ->with('warning', 'Ten wpis blogowy nie miał zapisanych powiązań.');
        }

        return redirect()
            ->route('settings.blog.show')
            ->with('success', 'Usunięto wszystkie powiązania wpisu blogowego.');
    }

    public function destroyCategory(int $blogPostId, int $categoryId)
    {
        $deleted = DB::connection($this->connection)
            ->table('blog_product_categories')
            ->where('blog_post_id', $blogPostId)
            ->where('category_id', $categoryId)
            ->delete();

        if ($deleted === 0) {
            return redirect()
                ->route('settings.blog.show')
                ->with('warning', 'Nie znaleziono wskazanego powiązania.');
        }

        return redirect()
            ->route('settings.blog.show')
            ->with('success', 'Usunięto kategorię z powiązań wpisu blogowego.');
    }

    private function getBlogPosts(): Collection
    {
        return DB::connection($this->connection)
            ->table('blog_posts')
            ->leftJoin('blog_post_translations', function ($join) {
                $join->on('blog_posts.id', '=', 'blog_post_translations.blog_post_id')
                    ->where('blog_post_translations.locale', 'pl');
            })
            ->select(
                'blog_posts.id',
                'blog_posts.slug',
                'blog_posts.publish_status',
                'blog_post_translations.title'
            )
            ->orderBy('blog_post_translations.title')
            ->orderBy('blog_posts.id')
            ->get()
            ->map(fn ($post) => [
                'id' => (int) $post->id,
                'title' => $post->title ?: ($post->slug ?: 'Wpis #' . $post->id),
                'slug' => $post->slug,
                'publish_status' => $post->publish_status,
            ]);
    }

    private function getCategoryOptions(): Collection
    {
        $categories = DB::connection($this->connection)
            ->table('categories')
            ->leftJoin('category_translations', function ($join) {
                $join->on('categories.id', '=', 'category_translations.category_id')
                    ->where('category_translations.locale', 'pl');
            })
            ->select(
                'categories.id',
                'categories.parent_id',
                'categories.slug',
                'categories.position',
                'category_translations.name'
            )
            ->get()
            ->map(function ($category) {
                $category->id = (int) $category->id;
                $category->parent_id = $category->parent_id === null ? null : (int) $category->parent_id;
                $category->display_name = $category->name ?: ($category->slug ?: 'Kategoria #' . $category->id);

                return $category;
            });

        $categoriesById = $categories->keyBy('id');

        return $categories
            ->map(function ($category) use ($categoriesById) {
                [$path, $depth] = $this->buildCategoryPath($category, $categoriesById);

                return [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->display_name,
                    'path' => $path,
                    'depth' => $depth,
                ];
            })
            ->sortBy('path', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function buildCategoryPath(object $category, Collection $categoriesById): array
    {
        $parts = [];
        $current = $category;
        $depth = 0;
        $guard = 0;

        while ($current && $guard < 50) {
            array_unshift($parts, $current->display_name);

            if ($current->parent_id === null) {
                break;
            }

            $current = $categoriesById->get($current->parent_id);
            if ($current) {
                $depth++;
            }

            $guard++;
        }

        return [implode(' > ', $parts), $depth];
    }

    private function getAssignments(Collection $blogPosts, Collection $categoryOptions): Collection
    {
        $postsById = $blogPosts->keyBy('id');
        $categoriesById = $categoryOptions->keyBy('id');

        return DB::connection($this->connection)
            ->table('blog_product_categories')
            ->orderBy('blog_post_id')
            ->orderBy('category_id')
            ->get()
            ->groupBy('blog_post_id')
            ->map(function ($relations, $blogPostId) use ($postsById, $categoriesById) {
                $categories = $relations
                    ->map(fn ($relation) => $categoriesById->get((int) $relation->category_id, [
                        'id' => (int) $relation->category_id,
                        'name' => 'Kategoria #' . $relation->category_id,
                        'path' => 'Kategoria #' . $relation->category_id,
                        'depth' => 0,
                    ]))
                    ->sortBy('path', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                return [
                    'blog_post_id' => (int) $blogPostId,
                    'post' => $postsById->get((int) $blogPostId, [
                        'id' => (int) $blogPostId,
                        'title' => 'Wpis #' . $blogPostId,
                        'slug' => null,
                        'publish_status' => null,
                    ]),
                    'categories' => $categories,
                    'category_ids' => $categories->pluck('id')->all(),
                ];
            })
            ->sortBy(fn ($assignment) => $assignment['post']['title'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function abortIfBlogPostMissing(int $blogPostId): void
    {
        $exists = DB::connection($this->connection)
            ->table('blog_posts')
            ->where('id', $blogPostId)
            ->exists();

        abort_unless($exists, 404);
    }
}
