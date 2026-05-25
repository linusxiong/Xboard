# Xboard

[English](./README.md) | [简体中文](./README.zh-CN.md)

Xboard 是基于 V2board 二次开发的高性能代理服务管理面板。它保留了 V2board 熟悉的使用流程，并在运行性能、部署方式、客户端订阅分发和前端维护性上做了增强。

## 免责声明

本项目仅用于学习和研究目的，由维护者个人开发和维护。不承诺可用性、安全性或运维结果。部署和使用前，请自行评估风险，并确保符合所在地法律法规和基础设施要求。

## 功能特性

- 升级到 Laravel 10。
- 使用 Adapterman/Webman 基于 Workerman 运行，提升并发性能。
- 支持 Docker 和 Docker Compose 部署。
- 支持分布式部署。
- 配置读取改为基于数据库。
- 支持根据用户 IP 所在地区分发订阅。
- 支持 HY2。
- 支持 sing-box 订阅分发。
- 支持在 Cloudflare 后直接获取真实访客 IP。
- 支持根据客户端版本自动分发新协议。
- 支持订阅线路过滤，例如在订阅地址后追加 `&filter=HongKong|USA`。
- 支持 SQLite 安装，适合轻量个人部署。
- 用户前端使用 Vue 3、TypeScript、Naive UI、UnoCSS、Pinia 重构。
- 修复了多个兼容性和稳定性问题。

## 运行环境

- 应用支持 PHP 8.1+；官方 Docker 镜像使用 PHP 8.3。
- Composer
- MySQL 5.7+ 或 SQLite
- Redis
- Laravel 10
- Adapterman 0.7+
- Workerman 5.2+
- Nginx
- Docker 镜像内使用 Supervisor 4.3+

## 性能表现

[查看详细性能对比](./docs/性能对比.md)

相较传统 PHP-FPM 部署方式，Xboard 在前后端请求性能上有明显提升。

| 场景 | php-fpm | php-fpm 开启 opcache | laravels | webman docker |
| --- | ---: | ---: | ---: | ---: |
| 首页 | 6 req/s | 157 req/s | 477 req/s | 803 req/s |
| 用户订阅 | 6 req/s | 196 req/s | 586 req/s | 1064 req/s |
| 用户首页延迟 | 308 ms | 110 ms | 101 ms | 98 ms |

## 页面预览

![仪表盘截图](./docs/images/dashboard.png)

## 安装、更新与回滚

请根据你的部署方式选择对应文档：

- [1Panel 部署](./docs/1panel安装指南.md)
- [Docker Compose 命令行快速部署](./docs/docker-compose安装指南.md)
- [aaPanel + Docker Compose 部署，推荐](./docs/aapanel+docker安装指南.md)
- [aaPanel 部署](./docs/aapanel安装指南.md)

## 从其他版本迁移

请根据当前使用的版本查看对应迁移文档：

- [v2board dev 2023-10-27 迁移指南](./docs/v2b_dev迁移指南.md)
- [v2board 1.7.4 迁移指南](./docs/v2b_1.7.4迁移指南.md)
- [v2board 1.7.3 迁移指南](./docs/v2b_1.7.3迁移指南.md)
- [v2board wyx2685 迁移指南](./docs/v2b_wyx2685迁移指南.md)

## 注意事项

修改管理员路径后，需要重启服务才能生效：

```bash
docker compose restart
```

如果使用 aaPanel 部署，也需要在 aaPanel 中重启 Webman 守护进程。
