<?php

namespace SynergizeFlow\Laravel\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SynergizeFlow\Laravel\Contracts\InsertBlogContract;

class SynergizeFlowController extends Controller
{
    /**
     * Validate license endpoint mimicking WordPress validate-license API.
     */
    public function validateLicense(Request $request): JsonResponse
    {
        $expectedKey = (string) config('synergizeflow.security_key');
        $license = (string) ($request->input('license') ?? $request->input('key') ?? '');

        $isValid = ! empty($expectedKey) && ! empty($license) && hash_equals($expectedKey, $license);

        return response()->json([
            'is_valid' => $isValid ? 'yes' : 'no',
        ]);
    }

    /**
     * Get website onboarding data and determine Workflow (A vs B).
     */
    public function getWebsiteData(Request $request): JsonResponse
    {
        $providers = config('synergizeflow.content_providers', []);
        $content = [];

        // Check for mapped blog posts
        $blogConfig = $providers['blog'] ?? null;
        if ($blogConfig && ! empty($blogConfig['model']) && class_exists($blogConfig['model'])) {
            $modelClass = $blogConfig['model'];
            $limit = $blogConfig['limit'] ?? 5;
            $titleField = $blogConfig['fields']['title'] ?? 'title';
            $contentField = $blogConfig['fields']['content'] ?? 'content';

            $records = $modelClass::query()->latest()->take($limit)->get();

            $posts = $records->map(function ($item) use ($titleField, $contentField) {
                return [
                    'title' => $item->{$titleField} ?? '',
                    'content' => $item->{$contentField} ?? '',
                ];
            });

            if ($posts->isNotEmpty()) {
                $content[] = [
                    'key' => 'blog',
                    'name' => $blogConfig['name'] ?? 'Blog Posts',
                    'category' => [],
                    'posts' => $posts->all(),
                ];
            }
        }

        $workflow = empty($content) ? 'B' : 'A';

        return response()->json([
            'status' => 'success',
            'workflow' => $workflow,
            'data' => [
                'website' => [
                    'website_url' => config('synergizeflow.website_url'),
                    'website_name' => config('synergizeflow.website_name'),
                    'website_description' => config('synergizeflow.website_description'),
                    'website_language' => config('synergizeflow.website_language'),
                ],
                'content' => $content,
            ],
            'message' => empty($content)
                ? 'No blog mapping found. Please analyze homepage URL.'
                : 'Content loaded successfully.',
        ]);
    }

    /**
     * Helper data endpoint mimicking WordPress helper-data API.
     */
    public function helperData(Request $request): JsonResponse
    {
        $type = (string) $request->input('type');
        $providers = config('synergizeflow.content_providers', []);

        switch ($type) {
            case 'get_category':
                $categoryConfig = $providers['category'] ?? null;
                if ($categoryConfig && ! empty($categoryConfig['model']) && class_exists($categoryConfig['model'])) {
                    $modelClass = $categoryConfig['model'];
                    $idField = $categoryConfig['fields']['id'] ?? 'id';
                    $nameField = $categoryConfig['fields']['name'] ?? 'name';

                    $records = $modelClass::query()->get();
                    $items = $records->map(fn ($item) => [
                        'id' => $item->{$idField},
                        'name' => $item->{$nameField},
                    ])->values()->all();

                    return response()->json(['data' => $items]);
                }

                return response()->json(['data' => []]);

            case 'get_user':
                $authorConfig = $providers['author'] ?? null;
                $authorModel = $authorConfig['model'] ?? User::class;

                if (! empty($authorModel) && class_exists($authorModel)) {
                    $idField = $authorConfig['fields']['id'] ?? 'id';
                    $nameField = $authorConfig['fields']['name'] ?? 'name';

                    $records = $authorModel::query()->get();
                    $items = $records->map(fn ($item) => [
                        'id' => $item->{$idField},
                        'name' => $item->{$nameField},
                    ])->values()->all();

                    if (! empty($items)) {
                        return response()->json(['data' => $items]);
                    }
                }

                // Default fallback if no users found
                return response()->json([
                    'data' => [
                        ['id' => 1, 'name' => 'Default Author'],
                    ],
                ]);

            case 'get_title':
                $blogConfig = $providers['blog'] ?? null;
                if ($blogConfig && ! empty($blogConfig['model']) && class_exists($blogConfig['model'])) {
                    $modelClass = $blogConfig['model'];
                    $idField = $blogConfig['fields']['id'] ?? 'id';
                    $titleField = $blogConfig['fields']['title'] ?? 'title';

                    $records = $modelClass::query()->latest()->take(50)->get();
                    $items = $records->map(fn ($item) => [
                        'id' => $item->{$idField},
                        'title' => $item->{$titleField},
                    ])->values()->all();

                    return response()->json(['data' => $items]);
                }

                return response()->json(['data' => []]);

            case 'get_version':
                return response()->json([
                    'data' => [
                        'current_version' => '1.0.0',
                        'latest_version' => '1.0.0',
                        'need_update' => false,
                    ],
                ]);

            default:
                return response()->json(['data' => []]);
        }
    }

    /**
     * Endpoint to insert AI-generated blog content.
     */
    public function insertBlog(Request $request): JsonResponse
    {
        $actionClass = config('synergizeflow.actions.insert_blog');

        if (empty($actionClass)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No insert_blog action configured in config/synergizeflow.php.',
            ], 422);
        }

        if (! class_exists($actionClass)) {
            return response()->json([
                'status' => 'error',
                'message' => "Configured action [{$actionClass}] does not exist.",
            ], 500);
        }

        if (! is_subclass_of($actionClass, InsertBlogContract::class)) {
            return response()->json([
                'status' => 'error',
                'message' => "Configured action [{$actionClass}] must implement ".InsertBlogContract::class.'.',
            ], 500);
        }

        /** @var InsertBlogContract $action */
        $action = app($actionClass);
        $result = $action->execute($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Blog inserted successfully.',
            'data' => $result,
        ]);
    }
}
