<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Territory;

final readonly class ComunaResolution
{
    public ResolutionStatus $status;

    public ?Comuna $match;

    /**
     * @param  list<string>  $transformations
     * @param  list<Comuna>  $candidates
     */
    public function __construct(
        public string $original,
        public ?MatchMethod $method,
        public array $transformations,
        public array $candidates,
    ) {
        $this->status = match (count($candidates)) {
            0 => ResolutionStatus::NotFound,
            1 => ResolutionStatus::Matched,
            default => ResolutionStatus::Ambiguous,
        };
        $this->match = $this->status === ResolutionStatus::Matched ? $candidates[0] : null;
    }
}
