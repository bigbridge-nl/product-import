<?php

namespace BigBridge\ProductImport\Model\Resource\Serialize;

/**
 * @author Patrick van Bergen
 */
class SerializeValueSerializer implements ValueSerializer
{
    public function serialize($value)
    {
        if (is_null($value)) {
            return null;
        } else {
            return serialize($value);
        }
    }

    public function extract($serializedSource, string $field)
    {
        if (!is_null($serializedSource) && ($serializedSource !== "")) {
            $source = unserialize($serializedSource);
            if (array_key_exists($field, $source)) {
                return $source[$field];
            }
        }
        return null;
    }
}