#!/bin/bash

# 智能GEO内容系统 - 服务器监控和自动重启脚本
# 作者：姚金刚
# 版本：1.0

PORT=8081
LOG_FILE="server_monitor.log"
PID_FILE="server.pid"

# 日志函数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# 检查服务器是否运行
check_server() {
    if curl -s --connect-timeout 3 "http://localhost:$PORT" > /dev/null 2>&1; then
        return 0  # 服务器运行正常
    else
        return 1  # 服务器无响应
    fi
}

# 启动服务器
start_server() {
    log_message "启动PHP开发服务器..."
    
    # 终止可能存在的旧进程
    if [ -f "$PID_FILE" ]; then
        OLD_PID=$(cat "$PID_FILE")
        if kill -0 "$OLD_PID" 2>/dev/null; then
            log_message "终止旧进程 PID: $OLD_PID"
            kill -TERM "$OLD_PID" 2>/dev/null
            sleep 2
            kill -KILL "$OLD_PID" 2>/dev/null
        fi
        rm -f "$PID_FILE"
    fi
    
    # 清理端口
    lsof -ti :$PORT | xargs kill -9 2>/dev/null
    
    # 启动新服务器
    nohup php -S localhost:$PORT router.php > server.log 2>&1 &
    SERVER_PID=$!
    echo $SERVER_PID > "$PID_FILE"
    
    log_message "服务器已启动，PID: $SERVER_PID"
    
    # 等待服务器启动
    sleep 3
    
    if check_server; then
        log_message "✅ 服务器启动成功: http://localhost:$PORT"
        return 0
    else
        log_message "❌ 服务器启动失败"
        return 1
    fi
}

# 监控模式
monitor_mode() {
    log_message "开始监控模式..."
    
    while true; do
        if check_server; then
            echo -n "."  # 服务器正常，显示一个点
        else
            echo ""
            log_message "⚠️  检测到服务器停止，正在重启..."
            
            if start_server; then
                log_message "✅ 服务器重启成功"
            else
                log_message "❌ 服务器重启失败，等待30秒后重试"
                sleep 30
            fi
        fi
        
        sleep 10  # 每10秒检查一次
    done
}

# 停止服务器
stop_server() {
    log_message "停止服务器..."
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if kill -0 "$PID" 2>/dev/null; then
            kill -TERM "$PID"
            sleep 2
            if kill -0 "$PID" 2>/dev/null; then
                kill -KILL "$PID"
            fi
            log_message "服务器已停止 (PID: $PID)"
        fi
        rm -f "$PID_FILE"
    fi
    
    # 清理端口
    lsof -ti :$PORT | xargs kill -9 2>/dev/null
    log_message "端口 $PORT 已清理"
}

# 显示状态
show_status() {
    echo "=== 智能GEO内容系统服务器状态 ==="
    echo "时间: $(date)"
    echo "端口: $PORT"
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if kill -0 "$PID" 2>/dev/null; then
            echo "进程: 运行中 (PID: $PID)"
        else
            echo "进程: PID文件存在但进程不存在"
        fi
    else
        echo "进程: 无PID文件"
    fi
    
    if check_server; then
        echo "HTTP: ✅ 响应正常"
    else
        echo "HTTP: ❌ 无响应"
    fi
    
    echo "日志文件: $LOG_FILE"
    echo "PID文件: $PID_FILE"
}

# 主程序
case "$1" in
    start)
        start_server
        ;;
    stop)
        stop_server
        ;;
    restart)
        stop_server
        sleep 2
        start_server
        ;;
    status)
        show_status
        ;;
    monitor)
        # 首先启动服务器
        if ! check_server; then
            start_server
        fi
        # 然后进入监控模式
        monitor_mode
        ;;
    *)
        echo "用法: $0 {start|stop|restart|status|monitor}"
        echo ""
        echo "命令说明:"
        echo "  start   - 启动服务器"
        echo "  stop    - 停止服务器"
        echo "  restart - 重启服务器"
        echo "  status  - 显示状态"
        echo "  monitor - 监控模式（自动重启）"
        exit 1
        ;;
esac
