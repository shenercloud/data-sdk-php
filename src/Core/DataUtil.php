<?php
namespace ShenerCloud\Core;

use ShenerCloud\Exception\DataException;

/**
 * Class OssUtil
 *
 * 工具类，主要供Client使用，用户也可以使用本类进行返回结果的格式化
 */
class DataUtil
{
    const DATA_CONTENT = 'content';
    const DATA_LENGTH = 'length';
    const DATA_HEADERS = 'headers';
    const DATA_MAX_OBJECT_GROUP_VALUE = 1000;
    const DATA_MAX_PART_SIZE = 5368709120; // 5GB
    const DATA_MID_PART_SIZE = 10485760; // 10MB
    const DATA_MIN_PART_SIZE = 102400; // 100KB

    /**
     * 生成query params
     *
     * @param array $options 关联数组
     * @return string 返回诸如 key1=value1&key2=value2
     */
    public static function toQueryString($options = array())
    {
        $temp = array();
        uksort($options, 'strnatcasecmp');
        foreach ($options as $key => $value) {
            if (is_string($key) && !is_array($value)) {
                $temp[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }
        return implode('&', $temp);
    }

    /**
     * 转义字符替换
     *
     * @param string $subject
     * @return string
     */
    public static function sReplace($subject)
    {
        $search = array('<', '>', '&', '\'', '"');
        $replace = array('&lt;', '&gt;', '&amp;', '&apos;', '&quot;');
        return str_replace($search, $replace, $subject);
    }

    /**
     * 检查是否是中文编码
     *
     * @param $str
     * @return int
     */
    public static function chkChinese($str)
    {
        return preg_match('/[\x80-\xff]./', $str);
    }

    /**
     * 检测是否GB2312编码
     *
     * @param string $str
     * @return boolean false UTF-8编码  TRUE GB2312编码
     */
    public static function isGb2312($str)
    {
        for ($i = 0; $i < strlen($str); $i++) {
            $v = ord($str[$i]);
            if ($v > 127) {
                if (($v >= 228) && ($v <= 233)) {
                    if (($i + 2) >= (strlen($str) - 1)) return true;  // not enough characters
                    $v1 = ord($str[$i + 1]);
                    $v2 = ord($str[$i + 2]);
                    if (($v1 >= 128) && ($v1 <= 191) && ($v2 >= 128) && ($v2 <= 191))
                        return false;
                    else
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * 检测是否GBK编码
     *
     * @param string $str
     * @param boolean $gbk
     * @return boolean
     */
    public static function checkChar($str, $gbk = true)
    {
        for ($i = 0; $i < strlen($str); $i++) {
            $v = ord($str[$i]);
            if ($v > 127) {
                if (($v >= 228) && ($v <= 233)) {
                    if (($i + 2) >= (strlen($str) - 1)) return $gbk ? true : FALSE;  // not enough characters
                    $v1 = ord($str[$i + 1]);
                    $v2 = ord($str[$i + 2]);
                    if ($gbk) {
                        return (($v1 >= 128) && ($v1 <= 191) && ($v2 >= 128) && ($v2 <= 191)) ? FALSE : TRUE;//GBK
                    } else {
                        return (($v1 >= 128) && ($v1 <= 191) && ($v2 >= 128) && ($v2 <= 191)) ? TRUE : FALSE;
                    }
                }
            }
        }
        return $gbk ? TRUE : FALSE;
    }

    /**
     * 检验bucket名称是否合法
     * bucket的命名规范：
     * 1. 只能包括小写字母，数字
     * 2. 必须以小写字母或者数字开头
     * 3. 长度必须在3-63字节之间
     *
     * @param string $bucket Bucket名称
     * @return boolean
     */
    public static function validateBucket($bucket)
    {
        $pattern = '/^[a-z0-9][a-z0-9-]{2,62}$/';
        if (!preg_match($pattern, $bucket)) {
            return false;
        }
        return true;
    }

    /**
     * 检验object名称是否合法
     * object命名规范:
     * 1. 规则长度必须在1-1023字节之间
     * 2. 使用UTF-8编码
     * 3. 不能以 "/" "\\"开头
     *
     * @param string $object Object名称
     * @return boolean
     */
    public static function validateObject($object)
    {
        $pattern = '/^.{1,1023}$/';
        if (empty($object) || !preg_match($pattern, $object) ||
            self::startsWith($object, '/') || self::startsWith($object, '\\')
        ) {
            return false;
        }
        return true;
    }


    /**
     * 判断字符串$str是不是以$findMe开始
     *
     * @param string $str
     * @param string $findMe
     * @return bool
     */
    public static function startsWith($str, $findMe)
    {
        if (strpos($str, $findMe) === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检验$options
     *
     * @param array $options
     * @throws OssException
     * @return boolean
     */
    public static function validateOptions($options)
    {
        //$options
        if ($options != NULL && !is_array($options)) {
            throw new DataException ($options . ':' . 'option must be array');
        }
    }

    /**
     * 检测是否windows系统，因为windows系统默认编码为GBK
     *
     * @return bool
     */
    public static function isWin()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) == "WIN";
    }

    /**
     * 主要是由于windows系统编码是gbk，遇到中文时候，如果不进行转换处理会出现找不到文件的问题
     *
     * @param $file_path
     * @return string
     */
    public static function encodePath($file_path)
    {
        if (self::chkChinese($file_path) && self::isWin()) {
            $file_path = iconv('utf-8', 'gbk', $file_path);
        }
        return $file_path;
    }

    /**
     * Decode key based on the encoding type
     *
     * @param string $key
     * @param string $encoding
     * @return string
     */
    public static function decodeKey($key, $encoding)
    {
        if ($encoding == "") {
            return $key;
        }

        if ($encoding == "url") {
            return rawurldecode($key);
        } else {
            throw new DataException("Unrecognized encoding type: " . $encoding);
        }
    }
}
