<?php

namespace Silber\Bouncer\Database\Titles;

use Illuminate\Support\Str;

abstract class Title
{
    /**
     * The human-readable title.
     *
     * @var string
     */
    protected $title;

    /**
     * Convert the given string into a human-readable format.
     *
     * @param  string|null  $value
     * @return string
     */
    protected function humanize($value)
    {
        if (is_null($value)) {
            return '';
        }

        // First we'll convert the string to snake case. Then we'll
        // convert all dashes and underscores to spaces. Finally,
        // we'll uppercase the 1st letter of the whole string.
        $value = Str::snake($value);

        $value = preg_replace('~(?:-|_)+~', ' ', $value);

        return ucfirst($value);
    }

    /**
     * Get the title as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->title;
    }
}
