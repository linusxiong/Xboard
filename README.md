# Xboard

[English](./README.md) | [简体中文](./README.zh-CN.md)

Xboard is a high-performance proxy service management panel based on secondary development of V2board. It keeps the familiar V2board workflow while improving runtime performance, deployment options, client subscription delivery, and frontend maintainability.

## Disclaimer

This project is developed and maintained for learning and research purposes. No availability, security, or operational guarantee is provided. You are responsible for evaluating, deploying, and operating it in compliance with your local laws and infrastructure requirements.

## Features

- Upgraded to Laravel 10.
- Runs with Adapterman/Webman on Workerman for improved concurrency.
- Docker and Docker Compose deployment support.
- Distributed deployment support.
- Database-backed configuration retrieval.
- Subscription distribution based on user IP location.
- HY2 support.
- sing-box subscription distribution.
- Real visitor IP support behind Cloudflare.
- Automatic protocol distribution based on client version.
- Subscription route filtering, for example `&filter=HongKong|USA`.
- SQLite installation support for lightweight personal deployments.
- User frontend rebuilt with Vue 3, TypeScript, Naive UI, UnoCSS, and Pinia.
- Multiple bug fixes and compatibility improvements.

## Runtime

- PHP 8.1+ for the application; the official Docker image uses PHP 8.3.
- Composer
- MySQL 5.7+ or SQLite
- Redis
- Laravel 10
- Adapterman 0.7+
- Workerman 5.2+
- Nginx
- Supervisor 4.3+ in the Docker image

## Performance

[View the detailed benchmark](./docs/性能对比.md)

Xboard improves performance significantly compared with a traditional PHP-FPM deployment.

| Scenario | php-fpm | php-fpm with opcache | laravels | webman docker |
| --- | ---: | ---: | ---: | ---: |
| Homepage | 6 req/s | 157 req/s | 477 req/s | 803 req/s |
| User subscription | 6 req/s | 196 req/s | 586 req/s | 1064 req/s |
| User homepage latency | 308 ms | 110 ms | 101 ms | 98 ms |

## Screenshot

![Dashboard screenshot](./docs/images/dashboard.png)

## Installation, Update, And Rollback

Choose the guide that matches your deployment method:

- [1Panel deployment](./docs/1panel安装指南.md)
- [Docker Compose command-line quick deployment](./docs/docker-compose安装指南.md)
- [aaPanel + Docker Compose deployment, recommended](./docs/aapanel+docker安装指南.md)
- [aaPanel deployment](./docs/aapanel安装指南.md)

## Migration From Other Versions

Check the corresponding migration guide for your current version:

- [v2board dev version 2023-10-27 migration guide](./docs/v2b_dev迁移指南.md)
- [v2board 1.7.4 migration guide](./docs/v2b_1.7.4迁移指南.md)
- [v2board 1.7.3 migration guide](./docs/v2b_1.7.3迁移指南.md)
- [v2board wyx2685 migration guide](./docs/v2b_wyx2685迁移指南.md)

## Notes

After changing the admin path, restart the service for the change to take effect:

```bash
docker compose restart
```

If you deploy with aaPanel, restart the Webman daemon process from aaPanel as well.
