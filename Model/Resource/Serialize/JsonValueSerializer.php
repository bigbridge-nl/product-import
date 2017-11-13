<?php

namespace BigBridge\ProductImport\Model\Resource\Serialize;

/**
 * @author Patrick van Bergen
 */
class JsonValueSerializer implements ValueSerializer
{
    public function serialize($value)
    {
        if (is_null($value)) {
            return null;
        } else {
            return json_encode($value);
        }
    }

    public function extract($serializedSource, string $field)
    {
        if (!is_null($serializedSource) && ($serializedSource !== "")) {
            $source = json_decode($serializedSource, true);
            if (array_key_exists($field, $source)) {
                return $source[$field];
            }
        }
        return null;
    }
}