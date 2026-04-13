<?php
/**
 * 智能GEO内容系统 - AI配置页面兼容入口
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

admin_redirect('ai-configurator.php');
exit;
