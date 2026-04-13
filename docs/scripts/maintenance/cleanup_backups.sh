#!/bin/bash
# 清理session修复产生的备份文件

cd "$(dirname "$0")/admin"

echo "准备清理备份文件..."
echo ""

backup_count=$(ls -1 *.bak 2>/dev/null | wc -l)

if [ "$backup_count" -eq 0 ]; then
    echo "没有找到备份文件"
    exit 0
fi

echo "找到 $backup_count 个备份文件"
echo ""
echo "备份文件列表:"
ls -1 *.bak
echo ""

read -p "确定要删除这些备份文件吗？(y/N) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm *.bak
    echo "✅ 已删除所有备份文件"
else
    echo "❌ 取消删除"
fi

