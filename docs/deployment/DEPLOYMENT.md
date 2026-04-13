# 智能GEO内容系统 - 服务器部署文档

> **版本**: 1.0  
> **作者**: 姚金刚  
> **更新日期**: 2026-02-03

---

## 📋 目录

- [系统要求](#系统要求)
- [部署前准备](#部署前准备)
- [部署步骤](#部署步骤)
- [配置说明](#配置说明)
- [安全加固](#安全加固)
- [性能优化](#性能优化)
- [监控与维护](#监控与维护)
- [故障排查](#故障排查)
- [备份与恢复](#备份与恢复)

---

## 🖥️ 系统要求

### 最低配置

| 组件 | 要求 |
|------|------|
| **操作系统** | Ubuntu 20.04+ / CentOS 8+ / Debian 11+ |
| **CPU** | 2核心 |
| **内存** | 2GB RAM |
| **硬盘** | 20GB 可用空间 |
| **PHP** | 8.0+ (推荐 8.4+) |
| **Web服务器** | Caddy 2.10+ / Nginx 1.18+ / Apache 2.4+ |

### 推荐配置

| 组件 | 要求 |
|------|------|
| **操作系统** | Ubuntu 22.04 LTS |
| **CPU** | 4核心 |
| **内存** | 4GB RAM |
| **硬盘** | 50GB SSD |
| **PHP** | 8.4.14 |
| **Web服务器** | Caddy 2.10.2 |

### PHP扩展要求

```bash
# 必需扩展
- pdo_sqlite      # SQLite数据库支持
- mbstring        # 多字节字符串处理
- json            # JSON处理
- curl            # HTTP请求（AI API调用）
- openssl         # 加密支持
- fileinfo        # 文件类型检测
- gd / imagick    # 图片处理

# 推荐扩展
- opcache         # 性能优化
- zip             # 压缩支持
- xml             # XML处理
```

---

## 📦 部署前准备

### 1. 服务器准备

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装基础工具
sudo apt install -y git curl wget unzip vim htop
```

### 2. 安装PHP 8.4

```bash
# 添加PHP仓库（Ubuntu）
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# 安装PHP及扩展
sudo apt install -y php8.4-fpm php8.4-cli php8.4-sqlite3 \
    php8.4-mbstring php8.4-curl php8.4-xml php8.4-gd \
    php8.4-zip php8.4-opcache php8.4-fileinfo

# 验证安装
php -v
php -m | grep -E "pdo_sqlite|mbstring|curl"
```

### 3. 安装Web服务器（Caddy）

```bash
# 安装Caddy
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install caddy

# 验证安装
caddy version
```

---

## 🚀 部署步骤

### 步骤1：上传代码

```bash
# 创建项目目录
sudo mkdir -p /var/www/geo-system
sudo chown -R $USER:$USER /var/www/geo-system

# 方式1：使用Git克隆（推荐）
cd /var/www
git clone <your-repository-url> geo-system

# 方式2：使用SCP上传
# 在本地执行：
scp -r /path/to/GEO网站系统/* user@server:/var/www/geo-system/

# 方式3：使用rsync同步
rsync -avz --exclude='logs/*' --exclude='data/db/*.db' \
    /path/to/GEO网站系统/ user@server:/var/www/geo-system/
```

### 步骤2：设置目录权限

```bash
cd /var/www/geo-system

# 设置所有者
sudo chown -R www-data:www-data .

# 设置基础权限
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;

# 设置可写目录权限
sudo chmod -R 775 data/
sudo chmod -R 775 logs/
sudo chmod -R 775 uploads/
sudo chmod -R 775 data/backups/

# 设置脚本执行权限
sudo chmod +x *.sh
```

### 步骤3：配置数据库

```bash
# 创建数据库目录
sudo mkdir -p data/db
sudo chown -R www-data:www-data data/

# 初始化数据库（如果没有现有数据库）
# 访问 http://your-domain.com/install.php 进行初始化

# 或者从备份恢复数据库
sudo cp /path/to/backup/blog.db data/db/blog.db
sudo chown www-data:www-data data/db/blog.db
sudo chmod 664 data/db/blog.db

# 设置SQLite WAL模式（提高并发性能）
sqlite3 data/db/blog.db "PRAGMA journal_mode=WAL;"
```

### 步骤4：配置环境变量

```bash
# 编辑配置文件
sudo vim includes/config.php
```

**重要配置项**：

```php
// 修改网站URL（必须）
define('SITE_URL', 'https://your-domain.com');

// 修改安全密钥（必须）
define('SECRET_KEY', 'your-random-secret-key-here-change-this');

// 修改管理员密码（推荐）
// 生成新密码哈希：
// php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD', '$2y$12$...');

// 数据库路径（检查是否正确）
define('DB_PATH', __DIR__ . '/../data/db/blog.db');
```

### 步骤5：配置Web服务器

#### 方案A：使用Caddy（推荐）

```bash
# 创建Caddyfile
sudo vim /etc/caddy/Caddyfile
```

**Caddyfile配置**：

```caddyfile
# 生产环境配置
your-domain.com {
    # 网站根目录
    root * /var/www/geo-system

    # 启用HTTPS（自动申请Let's Encrypt证书）
    # Caddy会自动处理，无需额外配置

    # 日志配置
    log {
        output file /var/www/geo-system/logs/caddy_access.log {
            roll_size 100mb
            roll_keep 10
        }
        format json
    }

    # 启用压缩
    encode gzip zstd

    # 安全头
    header {
        X-Frame-Options "SAMEORIGIN"
        X-Content-Type-Options "nosniff"
        X-XSS-Protection "1; mode=block"
        Referrer-Policy "strict-origin-when-cross-origin"
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        -Server
    }

    # 隐藏敏感文件
    @hidden {
        path */.*
        path */data/backups/*
        path */logs/*
        path */data/db/*
        path */docs/*
        path */.git/*
        path */install.php
        path */init-db.php
    }
    respond @hidden 404

    # 静态文件缓存
    @static {
        path *.css *.js *.jpg *.jpeg *.png *.gif *.ico *.svg *.woff *.woff2 *.ttf *.eot
    }
    header @static Cache-Control "public, max-age=31536000, immutable"

    # PHP处理
    php_fastcgi unix//run/php/php8.4-fpm.sock {
        read_timeout 300s
        write_timeout 300s
    }

    # 启用文件服务器
    file_server
}
```

```bash
# 重启Caddy
sudo systemctl restart caddy
sudo systemctl enable caddy

# 检查状态
sudo systemctl status caddy
```

#### 方案B：使用Nginx

```bash
# 创建Nginx配置
sudo vim /etc/nginx/sites-available/geo-system
```

**Nginx配置**：

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;

    # 重定向到HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com;

    root /var/www/geo-system;
    index index.php index.html;

    # SSL证书（使用Let's Encrypt）
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    # SSL配置
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # 日志
    access_log /var/www/geo-system/logs/nginx_access.log;
    error_log /var/www/geo-system/logs/nginx_error.log;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # 隐藏敏感文件
    location ~ /\. {
        deny all;
    }

    location ~ ^/(logs|data|docs|\.git) {
        deny all;
    }

    location ~ ^/(install\.php|init-db\.php)$ {
        deny all;
    }

    # PHP处理
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # 静态文件缓存
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # URL重写
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

```bash
# 启用站点
sudo ln -s /etc/nginx/sites-available/geo-system /etc/nginx/sites-enabled/

# 测试配置
sudo nginx -t

# 重启Nginx
sudo systemctl restart nginx
sudo systemctl enable nginx
```

### 步骤6：配置PHP-FPM

```bash
# 编辑PHP-FPM配置
sudo vim /etc/php/8.4/fpm/pool.d/www.conf
```

**关键配置**：

```ini
[www]
user = www-data
group = www-data

listen = /run/php/php8.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; 超时设置
request_terminate_timeout = 300s

; 环境变量
env[PATH] = /usr/local/bin:/usr/bin:/bin
```

```bash
# 编辑PHP配置
sudo vim /etc/php/8.4/fpm/php.ini
```

**关键配置**：

```ini
; 内存限制
memory_limit = 256M

; 上传限制
upload_max_filesize = 10M
post_max_size = 10M

; 执行时间
max_execution_time = 300
max_input_time = 300

; 错误报告（生产环境）
display_errors = Off
log_errors = On
error_log = /var/www/geo-system/logs/php_error.log

; 时区
date.timezone = Asia/Shanghai

; OPcache配置（性能优化）
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
```

```bash
# 重启PHP-FPM
sudo systemctl restart php8.4-fpm
sudo systemctl enable php8.4-fpm

# 检查状态
sudo systemctl status php8.4-fpm
```

### 步骤7：配置定时任务（Cron）

```bash
# 编辑crontab
sudo crontab -e -u www-data
```

**添加以下任务**：

```cron
# 智能GEO内容系统 - 定时任务

# 每5分钟执行一次定时发布任务
*/5 * * * * /usr/bin/php /var/www/geo-system/bin/cron.php >> /var/www/geo-system/logs/cron_$(date +\%Y-\%m-\%d).log 2>&1

# 每小时清理过期session
0 * * * * find /var/www/geo-system/data/sessions -type f -mtime +1 -delete

# 每天凌晨3点备份数据库
0 3 * * * /var/www/geo-system/backup.sh

# 每周日凌晨4点清理旧日志（保留30天）
0 4 * * 0 find /var/www/geo-system/logs -name "*.log" -mtime +30 -delete

# 每天检查系统健康状态
0 */6 * * * /usr/bin/php /var/www/geo-system/bin/health_check_cron.php >> /var/www/geo-system/logs/health_check.log 2>&1
```

### 步骤8：创建备份脚本

```bash
# 创建备份脚本
sudo vim /var/www/geo-system/backup.sh
```

**backup.sh内容**：

```bash
#!/bin/bash

# 智能GEO内容系统 - 备份脚本
# 作者：姚金刚
# 日期：2026-02-03

# 配置
PROJECT_DIR="/var/www/geo-system"
BACKUP_DIR="$PROJECT_DIR/data/backups"
DB_PATH="$PROJECT_DIR/data/db/blog.db"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="geo_backup_$DATE"

# 创建备份目录
mkdir -p "$BACKUP_DIR"

# 备份数据库
echo "开始备份数据库..."
sqlite3 "$DB_PATH" ".backup '$BACKUP_DIR/${BACKUP_NAME}.db'"

# 备份上传文件
echo "开始备份上传文件..."
tar -czf "$BACKUP_DIR/${BACKUP_NAME}_uploads.tar.gz" -C "$PROJECT_DIR" uploads/

# 备份配置文件
echo "开始备份配置文件..."
tar -czf "$BACKUP_DIR/${BACKUP_NAME}_config.tar.gz" -C "$PROJECT_DIR" includes/config.php

# 创建完整备份（可选）
echo "创建完整备份..."
tar -czf "$BACKUP_DIR/${BACKUP_NAME}_full.tar.gz" \
    --exclude='logs/*' \
    --exclude='data/backups/*' \
    --exclude='data/db/*.db-shm' \
    --exclude='data/db/*.db-wal' \
    -C "$PROJECT_DIR" .

# 删除30天前的备份
echo "清理旧备份..."
find "$BACKUP_DIR" -name "geo_backup_*.db" -mtime +30 -delete
find "$BACKUP_DIR" -name "geo_backup_*.tar.gz" -mtime +30 -delete

echo "备份完成：$BACKUP_NAME"
```

```bash
# 设置执行权限
sudo chmod +x /var/www/geo-system/backup.sh

# 测试备份
sudo -u www-data /var/www/geo-system/backup.sh
```

### 步骤9：配置防火墙

```bash
# 使用UFW（Ubuntu）
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable

# 检查状态
sudo ufw status
```

### 步骤10：验证部署

```bash
# 检查文件权限
ls -la /var/www/geo-system/data/db/

# 检查PHP配置
php -i | grep -E "pdo_sqlite|memory_limit|upload_max"

# 检查Web服务器状态
sudo systemctl status caddy  # 或 nginx

# 检查PHP-FPM状态
sudo systemctl status php8.4-fpm

# 测试数据库连接
php -r "
\$db = new PDO('sqlite:/var/www/geo-system/data/db/blog.db');
echo 'Database connection: OK\n';
"

# 访问网站
curl -I https://your-domain.com
```

---

## ⚙️ 配置说明

### 环境变量配置

编辑 `includes/config.php`：

```php
// 网站配置
define('SITE_NAME', '智能GEO内容系统');
define('SITE_URL', 'https://your-domain.com');  // ⚠️ 必须修改

// 安全配置
define('SECRET_KEY', 'your-random-secret-key');  // ⚠️ 必须修改
define('SESSION_TIMEOUT', 3600);                 // Session超时时间（秒）
define('MAX_LOGIN_ATTEMPTS', 5);                 // 最大登录尝试次数
define('LOGIN_LOCKOUT_TIME', 900);               // 登录锁定时间（秒）

// 上传配置
define('MAX_FILE_SIZE', 10 * 1024 * 1024);       // 最大上传文件大小（10MB）

// AI配置（在管理后台配置）
// 访问：https://your-domain.com/geo_admin/ai-models.php
```

### 数据库配置

SQLite数据库位于：`data/db/blog.db`

**优化配置**：

```bash
# 启用WAL模式（提高并发性能）
sqlite3 data/db/blog.db "PRAGMA journal_mode=WAL;"

# 设置缓存大小（提高查询性能）
sqlite3 data/db/blog.db "PRAGMA cache_size=10000;"

# 启用外键约束
sqlite3 data/db/blog.db "PRAGMA foreign_keys=ON;"
```

### AI模型配置

1. 登录管理后台：`https://your-domain.com/geo_admin/`
2. 进入"AI模型配置"：`/geo_admin/ai-models.php`
3. 添加AI模型：
   - 模型名称：Claude Sonnet 4
   - 模型ID：claude-sonnet-4-20250514
   - API密钥：<YOUR_API_KEY>
   - API地址：https://api.anthropic.com
   - 每日限制：0（无限制）

---

## 🔒 安全加固

### 1. 文件权限加固

```bash
# 设置严格的文件权限
cd /var/www/geo-system

# 配置文件只读
sudo chmod 640 includes/config.php
sudo chown root:www-data includes/config.php

# 数据库文件保护
sudo chmod 660 data/db/blog.db
sudo chown www-data:www-data data/db/blog.db

# 禁止执行上传目录中的PHP文件
sudo chmod 755 uploads/
sudo find uploads/ -type f -name "*.php" -delete
```

### 2. 禁用危险文件访问

在Web服务器配置中添加：

```nginx
# Nginx示例
location ~ ^/(install\.php|init-db\.php|test.*\.php|backup\.sh)$ {
    deny all;
}

location ~ /\.(git|env|htaccess) {
    deny all;
}
```

### 3. 启用HTTPS

```bash
# 使用Caddy（自动HTTPS）
# Caddy会自动申请和续期Let's Encrypt证书

# 使用Certbot（Nginx/Apache）
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
sudo certbot renew --dry-run  # 测试自动续期
```

### 4. 配置安全头

已在Web服务器配置中包含：
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Strict-Transport-Security`
- `Referrer-Policy`

### 5. 修改默认管理员密码

```bash
# 生成新密码哈希
php -r "echo password_hash('your-new-strong-password', PASSWORD_DEFAULT);"

# 编辑配置文件
sudo vim includes/config.php

# 修改ADMIN_PASSWORD为新生成的哈希值
```

### 6. 配置IP白名单（可选）

```nginx
# Nginx示例 - 限制管理后台访问
location /geo_admin/ {
    allow 1.2.3.4;      # 允许的IP
    allow 5.6.7.8;
    deny all;

    # ... 其他配置
}
```

### 7. 启用日志审计

```bash
# 创建日志目录
sudo mkdir -p /var/www/geo-system/logs

# 设置日志权限
sudo chown -R www-data:www-data /var/www/geo-system/logs
sudo chmod 755 /var/www/geo-system/logs

# 配置日志轮转
sudo vim /etc/logrotate.d/geo-system
```

**logrotate配置**：

```
/var/www/geo-system/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.4-fpm > /dev/null 2>&1 || true
    endscript
}
```

---

## ⚡ 性能优化

### 1. PHP OPcache优化

已在 `/etc/php/8.4/fpm/php.ini` 中配置：

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=0
```

### 2. SQLite优化

```bash
# 定期优化数据库
sqlite3 data/db/blog.db "VACUUM;"
sqlite3 data/db/blog.db "ANALYZE;"

# 创建优化脚本
cat > /var/www/geo-system/optimize_db.sh << 'EOF'
#!/bin/bash
DB_PATH="/var/www/geo-system/data/db/blog.db"
echo "优化数据库..."
sqlite3 "$DB_PATH" "VACUUM;"
sqlite3 "$DB_PATH" "ANALYZE;"
echo "优化完成"
EOF

chmod +x /var/www/geo-system/optimize_db.sh

# 添加到crontab（每周执行）
# 0 2 * * 0 /var/www/geo-system/optimize_db.sh
```

### 3. 静态资源CDN（可选）

```php
// 在 includes/config.php 中添加
define('CDN_URL', 'https://cdn.your-domain.com');
define('USE_CDN', true);
```

### 4. 启用Gzip压缩

已在Web服务器配置中启用（Caddy/Nginx）。

### 5. 浏览器缓存

已在Web服务器配置中设置静态资源缓存：
- CSS/JS/图片：1年缓存
- HTML：不缓存

---

## 📊 监控与维护

### 1. 系统监控

```bash
# 安装监控工具
sudo apt install htop iotop nethogs

# 实时监控
htop                    # CPU和内存
iotop                   # 磁盘I/O
nethogs                 # 网络流量
```

### 2. 日志监控

```bash
# 实时查看访问日志
tail -f /var/www/geo-system/logs/caddy_access.log

# 实时查看错误日志
tail -f /var/www/geo-system/logs/php_error.log

# 查看PHP-FPM日志
sudo tail -f /var/log/php8.4-fpm.log

# 统计访问量
cat /var/www/geo-system/logs/caddy_access.log | wc -l
```

### 3. 数据库监控

```bash
# 检查数据库大小
du -h /var/www/geo-system/data/db/blog.db

# 检查表统计
sqlite3 /var/www/geo-system/data/db/blog.db << EOF
SELECT name, COUNT(*) FROM (
    SELECT 'articles' as name FROM articles
    UNION ALL SELECT 'tasks' FROM tasks
    UNION ALL SELECT 'title_libraries' FROM title_libraries
);
EOF
```

### 4. 性能监控

```bash
# 创建性能监控脚本
cat > /var/www/geo-system/monitor.sh << 'EOF'
#!/bin/bash
echo "=== 系统性能监控 ==="
echo "时间: $(date)"
echo ""
echo "CPU使用率:"
top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1"%"}'
echo ""
echo "内存使用:"
free -h | grep Mem | awk '{print "使用: "$3" / "$2" ("$3/$2*100"%)"}'
echo ""
echo "磁盘使用:"
df -h /var/www/geo-system | tail -1 | awk '{print "使用: "$3" / "$2" ("$5")"}'
echo ""
echo "数据库大小:"
du -h /var/www/geo-system/data/db/blog.db
echo ""
echo "PHP-FPM进程:"
ps aux | grep php-fpm | wc -l
echo ""
EOF

chmod +x /var/www/geo-system/monitor.sh
```

### 5. 健康检查

```bash
# 创建健康检查脚本
cat > /var/www/geo-system/health_check.sh << 'EOF'
#!/bin/bash

# 检查Web服务器
if systemctl is-active --quiet caddy; then
    echo "✅ Caddy: 运行中"
else
    echo "❌ Caddy: 已停止"
    sudo systemctl start caddy
fi

# 检查PHP-FPM
if systemctl is-active --quiet php8.4-fpm; then
    echo "✅ PHP-FPM: 运行中"
else
    echo "❌ PHP-FPM: 已停止"
    sudo systemctl start php8.4-fpm
fi

# 检查数据库
if [ -f "/var/www/geo-system/data/db/blog.db" ]; then
    echo "✅ 数据库: 存在"
else
    echo "❌ 数据库: 不存在"
fi

# 检查磁盘空间
DISK_USAGE=$(df /var/www/geo-system | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt 80 ]; then
    echo "✅ 磁盘空间: ${DISK_USAGE}%"
else
    echo "⚠️  磁盘空间: ${DISK_USAGE}% (警告)"
fi

# 检查网站可访问性
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://your-domain.com)
if [ $HTTP_CODE -eq 200 ]; then
    echo "✅ 网站: 可访问 (HTTP $HTTP_CODE)"
else
    echo "❌ 网站: 不可访问 (HTTP $HTTP_CODE)"
fi
EOF

chmod +x /var/www/geo-system/health_check.sh

# 添加到crontab（每小时执行）
# 0 * * * * /var/www/geo-system/health_check.sh >> /var/www/geo-system/logs/health_check.log 2>&1
```

---

## 🔧 故障排查

### 常见问题1：500内部服务器错误

**症状**：访问网站显示500错误

**排查步骤**：

```bash
# 1. 检查PHP错误日志
sudo tail -50 /var/www/geo-system/logs/php_error.log

# 2. 检查Web服务器错误日志
sudo tail -50 /var/www/geo-system/logs/caddy_error.log

# 3. 检查文件权限
ls -la /var/www/geo-system/data/db/

# 4. 检查PHP-FPM状态
sudo systemctl status php8.4-fpm

# 5. 测试PHP配置
php -l /var/www/geo-system/index.php
```

**常见原因**：
- 数据库文件权限不正确
- PHP扩展未安装
- 配置文件语法错误
- PHP-FPM未运行

### 常见问题2：数据库锁定

**症状**：提示"database is locked"

**解决方案**：

```bash
# 1. 检查数据库文件权限
ls -la /var/www/geo-system/data/db/

# 2. 确保WAL模式已启用
sqlite3 /var/www/geo-system/data/db/blog.db "PRAGMA journal_mode;"

# 3. 如果不是WAL，启用它
sqlite3 /var/www/geo-system/data/db/blog.db "PRAGMA journal_mode=WAL;"

# 4. 检查是否有僵尸进程
ps aux | grep php

# 5. 重启PHP-FPM
sudo systemctl restart php8.4-fpm
```

### 常见问题3：批量任务无法执行

**症状**：点击"开始批量执行"后任务不运行

**排查步骤**：

```bash
# 1. 检查批量执行日志
tail -f /var/www/geo-system/logs/batch_*.log

# 2. 检查进程是否在运行
ps aux | grep batch_execute_task.php

# 3. 检查PHP CLI配置
php -i | grep memory_limit

# 4. 手动测试批量执行
cd /var/www/geo-system
php bin/batch_execute_task.php 23  # 23是任务ID

# 5. 检查AI API配置
# 访问：https://your-domain.com/geo_admin/ai-models.php
```

### 常见问题4：上传文件失败

**症状**：无法上传图片或文件

**解决方案**：

```bash
# 1. 检查上传目录权限
ls -la /var/www/geo-system/uploads/

# 2. 设置正确权限
sudo chown -R www-data:www-data /var/www/geo-system/uploads/
sudo chmod -R 775 /var/www/geo-system/uploads/

# 3. 检查PHP上传配置
php -i | grep -E "upload_max_filesize|post_max_size"

# 4. 检查磁盘空间
df -h /var/www/geo-system
```

### 常见问题5：定时任务不执行

**症状**：文章不自动发布

**排查步骤**：

```bash
# 1. 检查crontab配置
sudo crontab -l -u www-data

# 2. 检查cron日志
tail -f /var/www/geo-system/logs/cron_*.log

# 3. 手动执行cron脚本
sudo -u www-data php /var/www/geo-system/bin/cron.php

# 4. 检查cron服务状态
sudo systemctl status cron

# 5. 重启cron服务
sudo systemctl restart cron
```

### 常见问题6：HTTPS证书问题

**症状**：浏览器显示证书错误

**解决方案**：

```bash
# Caddy自动续期（通常无需手动操作）
sudo systemctl restart caddy

# Certbot手动续期
sudo certbot renew

# 检查证书有效期
sudo certbot certificates

# 强制续期
sudo certbot renew --force-renewal
```

---

## 💾 备份与恢复

### 备份策略

#### 1. 自动备份（推荐）

已在步骤8中配置，包括：
- 每天凌晨3点自动备份
- 保留30天的备份历史
- 备份内容：数据库、上传文件、配置文件

#### 2. 手动备份

```bash
# 完整备份
cd /var/www/geo-system
sudo -u www-data ./backup.sh

# 仅备份数据库
sqlite3 data/db/blog.db ".backup 'data/backups/manual_backup_$(date +%Y%m%d).db'"

# 仅备份上传文件
tar -czf data/backups/uploads_$(date +%Y%m%d).tar.gz uploads/
```

#### 3. 远程备份（推荐）

```bash
# 使用rsync同步到远程服务器
rsync -avz --delete \
    /var/www/geo-system/data/backups/ \
    user@backup-server:/backups/geo-system/

# 使用rclone同步到云存储（如阿里云OSS、AWS S3）
rclone sync /var/www/geo-system/data/backups/ remote:geo-backups/
```

### 恢复数据

#### 1. 恢复数据库

```bash
# 停止Web服务器
sudo systemctl stop caddy

# 备份当前数据库（以防万一）
cp data/db/blog.db data/db/blog.db.before_restore

# 恢复数据库
cp data/backups/geo_backup_20260203_030000.db data/db/blog.db

# 设置权限
sudo chown www-data:www-data data/db/blog.db
sudo chmod 664 data/db/blog.db

# 启用WAL模式
sqlite3 data/db/blog.db "PRAGMA journal_mode=WAL;"

# 启动Web服务器
sudo systemctl start caddy
```

#### 2. 恢复上传文件

```bash
# 解压备份
tar -xzf data/backups/geo_backup_20260203_030000_uploads.tar.gz -C /var/www/geo-system/

# 设置权限
sudo chown -R www-data:www-data uploads/
sudo chmod -R 775 uploads/
```

#### 3. 完整恢复

```bash
# 停止服务
sudo systemctl stop caddy
sudo systemctl stop php8.4-fpm

# 清空当前目录（谨慎操作！）
cd /var/www
sudo mv geo-system geo-system.old

# 解压完整备份
sudo mkdir geo-system
sudo tar -xzf geo-system.old/data/backups/geo_backup_20260203_030000_full.tar.gz -C geo-system/

# 恢复权限
sudo chown -R www-data:www-data geo-system/
cd geo-system
sudo chmod -R 755 .
sudo chmod -R 775 data/ logs/ uploads/

# 启动服务
sudo systemctl start php8.4-fpm
sudo systemctl start caddy

# 验证恢复
curl -I https://your-domain.com
```

---

## 📝 部署检查清单

### 部署前检查

- [ ] 服务器满足最低配置要求
- [ ] PHP 8.0+ 已安装并配置
- [ ] 所有必需的PHP扩展已安装
- [ ] Web服务器（Caddy/Nginx）已安装
- [ ] 域名DNS已正确配置
- [ ] SSL证书已准备（或使用Let's Encrypt）

### 部署中检查

- [ ] 代码已上传到服务器
- [ ] 文件权限已正确设置
- [ ] 数据库已初始化或恢复
- [ ] 配置文件已修改（SITE_URL、SECRET_KEY等）
- [ ] Web服务器配置已完成
- [ ] PHP-FPM配置已优化
- [ ] 定时任务已配置
- [ ] 备份脚本已创建并测试

### 部署后检查

- [ ] 网站可以正常访问
- [ ] HTTPS证书正常工作
- [ ] 管理后台可以登录
- [ ] 数据库连接正常
- [ ] 文件上传功能正常
- [ ] AI模型配置正确
- [ ] 批量任务可以执行
- [ ] 定时任务正常运行
- [ ] 日志正常记录
- [ ] 备份脚本正常执行

### 安全检查

- [ ] 默认管理员密码已修改
- [ ] SECRET_KEY已修改为随机值
- [ ] 敏感文件无法直接访问（install.php等）
- [ ] 数据库文件无法直接下载
- [ ] 安全头已配置
- [ ] 防火墙已配置
- [ ] 日志审计已启用
- [ ] 备份已加密（如需要）

---

## 🚀 快速部署命令（一键脚本）

创建一键部署脚本：

```bash
cat > /tmp/deploy_geo_system.sh << 'EOF'
#!/bin/bash

# 智能GEO内容系统 - 一键部署脚本
# 适用于Ubuntu 22.04 LTS

set -e

echo "🚀 开始部署智能GEO内容系统..."

# 配置变量
DOMAIN="your-domain.com"
PROJECT_DIR="/var/www/geo-system"
DB_PASSWORD=$(openssl rand -base64 32)
SECRET_KEY=$(openssl rand -base64 32)

# 1. 更新系统
echo "📦 更新系统..."
sudo apt update && sudo apt upgrade -y

# 2. 安装PHP
echo "📦 安装PHP 8.4..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-sqlite3 \
    php8.4-mbstring php8.4-curl php8.4-xml php8.4-gd \
    php8.4-zip php8.4-opcache php8.4-fileinfo

# 3. 安装Caddy
echo "📦 安装Caddy..."
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install caddy

# 4. 创建项目目录
echo "📁 创建项目目录..."
sudo mkdir -p $PROJECT_DIR
sudo chown -R $USER:$USER $PROJECT_DIR

# 5. 克隆代码（需要替换为实际的仓库地址）
echo "📥 下载代码..."
# git clone <your-repo-url> $PROJECT_DIR
# 或者手动上传代码

# 6. 设置权限
echo "🔐 设置权限..."
cd $PROJECT_DIR
sudo chown -R www-data:www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;
sudo chmod -R 775 data/ logs/ uploads/
sudo chmod +x *.sh

# 7. 配置数据库
echo "💾 配置数据库..."
sudo mkdir -p data/db
sqlite3 data/db/blog.db "PRAGMA journal_mode=WAL;"
sudo chown -R www-data:www-data data/

# 8. 配置Caddy
echo "⚙️  配置Caddy..."
sudo tee /etc/caddy/Caddyfile > /dev/null << CADDY
$DOMAIN {
    root * $PROJECT_DIR
    encode gzip zstd
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
}
CADDY

# 9. 重启服务
echo "🔄 重启服务..."
sudo systemctl restart php8.4-fpm
sudo systemctl restart caddy
sudo systemctl enable php8.4-fpm
sudo systemctl enable caddy

# 10. 配置防火墙
echo "🔥 配置防火墙..."
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

echo "✅ 部署完成！"
echo ""
echo "📝 下一步操作："
echo "1. 访问 https://$DOMAIN/install.php 初始化数据库"
echo "2. 修改 includes/config.php 中的配置"
echo "3. 修改默认管理员密码"
echo "4. 配置AI模型"
echo ""
echo "🔑 生成的密钥（请保存）："
echo "SECRET_KEY: $SECRET_KEY"
EOF

chmod +x /tmp/deploy_geo_system.sh
```

**使用方法**：

```bash
# 1. 编辑脚本，修改DOMAIN变量
vim /tmp/deploy_geo_system.sh

# 2. 执行部署
sudo bash /tmp/deploy_geo_system.sh
```

---

## 📞 技术支持

### 文档资源

- [系统说明文档](docs/系统说明文档.md)
- [快速参考](docs/快速参考.md)
- [本地环境配置指南](docs/本地环境配置指南.md)

### 日志位置

- **访问日志**: `/var/www/geo-system/logs/caddy_access.log`
- **错误日志**: `/var/www/geo-system/logs/php_error.log`
- **批量任务日志**: `/var/www/geo-system/logs/batch_*.log`
- **定时任务日志**: `/var/www/geo-system/logs/cron_*.log`

### 常用命令

```bash
# 查看服务状态
sudo systemctl status caddy
sudo systemctl status php8.4-fpm

# 重启服务
sudo systemctl restart caddy
sudo systemctl restart php8.4-fpm

# 查看日志
tail -f /var/www/geo-system/logs/php_error.log

# 备份数据
sudo -u www-data /var/www/geo-system/backup.sh

# 优化数据库
sqlite3 /var/www/geo-system/data/db/blog.db "VACUUM; ANALYZE;"

# 检查系统健康
/var/www/geo-system/health_check.sh
```

---

## 📄 许可证

本系统为商业软件，版权所有 © 2026 姚金刚

---

## 📅 更新日志

### v1.0 (2026-02-03)
- ✅ 初始部署文档
- ✅ 完整的部署步骤
- ✅ 安全加固指南
- ✅ 性能优化建议
- ✅ 监控与维护方案
- ✅ 故障排查指南
- ✅ 备份与恢复流程

---

**部署完成后，请务必：**
1. ✅ 修改默认管理员密码
2. ✅ 修改SECRET_KEY
3. ✅ 配置AI模型
4. ✅ 测试备份脚本
5. ✅ 配置监控告警

**祝您部署顺利！** 🎉
