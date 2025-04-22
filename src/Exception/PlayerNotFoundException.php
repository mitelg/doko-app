<?php

declare(strict_types=1);
/**
 * Copyright (c) Michael Telgmann
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mitelg\DokoApp\Exception;

class PlayerNotFoundException extends \RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Player with id "%s" not found', $id));
    }
}
