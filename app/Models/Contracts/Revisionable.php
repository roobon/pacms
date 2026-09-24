<?php

namespace App\Models\Contracts;

/**
 * An item whose full state can be snapshotted into a revision and restored from one.
 */
interface Revisionable
{
    /**
     * Full working-copy state in the schema node format.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array;

    /**
     * Write a snapshot back into the working copy (does not publish).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function applySnapshot(array $snapshot): void;

    /**
     * Content-type key used for permissions and routes (e.g. "pages").
     */
    public function contentType(): string;
}
