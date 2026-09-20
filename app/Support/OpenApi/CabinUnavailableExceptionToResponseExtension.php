<?php

declare(strict_types=1);

namespace App\Support\OpenApi;

use App\Exceptions\CabinUnavailableException;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class CabinUnavailableExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(CabinUnavailableException::class);
    }

    public function toResponse(Type $type): Response
    {
        $cabin = (new OpenApiTypes\ObjectType)
            ->addProperty('id', new OpenApiTypes\IntegerType)
            ->addProperty('code', new OpenApiTypes\StringType)
            ->addProperty('label', new OpenApiTypes\StringType)
            ->setRequired(['id', 'code', 'label']);

        $heldBy = (new OpenApiTypes\ObjectType)
            ->addProperty('kind', new OpenApiTypes\StringType)
            ->addProperty('holder_type', new OpenApiTypes\StringType)
            ->addProperty('reference', (new OpenApiTypes\StringType)->nullable(true))
            ->setRequired(['kind', 'holder_type', 'reference']);

        $item = (new OpenApiTypes\ObjectType)
            ->addProperty('cabin', $cabin)
            ->addProperty('held_by', $heldBy)
            ->setRequired(['cabin', 'held_by']);

        $body = (new OpenApiTypes\ObjectType)
            ->addProperty('message', new OpenApiTypes\StringType)
            ->addProperty('unavailable', (new OpenApiTypes\ArrayType)->setItems($item))
            ->setRequired(['message', 'unavailable']);

        return Response::make(409)
            ->setDescription('Cabin unavailable')
            ->setContent('application/json', Schema::fromType($body));
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', CabinUnavailableException::class, $this->components);
    }
}
