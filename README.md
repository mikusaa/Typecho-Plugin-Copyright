# Copyright for Typecho

`Copyright` 是一个用于 Typecho 的版权声明插件，可在文章或独立页面正文末尾输出统一的版权说明区块。

当前版本以 Typecho `1.3` 为优先适配目标，同时兼容 Typecho `1.2`。插件聚焦于版权声明主功能，并为后续扩展不同样式、CC 协议组合等能力预留了结构。

## 功能特性

- 为文章与独立页面输出版权声明区块
- 支持插件级全局默认设置
- 支持单篇文章或页面覆盖全局设置
- 默认自动读取当前内容作者
- 填写原文链接后显示“原文作者”输入框，便于补充转载来源
- 版权声明与原文作者支持 Markdown
- 兼容旧版配置键与旧版自定义字段

## 下载方式

### 方式一：下载 ZIP

1. 打开仓库：`https://github.com/mikusaa/Typecho-Plugin-Copyright`
2. 下载项目 ZIP 压缩包
3. 解压后将目录重命名为 `Copyright`

### 方式二：使用 Git 克隆

```bash
git clone https://github.com/mikusaa/Typecho-Plugin-Copyright.git Copyright
```

## 安装方法

1. 将插件目录放到 Typecho 站点的 `usr/plugins/` 下
2. 确认最终目录为 `usr/plugins/Copyright`
3. 登录 Typecho 后台，进入“控制台 -> 插件”
4. 找到 `Copyright` 并启用

## 全局设置

插件启用后，可在后台插件设置页配置以下内容：

- 默认版权声明：未在单篇内容中单独填写时使用，支持 Markdown
- 默认显示本文链接：在未填写原文链接时，是否显示当前内容固定链接
- 文章默认显示版权声明：控制文章默认是否输出版权区块
- 独立页面默认显示版权声明：控制独立页面默认是否输出版权区块

## 单篇设置

插件会在编辑器中注册默认自定义字段，无需手动输入字段名。相关字段会集中显示在“版权声明设置”面板中，并默认折叠。

| 字段 | 说明 |
| --- | --- |
| `copyrightMode` | 当前内容的显示策略：跟随全局 / 本篇启用 / 本篇关闭 |
| `copyrightSourceUrl` | 原文链接；填写后前台显示为“原文链接”，需填写合法 URL，不支持 Markdown |
| `copyrightAuthor` | 原文作者；仅在填写原文链接后显示，用于补充转载来源，支持 Markdown |
| `copyrightNotice` | 当前内容的版权声明，支持 Markdown |

## 显示规则

1. 单篇设置优先级高于插件全局设置
2. 默认作者信息自动读取当前内容作者
3. 填写原文链接后，可额外填写原文作者；填写后将优先作为署名信息输出
4. 未填写原文链接时，如已开启“默认显示本文链接”，则显示当前内容固定链接
5. 当前内容未填写版权声明时，回退到插件全局默认版权声明
6. Markdown 仅用于原文作者和版权声明；原文链接需直接填写 URL

## 兼容说明

- 旧版本使用的 `switch`、`author`、`url`、`notice` 字段仍会被兼容读取
- 历史版本中已保存的 `copyrightAuthor` 字段仍会继续兼容
- 旧版本插件配置中的 `showOnPost`、`showOnPage`、`showURL`、`notice` 仍会被兼容读取
- 编辑器中保存一次后，旧字段会逐步迁移到新的字段命名

## 项目结构

- `Plugin.php`：插件注册、配置表单、编辑器字段注入、前台渲染与兼容逻辑
- `Action.php`：插件扩展入口，当前提供 `/action/copyright?schema=1` 作为编辑器 schema 输出接口
- `assets/admin/editor.js`：后台编辑器面板交互逻辑
- `assets/admin/editor.css`：后台编辑器面板样式

## 后续规划

- 增加更多版权声明样式
- 提供不同版权模板
- 支持 CC 协议组合选择
