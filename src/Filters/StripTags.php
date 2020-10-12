<?php

namespace Nopensource\Sanitizer\Filters;

use Nopensource\Sanitizer\Contracts\Filter;

class StripTags implements Filter
{
    /**
     *  Strip tags from the given string.
     *
     *  @param  string  $value
     *  @return string
     */
    public function apply($value, $options = [])
    {
        return is_string($value) ? strip_tags($value) : $value;
    }
}
