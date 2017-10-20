<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class References
{
    public $names;

    public function __construct(array $names)
    {
        $this->names = $names;
    }

}