<?php
namespace Aura\Filter;

use Aura\Filter\Exception;
use Aura\Filter\Spec\SanitizeSpec;
use Aura\Filter\Spec\ValidateSpec;
use InvalidArgumentException;

class Filter
{
    /**
     * Stop filtering on a field when a rule for that field fails.
     */
    const HARD_RULE = 'HARD_RULE';

    /**
     * Continue filtering on a field even when a rule for that field fails.
     */
    const SOFT_RULE = 'SOFT_RULE';

    /**
     * Stop filtering on all fields when a rule fails.
     */
    const STOP_RULE = 'STOP_RULE';

    protected $specs = array();

    protected $skip = array();

    protected $messages = array();

    protected $field_messages = array();

    protected $validate_spec;

    protected $sanitize_spec;

    public function __construct(
        ValidateSpec $validate_spec,
        SanitizeSpec $sanitize_spec
    ) {
        $this->validate_spec = $validate_spec;
        $this->sanitize_spec = $sanitize_spec;
        $this->init();
    }

    protected function init()
    {
        // do nothing
    }

    public function __invoke(&$subject)
    {
        if ($this->apply($subject)) {
            return true;
        }

        $class = get_class($this);
        $message = PHP_EOL
                 . "  Filter: {$class}" . PHP_EOL
                 . "  Fields:" . PHP_EOL
                 . $this->getMessagesAsString();

        $e = new Exception\FilterFailed($message);
        $e->setFilterClass($class);
        $e->setFilterMessages($this->getMessages());
        $e->setFilterSubject($subject);
        throw $e;
    }

    public function validate($field)
    {
        return $this->addSpec(
            clone $this->validate_spec,
            $field
        );
    }

    public function sanitize($field)
    {
        return $this->addSpec(
            clone $this->sanitize_spec,
            $field
        );
    }

    public function useFieldMessage($field, $message)
    {
        $this->field_messages[$field] = $message;
    }

    protected function addSpec($spec, $field)
    {
        $this->specs[] = $spec;
        $spec->field($field);
        return $spec;
    }

    public function apply(&$subject)
    {
        if (is_array($subject)) {
            return $this->applyToArray($subject);
        }

        if (! is_object($subject)) {
            $type = gettype($subject);
            throw new InvalidArgumentException("Apply the filter to an object, not a {$type}.");
        }

        return $this->applyToObject($subject);
    }

    protected function applyToArray(&$array)
    {
        $object = (object) $array;
        $result = $this->applyToObject($object);
        $array = (array) $object;
        return $result;
    }

    protected function applyToObject($object)
    {
        $this->skip = array();
        $this->messages = array();
        $this->applySpecs($object);
        if ($this->messages) {
            return false;
        }
        return true;
    }

    protected function applySpecs($object)
    {
        foreach ($this->specs as $spec) {
            if ($this->skippedOrPassed($spec, $object)) {
                continue;
            }
            if ($this->failed($spec) === self::STOP_RULE) {
                break;
            }
        }
    }

    protected function skippedOrPassed($spec, $object)
    {
        return isset($this->skip[$spec->getField()])
            || call_user_func($spec, $object);
    }

    protected function failed($spec)
    {
        $field = $spec->getField();

        if (isset($this->field_messages[$field])) {
            $this->messages[$field] = array($this->field_messages[$field]);
        } else {
            $this->messages[$field][] = $spec->getMessage();
        }

        $failure_mode = $spec->getFailureMode();
        if ($failure_mode === self::HARD_RULE) {
            $this->skip[$field] = true;
        }

        return $failure_mode;
    }

    public function getMessages($field = null)
    {
        if (! $field) {
            return $this->messages;
        }

        if (isset($this->messages[$field])) {
            return $this->messages[$field];
        }

        return array();
    }

    public function getMessagesAsString()
    {
        $string = '';
        foreach ($this->getMessages() as $field => $messages) {
            foreach ($messages as $message) {
                $string .= "    {$field}: {$message}" . PHP_EOL;
            }
        }
        return $string;
    }
}