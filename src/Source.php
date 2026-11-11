<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * The official document a figure was taken from: tax authority guidance, legislation or a published table.
 *
 * @phpstan-type SourceArray array{title: string, url: string, retrievedAt: string, effectiveFrom?: string|null, effectiveTo?: string|null}
 */
final class Source
{
    public function __construct(
        /** Title of the official document or page. */
        public readonly string $title,
        /** Link to the official document or page. */
        public readonly string $url,
        /** Date (YYYY-MM-DD) the figure was last checked against the source. */
        public readonly string $retrievedAt,
        /** First day the figure applies, when it differs from the table's own dates. */
        public readonly ?string $effectiveFrom = null,
        /** Last day the figure applies, when it differs from the table's own dates. */
        public readonly ?string $effectiveTo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['title'] ?? ''),
            (string) ($data['url'] ?? ''),
            (string) ($data['retrievedAt'] ?? ''),
            isset($data['effectiveFrom']) ? (string) $data['effectiveFrom'] : null,
            isset($data['effectiveTo']) ? (string) $data['effectiveTo'] : null,
        );
    }
}
