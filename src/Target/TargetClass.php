<?php

namespace Etshy\Rector8237\Target;

use OpenApi\Annotations as OA;

class TargetClass
{
    /**
     * @var array|null
     * @OA\Property(type="array", @OA\Items(type="integer"))
     */
    private ?array $property = [];
}