<?php

namespace SynergizeFlow\Laravel\Contracts;

interface InsertBlogContract
{
    /**
     * Handle the insertion of AI-generated blog content.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): mixed;
}
