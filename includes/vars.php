<?php
class vars
{
    private $vars;

    public function setValue($key = NULL, $value = "")
    {
        if ($key == NULL) {
            return;
        }

        $this->vars[$key] = $value;
    }

    public function getValue($key) {
        if(!isset($this->vars[$key])) {
            return "";
        }

        return $this->vars[$key];
    }
}