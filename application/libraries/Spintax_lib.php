<?php
/**
 * Spintax - A helper class to process Spintax strings.
 * @name Spintax
 * @author Gounane abdallah - http://www.fb.com/gounane/
 */
class Spintax
{
    public function get($text)
    {
        return preg_replace_callback(
            '/\{(((?>[^\{\}]+)|(?R))*)\}/x',
            array($this, 'replace'),
            $text
        );
    }

    public function replace($text)
    {
        $text = $this->get($text[1]);
        $parts = explode('|', $text);
        return $parts[array_rand($parts)];
    }
}
?>