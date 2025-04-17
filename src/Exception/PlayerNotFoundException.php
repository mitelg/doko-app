<?php

declare(strict_types=1);

namespace Mitelg\DokoApp\Exception;

class PlayerNotFoundException extends \RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Player with id "%s" not found', $id));
    }
}
