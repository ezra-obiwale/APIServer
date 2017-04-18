<?php

/**
 * Description of Validator
 *
 * @author Ezra Obiwale <contact@ezraobiwale.com>
 */
class Validator {

    private $data;
    private $messages;
    private $rules;
    private $current;

    public function __construct($data, array $rules, array $messages = []) {
        $this->rules = $rules;
        $this->messages = $messages;
        $this->data = $data;
    }

    public function run() {
        foreach ($this->rules as $field => $rule) {
            // get rules separated by pipe
            $rules = explode('|', $rule);
            foreach ($rules as $_rule) {
                // remove surrounding spaces
                $_rule = trim($_rule);
                // get rule to parameter pairs
                $parts = explode(':', $_rule);
                // check if target method exist. go to next rule if not.
                if (!$parts[0] || !method_exists($this, $parts[0] . 'Rule') || (!$this->hasValue($field) && $parts[0] !== 'required'))
                    continue;
                // mark method as current action
                $this->current = $parts[0];
                // get parameter parts
                $params = explode(',', $parts[1]);
                // prepend the name of the target field to the parameters
                array_unshift($params, $field);
                // call method and throw exception if returns error message
                if ($message = call_user_func_array([$this, $parts[0] . 'Rule'], $params))
                    throw new Exception($message);
            }
        }
    }

    private function hasValue($field) {
        $val = $this->getValue($field);
        return $val || $val != 0;
    }

    private function getValue($field) {
        if (strstr($field, '.')) {
            $value = null;
            foreach (explode('.', $field) as $name) {
                if (!$value)
                    $value = $this->data[$name];
                else
                    $value = $value[$name];
                if (!$value || $value == 0)
                    break;
            }
            return $value;
        }
        return $this->data[$field];
    }

    private function getMessage($field, $default, array $params = []) {
        if (is_array($this->messages[$field])) {
            if (array_key_exists($this->current, $this->messages[$field]))
                return $this->messages[$field][$this->current];
        }
        else if (is_string($this->messages[$field]))
            return $this->messages[$field];
        foreach ($params as $key => $value) {
            $default = str_replace("${$key}", $this->cleanName($value), $default);
        }
        return ucfirst($this->cleanName($field)) . $default;
    }

    private function cleanName($name) {
        return str_replace('_', ' ', str_replace('.', ' ', camelTo_($name)));
    }

    private function requiredRule($field) {
        $message = $this->getMessage($field, ' is required');
        $value = $this->getValue($field);
        return $value || $value != 0 ? null : $message;
    }

    private function numericRule($field) {
        if (!is_string($this->regexRule($field, '/[^0-9]/'))) {
            return $this->getMessage($field, ' must be numeric');
        }
    }

    private function regexRule($field, $pattern, $message = null) {
        return !preg_match($pattern, $this->data[$field]) ?
                $this->getMessage($field, $message ?: ' does not match expectation') :
                null;
    }

    private function matchRule($field, $target) {
        return $this->getValue($field) !== $this->getValue($target) ?
                $this->getMessage($field, ' must match $1', [$target]) : null;
    }

    private function notmatchRule($field, $target) {
        return $this->getValue($field) === $this->getValue($target) ?
                $this->getMessage($field, ' must not match $1', [$target]) : null;
    }

    private function greaterthanRule($field, $target) {
        return $this->getValue($field) <= $this->getValue($target) ?
                $this->getMessage($field, ' must be greater than $1', [$target]) : null;
    }

    private function lessthanRule($field, $target) {
        return $this->getValue($field) >= $this->getValue($target) ?
                $this->getMessage($field, ' must be less than $1', [$target]) : null;
    }

    private function greaterthanorequaltoRule($field, $target) {
        return $this->getValue($field) < $this->getValue($target) ?
                $this->getMessage($field, ' must be greater than, or equal to, $1', [$target]) : null;
    }

    private function lessthanorequaltoRule($field, $target) {
        return $this->getValue($field) > $this->getValue($target) ?
                $this->getMessage($field, ' must be less than, or equal to, $1', [$target]) : null;
    }

    private function betweenRule($field, $val1, $val2) {
        $val = $this->getValue($field);
        return ($val > $val2 || $val < $val1) ?
                $this->getMessage($field, ' must be less than, or equal to, $1', [$target]) : null;
    }

    private function dateRule($field) {
        return !strtotime($this->getValue($field)) ?
                $this->getMessage($field, ' must be a date') : null;
    }

}
