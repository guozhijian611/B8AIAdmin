<?php
/**
 * Here is your custom functions.
 */

if (!function_exists('generateRandomNumber'))
{
    /**
     * 生成指定长度随机数
     * @param $length
     * @return string
     */
    function generateRandomNumber($length): string
    {
        $randomNumber = '';
        for ($i = 0; $i < $length; $i++) {
            $randomNumber .= rand(0, 9);
        }
        return $randomNumber;
    }
}

if (!function_exists('autoReplace'))
{
    /**
     * 自动替换模板数据
     * @param $content
     * @param $templateData
     * @return string
     */
    function autoReplace($content, $templateData): string
    {
        if ($templateData) {
            $replacePairs = [];
            foreach ($templateData as $key => $value) {
                $replacePairs['${' . $key . '}'] = (string) $value;
                $replacePairs['{' . $key . '}'] = (string) $value;
            }
            $content = strtr((string) $content, $replacePairs);
        }

        return (string) $content;
    }
}
