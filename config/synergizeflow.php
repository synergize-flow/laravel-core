<?php

use App\Models\User;

return [
    'website_name' => env('APP_NAME', 'Laravel App'),
    'website_url' => env('APP_URL', 'http://localhost'),
    'website_description' => env('SYNERGIZEFLOW_SITE_DESC', 'A Laravel Application'),
    'website_language' => env('APP_LOCALE', 'en'),

    /**
     * Security key used by SynergizeFlow / n8n to authenticate with this app.
     * Matches the domain license/security token stored in the SynergizeFlow SaaS.
     */
    'security_key' => env('SYNERGIZEFLOW_SECURITY_KEY'),

    /**
     * Content and model providers for SynergizeFlow.
     * Maps host models and fields to standard SynergizeFlow response structures.
     */
    'content_providers' => [
        // 'blog' => [
        //     'name' => 'Blog Posts',
        //     'model' => \App\Models\Post::class,
        //     'fields' => [
        //         'id' => 'id',
        //         'title' => 'title',
        //         'content' => 'content',
        //         'slug' => 'slug',
        //     ],
        //     'limit' => 5,
        // ],
        // 'category' => [
        //     'model' => \App\Models\Category::class,
        //     'fields' => [
        //         'id' => 'id',
        //         'name' => 'name',
        //     ],
        // ],
        'author' => [
            'model' => User::class,
            'fields' => [
                'id' => 'id',
                'name' => 'name',
            ],
        ],
    ],

    /**
     * Action classes for executing tasks triggered by SynergizeFlow.
     */
    'actions' => [
        /**
         * Must implement \SynergizeFlow\Laravel\Contracts\InsertBlogContract
         * Example: \App\Actions\InsertBlogAction::class
         * Or if using the headless blog extension: \SynergizeFlow\Blog\Actions\InsertHeadlessBlogAction::class
         */
        'insert_blog' => null,
    ],
];
