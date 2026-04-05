# NAS_project
> 轻量级私有 NAS 存储系统 | 大文件分片上传 | 无公网 IP 远程访问 | 跨平台兼容

[![GitHub stars](https://img.shields.io/github/stars/keyks/NAS_project?style=flat-square)](https://github.com/keyks/NAS_project)
[![GitHub forks](https://img.shields.io/github/forks/keyks/NAS_project?style=flat-square)](https://github.com/keyks/NAS_project)
[![PHP Version](https://img.shields.io/badge/PHP-7.0%2B-blue?style=flat-square)](https://www.php.net/)
[![Platform](https://img.shields.io/badge/平台-Windows%20%7C%20Linux%20%7C%20Android-green?style=flat-square)](https://github.com/keyks/NAS_project)
[![License](https://img.shields.io/github/license/keyks/NAS_project?style=flat-square)](LICENSE)

---

## 📖 项目介绍
**NAS_project** 是一套基于原生 PHP 开发的轻量私有云存储后端，无需数据库、无需复杂配置，开箱即用。
专为个人/家庭 NAS 场景设计，支持大文件断点续传、文件自动分类、在线预览、搜索管理等完整网盘能力。

最大亮点：
**通过 WSToolbox + Tailscale 实现无视距离、无公网 IP、无端口映射的全球远程访问**，真正做到随时随地访问家中 NAS。

## ✨ 核心功能
### 文件管理
- 支持普通单文件上传 / 大文件分片上传与合并
- 文件秒传校验（文件名+大小快速查重）
- 自动按类型分类：文件、图片、视频、音乐、压缩包、其他
- 文件列表、按日期筛选、关键词搜索
- 文件重命名、删除、在线预览
- 磁盘容量实时统计与展示

### 安全与稳定
- 文件 ID 映射机制，不暴露真实物理路径
- 自动清理 24 小时前过期临时分片
- 目录穿越防护、文件名安全过滤、文件权限控制
- 完整异常捕获与错误处理，上传异常自动清理碎片
- 支持跨域请求，方便前后端分离对接

### 远程访问（核心特色）
- 无需公网 IP
- 无需端口映射
- 无需域名备案
- 无视运营商、地区、网络环境限制
- 点对点加密访问，安全性高

## 🧰 适用场景
- 个人私有网盘 / 家庭 NAS
- 安卓手机搭建本地服务器
- Windows/Linux 小型文件服务
- 无公网 IP 环境下的远程文件管理
- 多设备之间文件互通

## 📁 项目结构
```
NAS_project/
├── api/                     # 接口目录
│   ├── index.php            # 文件管理核心 API（上传、列表、删除、重命名等）
│   └── storage.php          # 磁盘容量统计接口
├── uploads/                 # 上传文件存储目录（程序自动创建）
│   ├── 文件/
│   ├── 图片/
│   ├── 视频/
│   ├── 音乐/
│   ├── 压缩包/
│   ├── 其他/
│   └── temp/                # 分片上传临时目录（自动清理）
└── README.md                # 项目说明文档
```

## 🚀 部署教程

### 1. 环境要求
- PHP ≥ 7.0
- 开启：`fileinfo`、`mbstring`、`json` 扩展
- 磁盘读写权限
- 支持环境：
  - Windows：phpStudy、XAMPP、WAMP、小皮面板
  - Linux：Apache / Nginx + PHP
  - Android：KSWeb、Termux、WebHost 等 PHP 建站工具

### 2. 快速部署
1. 克隆项目
```bash
git clone https://github.com/keyks/NAS_project.git
```

2. 将项目放入网站根目录
例如：
- Windows：`phpstudy_pro/WWW/NAS_project/`
- Linux：`/var/www/html/NAS_project/`
- Android：`/storage/emulated/0/htdocs/NAS_project/`

3. 目录权限（Linux/Android 可选）
```bash
chmod -R 777 uploads/
```

4. 启动服务
访问：
```
http://localhost/NAS_project/api/index.php
```

程序会**自动创建**：
- uploads 分类目录
- temp 分片临时目录
- .file_id_map.json 文件索引

## 🌍 远程访问方案（WSToolbox + Tailscale）
### 实现原理
本项目采用 **Tailscale 点对点组网 + WSToolbox 内网穿透** 实现无距离限制访问：

1. **Tailscale**
- 为所有登录同一账号的设备建立加密虚拟局域网
- 设备之间获得独立内网 IP
- 跨运营商、跨地区、国内外均可直连
- 无需公网 IP、无需路由器设置

2. **WSToolbox**
- 将 NAS 网页服务映射到本地局域网
- 提供稳定、持久的 Web 访问通道
- 配合 Tailscale 实现外网设备直接访问

### 最终效果
- 在家、公司、出差、国外均可访问
- 速度取决于点对点直连质量
- 无流量限制、无带宽阉割
- 真正私有、完全可控

## 📌 API 接口文档
所有接口统一请求：`/api/index.php`
通过 GET/POST 参数 `action` 区分操作。

### 接口列表
| 动作名称         | 功能说明 |
|-----------------|----------|
| listFiles       | 获取文件列表（支持分类、日期、关键词筛选） |
| uploadFile      | 普通文件流式上传 |
| uploadChunk     | 大文件分片上传 |
| checkFileExists | 秒传校验（判断文件是否已存在） |
| mergeChunks     | 合并分片，生成完整文件 |
| viewFile        | 在线预览（文本/图片/音视频地址） |
| renameFile      | 文件重命名 |
| deleteFile      | 删除文件并清理索引 |
| cleanupUpload   | 手动清理分片临时目录 |

### 磁盘容量接口
```
/api/storage.php
```
返回：总容量、已用、剩余、使用率百分比。

## 🔧 管理机制
### 文件 ID 索引系统
- 所有文件通过唯一 16 位 ID 访问
- 真实路径保存在 `uploads/temp/.file_id_map.json`
- 自动清理失效索引
- 重命名/删除自动同步索引

### 自动清理机制
- 每天自动清理 24 小时前未合并的分片目录
- 上传异常自动删除碎片文件
- 删除文件自动清理对应临时目录

## 🧩 前端对接说明
本项目为**纯后端 API**，可对接任何前端：
- Vue/React 管理后台
- Uniapp/H5 移动端
- 原生安卓/iOS 应用
- 自建网页面板

返回格式统一为 JSON：
```json
{
  "success": true,
  "message": "操作成功",
  "files": [...]
}
```

## ⚠️ 安全建议
1. 不要将 API 直接暴露在公网（建议配合 Tailscale 内网访问）
2. 定期备份 uploads 目录
3. 可自行增加接口密钥鉴权
4. 关闭 PHP 错误显示（已默认关闭）

## 📌 常见问题
### 1. 上传失败 / 目录无法创建
检查 PHP 目录权限，确保 `uploads/` 可写。

### 2. 大文件上传失败
修改 `php.ini`：
```
upload_max_filesize = 2048M
post_max_size = 2048M
max_execution_time = 300
```

### 3. Android 环境无法访问存储
授予 APP 存储权限，确保路径指向 `/storage/emulated/0/`。

### 4. 远程访问速度慢
Tailscale 默认自动优选节点，部分网络需要等待 10～30 秒建立直连。

## 📄 开源协议
本项目基于 **MIT License** 开源，可自由使用、修改、分发。

## 🤝 项目地址
GitHub：https://github.com/keyks/NAS_project

---