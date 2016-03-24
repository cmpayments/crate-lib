<?php

namespace CmPayments\Crate\Tests;

use CmPayments\Crate\Compactor\CompactorInterface;

class Compactor implements CompactorInterface
{
    public function compact($contents)
    {
        return trim($contents);
    }

    public function supports($file)
    {
        return ('php' === pathinfo($file, PATHINFO_EXTENSION));
    }
}
