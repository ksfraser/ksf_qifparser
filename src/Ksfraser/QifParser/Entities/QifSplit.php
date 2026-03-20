<?php

namespace Ksfraser\QifParser\Entities;

/**
 * Entity representing a single split line within a split transaction.
 *
 * @requirement FR-2.1.4 (Split Transaction Support)
 */
class QifSplit
{
    /** @var float */
    public $amount = 0.0;

    /** @var string|null */
    public $category;

    /** @var string|null */
    public $memo;
}
