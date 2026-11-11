<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * A region with its own tax table: a US state, a Canadian province or territory, Scotland or Wales, a Spanish
 * autonomous community, a Swiss canton, and so on.
 */
final class Region
{
    public function __construct(
        /** Code used by the API's `region=` parameter, e.g. `ca`, `on`, `scotland`. */
        public readonly string $code,
        public readonly string $name,
        /** URL slug of the region's page on calcbloc.com, e.g. `california`. */
        public readonly string $slug,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $code = (string) ($data['code'] ?? '');

        return new self($code, (string) ($data['name'] ?? $code), (string) ($data['slug'] ?? $code));
    }
}
