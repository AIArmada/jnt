<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use InvalidArgumentException;

/**
 * Verifies owner tuples inherited from a parent order against the ambient
 * owner context.
 *
 * `HasOwner` validates preset owner keys before `creating` listeners run,
 * but inherited tuples are assigned inside those listeners — after the
 * framework check. Without this, an ambient owner plus disabled auto-assign
 * could silently persist a child into another owner's scope.
 */
final class InheritedOwnerGuard
{
    public static function assertMatchesContext(?string $ownerType, string | int | null $ownerId, string $model): void
    {
        if (! (bool) config('jnt.owner.enabled', false)) {
            return;
        }

        if ($ownerType === null && $ownerId === null) {
            return;
        }

        $context = OwnerContext::resolve();

        if ($context === null) {
            return;
        }

        if ($ownerType === $context->getMorphClass() && (string) $ownerId === (string) $context->getKey()) {
            return;
        }

        throw new InvalidArgumentException("{$model} inherits an owner outside the current owner context.");
    }
}
