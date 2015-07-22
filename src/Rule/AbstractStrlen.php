<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Filter\Rule;

/**
 *
 * Abstract rule for string-length filters; supports the `mbstring` extension.
 *
 * @package Aura.Filter
 *
 */
abstract class AbstractStrlen
{
    protected function mbstring()
    {
        return extension_loaded('mbstring');
    }

    protected function iconv()
    {
        return extension_loaded('iconv');
    }

    protected function libxml()
    {
        return extension_loaded('libxml');
    }

    /**
     *
     * Proxy to `iconv_strlen()` or `mb_strlen()` when available; fall back to
     * `utf8_decode()` and `strlen()` otherwise.
     *
     * @param string $str Return the number of characters in this string.
     *
     * @return int
     *
     */
    protected function strlen($str)
    {
        if ($this->iconv()) {
            return $this->strlenIconv($str);
        }

        if ($this->mbstring()) {
            return mb_strlen($str, 'UTF-8');
        }

        if ($this->libxml()) {
            return strlen(utf8_decode($str));
        }

        return strlen($str);
    }

    protected function strlenIconv($str)
    {
        $level = error_reporting(0);
        $strlen = iconv_strlen($str, 'UTF-8');
        error_reporting($level);

        if ($strlen !== false) {
            return $strlen;
        }

        throw new Exception\MalformedUtf8();
    }

    /**
     *
     *  Proxy to `iconv_substr()` when the `iconv` extension is loaded,
     * else to `mb_substr()` when the `mbstring` extension is loaded,
     * otherwise to `substr polyfill`.
     *
     * @param string $str The string to work with.
     *
     * @param int $start Start at this position.
     *
     * @param int $length End after this many characters.
     *
     * @return string
     *
     */
    protected function substr($str, $start, $length = null)
    {
        if ($this->iconv()) {
            return iconv_substr($str, $start, $length, 'UTF-8');
        }

        if ($this->mbstring()) {
            return mb_substr($str, $start, $length, 'UTF-8');
        }

        $split = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
        return join('', array_slice($split, $start, $length));
    }


    protected function strpad($input, $length, $pad_str = ' ', $type = STR_PAD_RIGHT)
    {
        $input_len = $this->strlen($input);
        if ($length <= $input_len) {
            return $input;
        }

        $pad_str_len = $this->strlen($pad_str);
        $pad_len = $length - $input_len;

        if ($type == STR_PAD_RIGHT) {
            $repeat_times = ceil($pad_len / $pad_str_len);
            $input .= str_repeat($pad_str, $repeat_times);
            return $this->substr($input, 0, $length);
        }

        if ($type == STR_PAD_LEFT) {
            $repeat_times = ceil($pad_len / $pad_str_len);
            $prefix = str_repeat($pad_str, $repeat_times);
            return $this->substr($prefix, 0, floor($pad_len)) . $input;
        }

        if ($type == STR_PAD_BOTH) {
            $pad_len /= 2;
            $pad_amount_left = floor($pad_len);
            $pad_amount_right = ceil($pad_len);
            $repeat_times_left = ceil($pad_amount_left / $pad_str_len);
            $repeat_times_right = ceil($pad_amount_right / $pad_str_len);

            $prefix = str_repeat($pad_str, $repeat_times_left);
            $padding_left = $this->substr($prefix, 0, $pad_amount_left);

            $suffix = str_repeat($pad_str, $repeat_times_right);
            $padding_right = $this->substr($suffix, 0, $pad_amount_right);

            return $padding_left . $input . $padding_right;
        }
    }
}
