<?php
/*
 * @Author: 沉梦 chenmxgg@163.com <blog.achaci.cn>
 * @Date: 2022-10-15 13:56:41
 * @LastEditors: 沉梦 chenmxgg@163.com <blog.achaci.cn>
 * @LastEditTime: 2022-10-22 22:10:23
 * @Description:
 *
 * Copyright (c) 2022 by 成都沉梦科技, All Rights Reserved.
 */

namespace Chenm\WebmanWebsafe\Main;

!defined('DS') && define('DS', DIRECTORY_SEPARATOR);

/**
 * 简单的日志记录类
 */
class Log
{
    private $name;
    private $dir;
    private $dirname;
    private $path;
    private $saveDay = 15;

    /**
     * Webman请求类
     * @var \Webman\Http\Request|\support\Request|null
     */
    private $request;

    /**
     * 初始化构造函数
     *
     * @param int $_saveDay 日志保留天数
     */
    public function __construct($_saveDay = 15, $request = null)
    {
        if ($_saveDay > 0) {
            $this->saveDay = $_saveDay;
        }

        if ($request instanceof \Webman\Http\Request  || $request instanceof \support\Request) {
            $this->request = $request;
        } else {
            $this->request = request();
        }

        // 日志目录
        $this->dirname = dirname(__DIR__) . "/Runtime/";
        // 设置日志目录和路径
        $this->setLogDir();
        // 删除历史过期日志
        $this->delDir($this->dirname);
    }

    /**
     * 设置日志子类型名称
     *
     * @param string|null $name
     * @return Log
     */
    public function setName(string $name): self
    {
        if ($name) {
            $this->name = $name;
            // 日志目录
            $this->dirname = dirname(__DIR__) . "/Runtime/" . $this->name . '/';
            // 设置日志目录和路径
            $this->setLogDir();
        }

        return $this;
    }

    /**
     * 添加自动日志
     *
     * @param string $action 类型
     * @param string $msg 内容 可空
     * @param bool $desc 是否记录GET和POST数据
     */
    public function add(string $action, string $msg = '', bool $desc = true): void
    {
        $runPath = $this->getRunPath();
        $txt     = "[" . date("Y-m-d H:i:s") . "] ";
        if ($action) {
            $txt .= '>>' . $action . '<< ';
        }

        if ($msg) {
            $txt .= $msg;
        }

        if ($desc) {
            $txt .= "\nPOST:" . json_encode($_POST) . "\nGET:" . json_encode($_GET);
            $txt .= "\nrunPath:" . $runPath;
        }

        $txt .= "\n";
        $fp = fopen($this->path, "a");
        if ($fp) {
            flock($fp, LOCK_EX);
            fwrite($fp, $txt);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 获取当前请求路径
     *
     * @return string
     */
    private function getRunPath(): string
    {
        $scriptName = $this->request->path();
        $s          = stripos($scriptName, '?');
        if ($s > 0) {
            $scriptName = substr($scriptName, 0, $s);
        }
        return $scriptName;
    }

    /**
     * 递归创建文件夹
     *
     * @param string $dir 文件夹路径
     * @return bool
     */
    private function makedir(string $dir = ''): bool
    {
        $dir    = str_replace('/', DIRECTORY_SEPARATOR, $dir);
        $arr    = explode(DIRECTORY_SEPARATOR, trim($dir, DIRECTORY_SEPARATOR));
        $newDir = DIRECTORY_SEPARATOR;
        foreach ($arr as $value) {
            $newDir .= $value . DIRECTORY_SEPARATOR;
            if (!is_dir($newDir)) {
                try {
                    mkdir($newDir, 0755, true);
                } catch (\Exception $e) {
                    // 忽略错误
                    return false;
                }
            }
        }
        return is_dir($newDir);
    }

    /**
     * 设置日志文件目录
     *
     * @return bool
     */
    private function setLogDir(): bool
    {
        $this->dir = $this->dirname;
        $this->dir = rtrim($this->dir, '/') . '/' . date("Ym/d");
        $this->makedir($this->dir);
        $this->setLogFile();
        return true;
    }

    /**
     * 设置日志文件名称
     *
     * @return bool
     */
    private function setLogFile(): bool
    {
        $h = date("H");
        $i = date("i");
        if ($i > 30) {
            $e    = $h + 1;
            $file = $this->dir . '/' . $h . ':30~' . $e . ':00.txt';
        } else {
            $file = $this->dir . '/' . $h . ':00~' . $h . ':30.txt';
        }

        $this->path = $file;
        return true;
    }

    /**
     * 递归删除过期日志文件
     *
     * @param string $dir 文件夹路径
     * @return bool
     */
    private function delDir(string $dir): bool
    {
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) {
            return false;
        }

        $files = scandir($dir);
        if ($files === false) {
            return false;
        }

        $now = new \DateTime();
        foreach ($files as $filename) {
            if ($filename === "." || $filename === "..") {
                continue;
            }

            $filePath = $dir . $filename;
            if (is_dir($filePath)) {
                try {
                    $dirTime = \DateTime::createFromFormat('Ym', $filename);
                    if ($dirTime === false) {
                        continue;
                    }

                    $interval = $now->diff($dirTime);
                    if ($interval->days > $this->saveDay) {
                        $this->delFile($filePath . '/');
                        @rmdir($filePath);
                    }
                } catch (\Exception $e) {
                    // 忽略错误
                    continue;
                }
            }
        }
        return true;
    }

    /**
     * 递归删除目录文件
     *
     * @param string $dir 文件夹路径
     * @return bool
     */
    private function delFile(string $dir): bool
    {
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) {
            return false;
        }

        $files = scandir($dir);
        if ($files === false) {
            return false;
        }

        foreach ($files as $filename) {
            if ($filename === "." || $filename === "..") {
                continue;
            }

            $filePath = $dir . $filename;
            if (is_dir($filePath)) {
                $this->delFile($filePath . '/');
            } else {
                @unlink($filePath);
            }
        }

        @rmdir($dir);
        return true;
    }
}
