<?php

namespace App\Processor;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Interface ProcessorInterface.
 */
interface ProcessorInterface
{
    /**
     * @return array
     */
    public function getConfiguration();

    /**
     * @param ArrayCollection $data
     *
     * @return ArrayCollection
     */
    public function format(ArrayCollection $data);
}
