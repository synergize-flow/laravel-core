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
     * Validate license endpoint (Domain Ownership & Handshake Verification).
     *
     * Note for Developers:
     * This endpoint is invoked by the SynergizeFlow SaaS platform to perform an initial
     * domain ownership handshake. It ensures that the SaaS is communicating with the correct
     * intended domain and that the site administrator has administrative control over this server.
     *
     * Overriding this method to return 'yes' or logging incoming payloads offers no loophole:
     * 1. Domain Ownership: Only an administrator with server access can configure or inspect this app.
     * 2. Operational Authentication: All functional endpoints (/get-website-data, /helper-data,
     *    and /insert-blog) are strictly guarded by VerifySynergizeFlowToken middleware using the
     *    configured security_key.
     * 3. Compute Isolation: AI agent execution and billing run entirely on the SaaS backend,
     *    so a mismatched or hardcoded local response will simply result in 401 errors for actual tasks.
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

        // Check for mapped pages
        $pageConfig = $providers['page'] ?? null;
        if ($pageConfig && ! empty($pageConfig['model']) && class_exists($pageConfig['model'])) {
            $modelClass = $pageConfig['model'];
            $limit = $pageConfig['limit'] ?? 10;
            $titleField = $pageConfig['fields']['title'] ?? 'title';
            $contentField = $pageConfig['fields']['content'] ?? 'content';

            $records = $modelClass::query()->latest()->take($limit)->get();

            $pages = $records->map(function ($item) use ($titleField, $contentField) {
                return [
                    'title' => $item->{$titleField} ?? '',
                    'content' => $item->{$contentField} ?? '',
                ];
            });

            if ($pages->isNotEmpty()) {
                $content[] = [
                    'key' => 'page',
                    'name' => $pageConfig['name'] ?? 'Pages Content',
                    'posts' => $pages->all(),
                ];
            }
        }

        $scanData = $this->resolveScanUrls();
        $scanUrls = $scanData['urls'];
        $scanPages = $scanData['pages'];

        $workflow = empty($content) ? 'B' : 'A';

        $message = 'Content loaded successfully.';
        if (empty($content)) {
            $urlCount = count($scanUrls);
            $message = $urlCount > 1
                ? "No blog mapping found. {$urlCount} scan URLs provided for analysis."
                : 'No blog mapping found. Please analyze homepage URL.';
        }

        return response()->json([
            'status' => 'success',
            'workflow' => $workflow,
            'data' => [
                'website' => [
                    'website_url' => config('synergizeflow.website_url'),
                    'website_name' => config('synergizeflow.website_name'),
                    'website_description' => config('synergizeflow.website_description'),
                    'website_language' => config('synergizeflow.website_language'),
                    'scan_urls' => $scanUrls,
                    'scan_pages' => $scanPages,
                ],
                'scan_urls' => $scanUrls,
                'content' => $content,
            ],
            'message' => $message,
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
                        'current_version' => '1.0.5',
                        'latest_version' => '1.0.5',
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

    /**
     * Resolve and normalize the list of URLs to scan during onboarding.
     *
     * @return array{urls: array<string>, pages: array<array{url: string, title: string}>}
     */
    protected function resolveScanUrls(): array
    {
        $baseUrl = (string) config('synergizeflow.website_url', config('app.url', 'http://localhost'));
        $rawUrls = config('synergizeflow.scan_urls', []);

        if (! is_array($rawUrls) || empty($rawUrls)) {
            $normalizedUrl = rtrim($baseUrl, '/');

            return [
                'urls' => [$normalizedUrl],
                'pages' => [
                    [
                        'url' => $normalizedUrl,
                        'title' => (string) config('synergizeflow.website_name', 'Homepage'),
                    ],
                ],
            ];
        }

        $urls = [];
        $pages = [];

        foreach ($rawUrls as $key => $value) {
            $url = '';
            $title = '';

            if (is_array($value)) {
                $url = (string) ($value['url'] ?? $value['path'] ?? '');
                $title = (string) ($value['title'] ?? $value['label'] ?? $value['name'] ?? '');
            } elseif (is_string($key)) {
                $url = $key;
                $title = (string) $value;
            } else {
                $url = (string) $value;
            }

            $url = trim($url);
            if ($url === '') {
                continue;
            }

            // Prefix relative paths with website_url
            if (! preg_match('#^https?://#i', $url)) {
                $trimmedPath = ltrim($url, '/');
                $url = $trimmedPath === '' ? rtrim($baseUrl, '/') : rtrim($baseUrl, '/').'/'.$trimmedPath;
            }

            if (empty($title)) {
                $path = parse_url($url, PHP_URL_PATH);
                $title = empty($path) || $path === '/' ? 'Homepage' : ucwords(trim(str_replace(['-', '_', '/'], ' ', $path)));
            }

            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
                $pages[] = [
                    'url' => $url,
                    'title' => $title,
                ];
            }
        }

        if (empty($urls)) {
            $normalizedUrl = rtrim($baseUrl, '/');
            $urls = [$normalizedUrl];
            $pages = [
                [
                    'url' => $normalizedUrl,
                    'title' => (string) config('synergizeflow.website_name', 'Homepage'),
                ],
            ];
        }

        return [
            'urls' => $urls,
            'pages' => $pages,
        ];
    }
}
