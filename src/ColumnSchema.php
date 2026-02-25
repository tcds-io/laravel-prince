<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Closure;
use Illuminate\Http\Request;
use JsonSerializable;
use Override;

readonly class ColumnSchema implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $type,
        public Closure $parser,
        public mixed $values = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            ...($this->values ? ['values' => $this->values] : []),
        ];
    }

    public function valueOf(Request $request): mixed
    {
        $value = $request->input($this->name);

        return $value ? call_user_func($this->parser, $value) : null;
    }
}
