<?php

namespace Nopensource\Sanitizer;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\ClosureValidationRule;
use InvalidArgumentException;
use Nopensource\Sanitizer\Contracts\Filter;
use Nopensource\Sanitizer\Filters\Capitalize;
use Nopensource\Sanitizer\Filters\Cast;
use Nopensource\Sanitizer\Filters\EscapeHTML;
use Nopensource\Sanitizer\Filters\FormatDate;
use Nopensource\Sanitizer\Filters\Lowercase;
use Nopensource\Sanitizer\Filters\Uppercase;
use Nopensource\Sanitizer\Filters\Trim;
use Nopensource\Sanitizer\Filters\StripTags;
use Nopensource\Sanitizer\Filters\Digit;
use Nopensource\Sanitizer\Filters\FilterIf;

/**
 * Class Sanitizer
 * @package Nopensource\Sanitizer
 */
class Sanitizer
{
    /**
     *  Data to sanitize
     *  @var array
     */
    protected $data;

    /**
     *  Filters to apply
     *  @var array
     */
    protected $rules;

    /**
     *  Available filters as $name => $classPath
     *  @var array
     */
    protected $filters = [
        'capitalize'  => Capitalize::class,
        'cast'        => Cast::class,
        'escape'      => EscapeHTML::class,
        'format_date' => FormatDate::class,
        'lowercase'   => Lowercase::class,
        'uppercase'   => Uppercase::class,
        'trim'        => Trim::class,
        'strip_tags'  => StripTags::class,
        'digit'       => Digit::class,
        'filter_if'   => FilterIf::class,
    ];

    /**
     *  Create a new sanitizer instance.
     *
     *  @param  array   $data
     *  @param  array   $rules      Rules to be applied to each data attribute
     *  @param  array   $filters    Available filters for this sanitizer
     *  @return Sanitizer
     */
    public function __construct(array $data, array $rules, array $customFilters = [])
    {
        $this->data    = $data;
        $this->rules   = $this->parseRules($rules);
        $this->filters = array_merge($this->filters, $customFilters);
    }

    /**
     *  Parse a rules array.
     *
     *  @param  array $rules
     *  @return array
     */
    protected function parseRules(array $rules)
    {
        $parsedRules = [];

        $rawRules = (new ValidationRuleParser($this->data))->explode($rules);

        foreach ($rawRules->rules as $attribute => $attributeRules) {
            foreach ($attributeRules as $attributeRule) {
                $parsedRule = $this->parseRule($attributeRule);
                if ($parsedRule) {
                    $parsedRules[$attribute][] = $parsedRule;
                }
            }
        }

        return $parsedRules;
    }

    /**
     *  Parse a rule.
     *
     *  @param  string|Closure $rule
     *  @return array|Closure
     */
    protected function parseRule($rule)
    {
        if (is_string($rule)) {
            return $this->parseRuleString($rule);
        } elseif ($rule instanceof ClosureValidationRule) {
            return $rule->callback;
        } else {
            throw new InvalidArgumentException("Unsupported rule type.");
        }
    }

    /**
     *  Parse a rule string formatted as filterName:option1, option2 into an array formatted as [name => filterName, options => [option1, option2]]
     *
     *  @param  string $rule    Formatted as 'filterName:option1, option2' or just 'filterName'
     *  @return array           Formatted as [name => filterName, options => [option1, option2]]. Empty array if no filter name was found.
     */
    protected function parseRuleString($rule)
    {
        if (strpos($rule, ':') !== false) {
            list($name, $options) = explode(':', $rule, 2);
            $options              = array_map('trim', explode(',', $options));
        } else {
            $name    = $rule;
            $options = [];
        }
        if (!$name) {
            return [];
        }
        return compact('name', 'options');
    }

    /**
     *  Apply the given filter by its name
     *
     *  @param  string|Closure $rule
     *  @return Filter
     */
    protected function applyFilter($rule, $value)
    {
        if ($rule instanceof Closure) {
            return call_user_func($rule, $value);
        }

        $name    = $rule['name'];
        $options = $rule['options'];

        // If the filter does not exist, throw an Exception:
        if (!isset($this->filters[$name])) {
            throw new InvalidArgumentException("No filter found by the name of $name");
        }

        $filter = $this->filters[$name];

        if ($filter instanceof Closure) {
            return call_user_func_array($filter, [$value, $options]);
        } else {
            return (new $filter)->apply($value, $options);
        }
    }

    /**
     *  Sanitize the given data
     *
     *  @return array
     */
    public function sanitize()
    {
        $sanitized = $this->data;

        foreach ($this->rules as $attr => $rules) {
            if (Arr::has($this->data, $attr)) {
                $value = Arr::get($this->data, $attr);
                $original = $value;

                $sanitize = true;
                foreach ($rules as $rule) {
                    if (is_array($rule) && $rule['name'] === 'filter_if') {
                        $sanitize = $this->applyFilter($rule, $this->data);
                    } else {
                        $value = $this->applyFilter($rule, $value);
                    }
                }

                if ($sanitize) {
                    Arr::set($sanitized, $attr, $value);
                } else {
                    Arr::set($sanitized, $attr, $original);
                }
            }
        }

        return $sanitized;
    }
}
