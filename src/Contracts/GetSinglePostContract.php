<?php

namespace SynergizeFlow\Laravel\Contracts;

interface GetSinglePostContract
{
    /**
     * Handle fetching a single post by ID.
     *
     * @param mixed $id
     * @return array<string, mixed>
     */
    public function execute(mixed $id): array;
}
