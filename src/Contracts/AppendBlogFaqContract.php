<?php

namespace SynergizeFlow\Laravel\Contracts;

interface AppendBlogFaqContract
{
    /**
     * Handle appending FAQ content and schema to an existing blog.
     *
     * @param  array<string, mixed>  $payload
     * @return mixed
     */
    public function execute(array $payload): mixed;
}
