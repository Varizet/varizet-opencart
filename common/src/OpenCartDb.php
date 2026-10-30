<?php

declare(strict_types=1);

namespace Varizet\OpenCart;

/** Wraps OpenCart's registry `db`. */
final class OpenCartDb implements Db
{
    public function __construct(private readonly object $db)
    {
    }

    public function rows(string $sql): array
    {
        $result = $this->db->query($sql);
        return is_object($result) && isset($result->rows) && is_array($result->rows) ? array_values($result->rows) : [];
    }

    public function execute(string $sql): void
    {
        $this->db->query($sql);
    }

    public function escape(string $value): string
    {
        return (string) $this->db->escape($value);
    }
}
