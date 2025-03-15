<?php
/*
 * @Author: 沉梦 chenmxgg@163.com <blog.achaci.cn>
 * @Date: 2022-10-29 15:41:41
 * @LastEditors: 沉梦 chenmxgg@163.com <blog.achaci.cn>
 * @LastEditTime: 2022-11-04 22:05:47
 * @Description:
 *
 * Copyright (c) 2022 by 成都沉梦科技, All Rights Reserved.
 */

namespace Chenm\WebmanWebsafe\Main;

/**
 * webman-web 安全主文件
 */
class Core extends Config implements \Webman\MiddlewareInterface
{

    /**
     * @var Log
     */
    private $log;

    private $intercept = false;

    private $returnData = '';

    /**
     * Webman请求类
     * @var \Webman\Http\Request|\support\Request|null
     */
    private $request;

    public function process(\Webman\Http\Request $request, callable $handler): \Webman\Http\Response
    {
        // 请求类
        $this->request = $request;

        $this->run();

        // 拦截
        if ($this->intercept === true) {
            return response($this->returnData, 200, [
                'Content-Type' => 'text/html;charset=utf-8',
            ]);
        }
        // 不需要拦截
        return $handler($request);
    }

    /**
     * @var static
     */
    private static $instance = null;

    public function __construct($config = [])
    {
        //日志类实例化
        $this->log = new Log(30, $this->request);
        return $this;
    }

    /**
     * 初始化防御脚本
     *
     * @param  array $config
     * @return $this
     */
    public static function init($config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    /**
     * 获取需要验证的数据
     *
     * @param  string $key 验证类型
     * @return array
     */
    private function getCheckData($key = 'GET')
    {

        $result = [[], [], ''];
        $key    = strtolower($key);
        $data   = [
            'get'     => $this->request->get(),
            'post'    => $this->request->post(),
            'cookie'  => $this->request->cookie(),
            'session' => $this->request->session()->all(),
            'server'  => $this->request->header(),
        ];

        if (isset($data[$key])) {
            $result[0] = array_keys($data[$key]);
            $result[1] = array_values($data[$key]);
        }

        $rulesData = [
            'get'     => self::$GET_S,
            'post'    => self::$POST_S,
            'cookie'  => self::$COOKIE_S,
            'session' => self::$SESSION_S,
            'server'  => self::$SERVER_S,
        ];

        if (isset($rulesData[$key])) {
            $result[2] = $this->config_extends['XSS']['open'] ? [$rulesData[$key], self::$XSS_S] : [$rulesData[$key]];
        }
        return $result;
    }

    /**
     * 执行脚本  并自动记录拦截日志
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->config as $key => $item) {
            if ($item['open']) {
                if (!$this->checkWhite($this->white[$key])) {
                    [$keys, $values, $rules] = $this->getCheckData($key);
                    foreach ($rules as $key => $rule) {
                        (count($keys) > 0 || count($values) > 0) && $this->checkSafe([$key, $item], $keys, $values, $rule);
                    }
                }
            }
        }
    }

    /**
     * 检测是否触发防火墙
     *
     * @param  array  $keys
     * @param  array  $values
     * @param  string $rule
     * @return void
     */
    private function checkSafe(array $config = [], array $keys = [], array $values = [], string $rule = '')
    {
        // 检测键名
        $this->checkItem($config, $rule, $keys);
        // 检测键值
        $this->checkItem($config, $rule, $values);
    }

    /**
     * 执行检测
     * @param array        $config 检测配置
     * @param string       $rule   检测正则
     * @param string|array $data   检测内容
     * @return void
     */
    public function checkItem($config = [], $rule = '', $data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $this->checkItem($config, $rule, $value);
            }
        } else {
            if (preg_match($rule, $data, $match)) {
                $this->handleCall([
                    'data'    => [
                        'Uri'   => $this->getFillerUri(),
                        'Rule'  => $rule,
                        'Value' => $data,
                        'Match' => $match,
                    ],
                    'config'  => $config,
                    'message' => '检测到非法字符，已被系统拦截！',
                ]);
            }
        }
    }

    /**
     * 安全拦截回调处理
     *
     * @param  array $data
     * @return void
     */
    private function handleCall(array $data = [])
    {
        //记录日志
        $type    = isset($data['config'][0]) && $data['config'][0] ? $data['config'][0] : 'unknown';
        $level   = isset($data['config'][1]) && isset($data['config'][1]['level']) ? $data['config'][1]['level'] : 0;
        $logData = '类型 [ ' . $type . ' ] 链接 [ ' . $data['data']['Uri'] . ' ] 规则 [' . $data['data']['Rule'] . ']  触发内容 [' . $data['data']['Value'] . ']  触发词 [' . $data['data']['Match'][0] . '] ';
        switch ($level) {
            //普通级别
            case 1:
                $this->infoLog($type, $logData);
                break;
            //异常级别
            case 2:
                $this->warningLog($type, $logData);
                $this->intercept = true;
                break;
            //危险级别
            case 2:
                $this->dangerLog($type, $logData);
                $this->intercept = true;
                break;
            //其他
            default:
                $this->otherLog($type, $logData);
                $this->intercept = true;
                break;
        }

        try {
            if ($this->intercept) {
                //输出系统提示
                $name = '\\Chenm\\websafe\\Tpl\\' . $this->tpl;
                $obj  = new $name();
                if (method_exists($obj, 'showPage')) {
                    $this->returnData = $obj->showPage($data['message']);
                }
            }
        } catch (\Exception $e) {
            //throw $th;
            $this->runErrorLog('安全提示执行错误：' . $e->getMessage());
        }
    }

    /**
     * 检测是否白名单
     *
     * @param  array       $rules 白名单规则
     * @param  string|null $uri   请求uri
     * @return bool
     */
    private function checkWhite($rules = [], ?string $uri = null)
    {
        $request_uri = $uri ?? $this->getFillerUri();
        if (!$request_uri) {
            return false;
        }

        $rules = is_array($rules) ? $rules : [$rules];
        foreach ($rules as $key => $ruleValue) {
            if (preg_match('/' . $ruleValue . '/', $request_uri)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取uri
     *
     * @return string
     */
    private function getFillerUri($uri = null)
    {
        return str_replace(explode('|', '\w|.*?|.*|\S|\s'), '', $uri ?: $this->request->uri());
    }

    /**
     * 异常日志
     *
     * @return void
     */
    public function warningLog($type, $msg)
    {
        $this->log->setName('warn')->add($type, $msg, false);
    }

    /**
     * 危险日志
     *
     * @return void
     */
    public function dangerLog($type, $msg)
    {
        $this->log->setName('danger')->add($type, $msg, false);
    }

    /**
     * 可疑日志
     *
     * @return void
     */
    public function infoLog($type, $msg)
    {
        $this->log->setName('info')->add($type, $msg, false);
    }

    /**
     * 其他日志
     *
     * @return void
     */
    public function otherLog($type, $msg)
    {
        $this->log->setName('other')->add($type, $msg, false);
    }

    /**
     * 执行错误日志
     *
     * @return void
     */
    public function runErrorLog($msg)
    {
        $this->log->setName('runerror')->add('', $msg, false);
    }
}
