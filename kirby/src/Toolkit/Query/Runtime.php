<?php

namespace Kirby\Toolkit\Query;

class Runtime {
    static function access($object, $key, bool $nullSafe = false): mixed {
        if($nullSafe && $object === null) {
            return null;
        }

        if(is_array($object)) {
            return $object[$key] ?? null;
        } else if(is_object($object)) {
            if(method_exists($object, $key) || method_exists($object, '__call')) {
                return $object->$key();
            }
            return $object->$key ?? null;
        } else {
            throw new \Exception("Cannot access \"$key\" on " . gettype($object));
        }
    }
}
