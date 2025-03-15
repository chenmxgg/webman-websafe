# webman-websafe

#### 介绍
webman、workerman可用的WEB应用层防火墙

#### 软件架构
PHP开发，配合webman的请求类获取请求数据


#### 使用说明

1.  只能在webman使用
2.  PHP版本>=7.2
3.  在webman的项目根目录中config/middleware.php添加全局中间件
```php
return [
    // 全局中间件
    '' => [
        // ... 这里省略其它中间件
        \Chenm\WebmanWebsafe\Main\Core::class,
    ],
    // ... 这里省略其它中间件
];

```
