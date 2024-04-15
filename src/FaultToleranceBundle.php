<?php

declare(strict_types=1);

namespace MicroModule\FaultToleranceBundle;

use MicroModule\FaultToleranceBundle\DependencyInjection\FaultToleranceBundleExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class FaultToleranceBundle.
 */
class FaultToleranceBundle extends Bundle
{
    /**
     * @return FaultToleranceBundleExtension
     */
    public function getContainerExtension(): FaultToleranceBundleExtension
    {
        return new FaultToleranceBundleExtension();
    }
}
