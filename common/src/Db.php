<?php

declare(strict_types=1);

namespace Varizet\OpenCart;

/**
 * The part of OpenCart's `DB` object the module uses (identical in 3.x and 4.x):
 * `query(string): object{row: array, rows: array, num_rows: int}|bool` and `escape(string)`.
 */
interface Db
{
    /** @return list<array<string, mixed>> */
    public function rows(string $sql): array;

    public function execute(string $sql): void;

    public function escape(string $value): string;
}
