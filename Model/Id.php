<?php

namespace BigBridge\ProductImport\Model;

/**
 * An id placeholder, that contains the name that is to be resolved into an id.
 *
 * @author Patrick van Bergen
 */
class Id
{
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}