<?php

declare(strict_types=1);

namespace App\Support\OpenApi;

use App\Exceptions\EmailConflictException;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

final class EmailConflictExceptionToResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(EmailConflictException::class);
    }

    public function toResponse(Type $type): Response
    {
        $contact = (new OpenApiTypes\ObjectType)
            ->addProperty('id', new OpenApiTypes\IntegerType)
            ->addProperty('name', new OpenApiTypes\StringType)
            ->setRequired(['id', 'name']);

        $body = (new OpenApiTypes\ObjectType)
            ->addProperty('message', new OpenApiTypes\StringType)
            ->addProperty('conflicting_contact', $contact)
            ->setRequired(['message', 'conflicting_contact']);

        return Response::make(409)
            ->setDescription('Email belongs to another contact')
            ->setContent('application/json', Schema::fromType($body));
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', EmailConflictException::class, $this->components);
    }
}
