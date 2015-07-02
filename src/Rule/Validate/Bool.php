<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Filter\Rule\Validate;

use Aura\Filter\Rule\AbstractBool;

/**
 *
 * Rule for booleans.
 *
 * @package Aura.Filter
 *
 */
class Bool extends AbstractBool
{
    /**
     *
     * Validates that the value is a boolean representation.
     *
     * @param object $subject The subject to be filtered.
     *
     * @param string $field The subject field name.
     *
     * @return bool True if valid, false if not.
     *
     */
    public function __invoke($subject, $field)
    {
        $value = $subject->$field;
        return $this->isTrue($value) || $this->isFalse($value);
    }
}
