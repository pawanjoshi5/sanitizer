<?php

namespace Nopensource\Sanitizer\Filters;

use Nopensource\Sanitizer\Contracts\Filter;

class Uppercase implements Filter
{
    /**
     *  Lowercase the given string.
     *
     *  @param  string  $value
     *  @return string
     */
    public function apply($value, $options = [])
    {
        return is_string($value) ? mb_strtoupper($value) : $value;
    }
}
