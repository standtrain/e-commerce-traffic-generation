# 电商引流平台 / E-Commerce Traffic Platform / EC集客プラットフォーム / 전자상거래 트래픽 플랫폼

> **[中文](#中文) | [English](#english) | [日本語](#日本語) | [한국어](#한국어)**

---

## 中文

一个简洁高效的电商商品链接管理与引流工具，基于 PHP + MySQL 构建。支持多平台商品链接聚合展示、AI 智能总结、多语言切换、深色/浅色主题等功能。

### 功能特性

- **多平台商品管理** — 支持淘宝、天猫、京东、闲鱼、亚马逊、拼多多、抖音、唯品会等主流电商平台，也可自定义平台
- **一键获取商品信息** — 粘贴商品链接后自动抓取标题、描述、价格等信息
- **AI 智能总结** — 接入 OpenAI / DeepSeek 等兼容 API，自动生成商品总结、详细介绍和标签
- **多语言支持** — 中文、English、日本語、한국어 四语言切换
- **深色/浅色主题** — 支持手动切换或按时间自动切换
- **图片管理** — 多图上传，自动压缩与缩略图生成，Lightbox 大图预览
- **点击统计** — 记录每个链接的点击次数
- **安全跳转** — 点击购买时弹出安全提醒倒计时，防止钓鱼
- **用户管理** — 管理员与普通用户角色，支持多管理员
- **自定义样式** — 支持自定义 CSS、页脚代码、网站图标、背景（纯色/渐变/图片）
- **联系方式页面** — 展示微信、QQ、邮箱、电话等联系信息
- **CSRF 防护** — 所有表单操作均带 CSRF Token 验证
- **响应式设计** — 适配桌面端与移动端

### 环境要求

- PHP >= 7.4（需要 `pdo_mysql`、`curl`、`gd` 扩展）
- MySQL >= 5.7
- Web 服务器（Apache / Nginx）

### 安装部署

#### 1. 部署文件

将项目文件上传到 Web 服务器根目录或子目录。

#### 2. 运行安装向导

浏览器访问 `http://你的域名/install.php`，按提示填写：

- 数据库主机、名称、用户名、密码
- 网站名称
- 管理员密码（至少 6 位）
- 默认语言

安装程序会自动创建数据库表、写入配置文件 `config.php`。

#### 3. 登录后台

访问 `admin.php`，使用安装时设置的管理员账号密码登录。

#### 4. 开始使用

在后台「链接管理」页面添加商品链接，前台首页即可展示。

### 项目结构

```
├── index.php           # 前台首页（商品链接展示）
├── detail.php          # 商品详情页
├── click.php           # 点击跳转（记录点击后跳转目标 URL）
├── redirect.php        # 跳转记录接口
├── record_click.php    # 点击记录 API
├── contact.php         # 联系我们页面
├── admin.php           # 后台管理（链接、用户、设置）
├── install.php         # 安装向导
├── lang.php            # 多语言翻译文件
├── database.sql        # 数据库初始化脚本（备用）
├── background.png      # 默认背景图
├── assets/
│   └── css/
│       └── style.css   # 全局样式
└── uploads/            # 上传文件目录
    └── .htaccess       # 安全限制（仅允许图片访问）
```

> `config.php` 在安装后自动生成，包含数据库连接配置和核心函数。

### 后台管理

| 功能 | 说明 |
|------|------|
| 链接管理 | 添加/编辑/删除商品链接，支持一键获取 URL 信息、AI 重新生成总结 |
| 用户管理 | 添加/编辑/删除用户，设置管理员或普通用户角色 |
| 网站设置 | 站点名称、描述、图标、背景、主题、字体颜色、联系方式、AI 配置、自定义 CSS 等 |

### AI 配置

在后台「网站设置」→「AI 智能设置」中配置：

- **API URL**：兼容 OpenAI 格式的接口地址
  - OpenAI: `https://api.openai.com/v1/chat/completions`
  - DeepSeek: `https://api.deepseek.com/v1/chat/completions`
  - 智谱 BigModel: `https://open.bigmodel.cn/api/paas/v4/chat/completions`
- **API Key**：对应服务商的密钥
- **模型名称**：如 `gpt-3.5-turbo`、`deepseek-chat` 等

启用后，添加链接时会自动调用 AI 生成商品总结、详细介绍和标签。

### 安全说明

- 上传目录 `uploads/` 通过 `.htaccess` 限制仅允许访问图片文件
- 所有用户输入均经过 `htmlspecialchars` 转义
- 数据库操作使用 PDO 预处理语句，防止 SQL 注入
- 表单操作带 CSRF Token 验证
- 密码使用 `password_hash` 加密存储

### 作者

[standtrain](https://blog.sttr.top)

---

## English

A clean and efficient e-commerce product link management and traffic tool, built with PHP + MySQL. Supports multi-platform product link aggregation, AI-powered summarization, multi-language switching, dark/light themes, and more.

### Features

- **Multi-platform Product Management** — Supports major platforms including Taobao, Tmall, JD, Xianyu, Amazon, Pinduoduo, Douyin, Vipshop, and custom platforms
- **One-click Product Info** — Automatically fetches title, description, and price after pasting a product link
- **AI Smart Summary** — Integrates with OpenAI / DeepSeek compatible APIs to auto-generate product summaries, detailed descriptions, and tags
- **Multi-language Support** — Switch between Chinese, English, Japanese, and Korean
- **Dark/Light Theme** — Manual toggle or automatic switching based on time of day
- **Image Management** — Multi-image upload with auto-compression, thumbnail generation, and Lightbox preview
- **Click Statistics** — Tracks click counts for each link
- **Secure Redirect** — Safety reminder countdown when clicking to buy, preventing phishing
- **User Management** — Admin and regular user roles, supporting multiple admins
- **Custom Styling** — Custom CSS, footer code, favicon, and background (solid/gradient/image)
- **Contact Page** — Display WeChat, QQ, email, phone, and other contact information
- **CSRF Protection** — All form operations include CSRF Token verification
- **Responsive Design** — Optimized for both desktop and mobile

### Requirements

- PHP >= 7.4 (with `pdo_mysql`, `curl`, `gd` extensions)
- MySQL >= 5.7
- Web server (Apache / Nginx)

### Installation

#### 1. Deploy Files

Upload the project files to your web server's root directory or a subdirectory.

#### 2. Run the Installation Wizard

Visit `http://your-domain/install.php` in your browser and fill in:

- Database host, name, username, and password
- Site name
- Admin password (at least 6 characters)
- Default language

The installer will automatically create database tables and write the `config.php` configuration file.

#### 3. Log in to Admin Panel

Visit `admin.php` and log in with the admin credentials set during installation.

#### 4. Start Using

Add product links in the "Link Management" page of the admin panel, and they will be displayed on the homepage.

### Project Structure

```
├── index.php           # Homepage (product link display)
├── detail.php          # Product detail page
├── click.php           # Click redirect (records click then redirects)
├── redirect.php        # Redirect record endpoint
├── record_click.php    # Click recording API
├── contact.php         # Contact us page
├── admin.php           # Admin panel (links, users, settings)
├── install.php         # Installation wizard
├── lang.php            # Multi-language translation file
├── database.sql        # Database initialization script (backup)
├── background.png      # Default background image
├── assets/
│   └── css/
│       └── style.css   # Global styles
└── uploads/            # Upload directory
    └── .htaccess       # Security restriction (image access only)
```

> `config.php` is auto-generated after installation, containing database connection config and core functions.

### Admin Panel

| Feature | Description |
|---------|-------------|
| Link Management | Add/edit/delete product links, one-click URL info fetch, AI re-generation |
| User Management | Add/edit/delete users, set admin or regular user roles |
| Site Settings | Site name, description, icon, background, theme, font color, contact info, AI config, custom CSS, etc. |

### AI Configuration

Configure in Admin Panel → "Site Settings" → "AI Smart Settings":

- **API URL**: OpenAI-compatible endpoint
  - OpenAI: `https://api.openai.com/v1/chat/completions`
  - DeepSeek: `https://api.deepseek.com/v1/chat/completions`
  - Zhipu BigModel: `https://open.bigmodel.cn/api/paas/v4/chat/completions`
- **API Key**: Your service provider's API key
- **Model Name**: e.g. `gpt-3.5-turbo`, `deepseek-chat`, etc.

Once enabled, AI will automatically generate product summaries, detailed descriptions, and tags when adding links.

### Security

- Upload directory `uploads/` is restricted to image files only via `.htaccess`
- All user input is escaped with `htmlspecialchars`
- Database operations use PDO prepared statements to prevent SQL injection
- Form operations include CSRF Token verification
- Passwords are stored using `password_hash`

### Author

[standtrain](https://blog.sttr.top)

---

## 日本語

PHP + MySQL で構築された、シンプルで効率的なEC商品リンク管理・集客ツールです。マルチプラットフォームの商品リンク集約表示、AI要約、多言語切替、ダーク/ライトテーマなどの機能をサポートしています。

### 機能一覧

- **マルチプラットフォーム商品管理** — Taobao、Tmall、JD、Xianyu、Amazon、Pinduoduo、Douyin、Vipshopなどの主要ECプラットフォームに対応、カスタムプラットフォームも可能
- **ワンクリック商品情報取得** — 商品リンクを貼り付けると、タイトル・説明・価格を自動取得
- **AIスマート要約** — OpenAI / DeepSeek 互換APIと連携し、商品要約・詳細説明・タグを自動生成
- **多言語サポート** — 中国語、English、日本語、한국어 の4言語切替対応
- **ダーク/ライトテーマ** — 手動切替または時間帯による自動切替
- **画像管理** — 複数画像アップロード、自動圧縮・サムネイル生成、Lightboxプレビュー
- **クリック統計** — 各リンクのクリック数を記録
- **安全なリダイレクト** — 購入クリック時に安全確認のカウントダウンを表示、フィッシング防止
- **ユーザー管理** — 管理者と一般ユーザーロール、複数管理者に対応
- **カスタムスタイル** — カスタムCSS、フッターコード、ファビコン、背景（単色/グラデーション/画像）設定
- **お問い合わせページ** — WeChat、QQ、メール、電話などの連絡先を表示
- **CSRF対策** — すべてのフォーム操作にCSRFトークン検証を実装
- **レスポンシブデザイン** — デスクトップとモバイル両方に対応

### 動作要件

- PHP >= 7.4（`pdo_mysql`、`curl`、`gd` 拡張が必要）
- MySQL >= 5.7
- Webサーバー（Apache / Nginx）

### インストール

#### 1. ファイルのデプロイ

プロジェクトファイルをWebサーバーのルートディレクトリまたはサブディレクトリにアップロードします。

#### 2. インストールウィザードの実行

ブラウザで `http://your-domain/install.php` にアクセスし、以下の情報を入力：

- データベースホスト、名前、ユーザー名、パスワード
- サイト名
- 管理者パスワード（6文字以上）
- デフォルト言語

インストーラーが自動的にデータベーステーブルを作成し、`config.php` 設定ファイルを書き出します。

#### 3. 管理パネルへのログイン

`admin.php` にアクセスし、インストール時に設定した管理者認証情報でログインします。

#### 4. 利用開始

管理パネルの「リンク管理」ページで商品リンクを追加すると、フロントページに表示されます。

### プロジェクト構成

```
├── index.php           # トップページ（商品リンク表示）
├── detail.php          # 商品詳細ページ
├── click.php           # クックリダイレクト（クリック記録後にリダイレクト）
├── redirect.php        # リダイレクト記録エンドポイント
├── record_click.php    # クリック記録API
├── contact.php         # お問い合わせページ
├── admin.php           # 管理パネル（リンク、ユーザー、設定）
├── install.php         # インストールウィザード
├── lang.php            # 多言語翻訳ファイル
├── database.sql        # データベース初期化スクリプト（バックアップ）
├── background.png      # デフォルト背景画像
├── assets/
│   └── css/
│       └── style.css   # グローバルスタイル
└── uploads/            # アップロードディレクトリ
    └── .htaccess       # セキュリティ制限（画像のみアクセス可）
```

> `config.php` はインストール後に自動生成され、データベース接続設定とコア関数を含みます。

### 管理パネル

| 機能 | 説明 |
|------|------|
| リンク管理 | 商品リンクの追加/編集/削除、ワンクリックURL情報取得、AI再生成 |
| ユーザー管理 | ユーザーの追加/編集/削除、管理者または一般ユーザーロールの設定 |
| サイト設定 | サイト名、説明、アイコン、背景、テーマ、フォントカラー、連絡先、AI設定、カスタムCSSなど |

### AI設定

管理パネル →「サイト設定」→「AIスマート設定」で設定：

- **API URL**：OpenAI互換エンドポイント
  - OpenAI: `https://api.openai.com/v1/chat/completions`
  - DeepSeek: `https://api.deepseek.com/v1/chat/completions`
  - Zhipu BigModel: `https://open.bigmodel.cn/api/paas/v4/chat/completions`
- **API Key**：サービスプロバイダーのAPIキー
- **モデル名**：例：`gpt-3.5-turbo`、`deepseek-chat` など

有効にすると、リンク追加時にAIが商品要約、詳細説明、タグを自動生成します。

### セキュリティ

- アップロードディレクトリ `uploads/` は `.htaccess` により画像ファイルのみアクセス可能に制限
- すべてのユーザー入力は `htmlspecialchars` でエスケープ
- データベース操作はPDOプリペアドステートメントを使用しSQLインジェクションを防止
- フォーム操作にはCSRFトークン検証を実装
- パスワードは `password_hash` で暗号化保存

### 作者

[standtrain](https://blog.sttr.top)

---

## 한국어

PHP + MySQL로 구축된 간결하고 효율적인 전자상거래 상품 링크 관리 및 유입 도구입니다. 다중 플랫폼 상품 링크 집약 표시, AI 요약, 다국어 전환, 다크/라이트 테마 등의 기능을 지원합니다.

### 주요 기능

- **다중 플랫폼 상품 관리** — 타오바오, 티몰, 징둥, 시안위, 아마존, 핀둬둬, 더우인, 웨이푸이후이 등 주요 전자상거래 플랫폼 지원 및 사용자 정의 플랫폼 가능
- **원클릭 상품 정보 가져오기** — 상품 링크를 붙여넣으면 제목, 설명, 가격을 자동으로 가져옴
- **AI 스마트 요약** — OpenAI / DeepSeek 호환 API와 연동하여 상품 요약, 상세 설명, 태그를 자동 생성
- **다국어 지원** — 中文, English, 日本語, 한국어 4개 언어 전환 가능
- **다크/라이트 테마** — 수동 전환 또는 시간대별 자동 전환
- **이미지 관리** — 다중 이미지 업로드, 자동 압축 및 썸네일 생성, Lightbox 미리보기
- **클릭 통계** — 각 링크의 클릭 수 기록
- **안전한 리다이렉트** — 구매 클릭 시 안전 확인 카운트다운 표시, 피싱 방지
- **사용자 관리** — 관리자와 일반 사용자 역할, 다중 관리자 지원
- **사용자 정의 스타일** — 사용자 정의 CSS, 푸터 코드, 파비콘, 배경(단색/그라데이션/이미지) 설정
- **문의 페이지** — WeChat, QQ, 이메일, 전화 등 연락처 표시
- **CSRF 방어** — 모든 폼 작업에 CSRF 토큰 검증 적용
- **반응형 디자인** — 데스크톱 및 모바일 모두 최적화

### 시스템 요구사항

- PHP >= 7.4 (`pdo_mysql`, `curl`, `gd` 확장 필요)
- MySQL >= 5.7
- 웹 서버 (Apache / Nginx)

### 설치 방법

#### 1. 파일 배포

프로젝트 파일을 웹 서버의 루트 디렉토리 또는 하위 디렉토리에 업로드합니다.

#### 2. 설치 마법사 실행

브라우저에서 `http://your-domain/install.php`에 접속하여 다음 정보를 입력:

- 데이터베이스 호스트, 이름, 사용자명, 비밀번호
- 사이트 이름
- 관리자 비밀번호 (6자리 이상)
- 기본 언어

설치 프로그램이 자동으로 데이터베이스 테이블을 생성하고 `config.php` 설정 파일을 작성합니다.

#### 3. 관리 패널 로그인

`admin.php`에 접속하여 설치 시 설정한 관리자 계정으로 로그인합니다.

#### 4. 사용 시작

관리 패널의 "링크 관리" 페이지에서 상품 링크를 추가하면 프론트 페이지에 표시됩니다.

### 프로젝트 구조

```
├── index.php           # 메인 페이지 (상품 링크 표시)
├── detail.php          # 상품 상세 페이지
├── click.php           # 클릭 리다이렉트 (클릭 기록 후 리다이렉트)
├── redirect.php        # 리다이렉트 기록 엔드포인트
├── record_click.php    # 클릭 기록 API
├── contact.php         # 문의 페이지
├── admin.php           # 관리 패널 (링크, 사용자, 설정)
├── install.php         # 설치 마법사
├── lang.php            # 다국어 번역 파일
├── database.sql        # 데이터베이스 초기화 스크립트 (백업)
├── background.png      # 기본 배경 이미지
├── assets/
│   └── css/
│       └── style.css   # 전역 스타일
└── uploads/            # 업로드 디렉토리
    └── .htaccess       # 보안 제한 (이미지 접근만 허용)
```

> `config.php`는 설치 후 자동 생성되며, 데이터베이스 연결 설정과 핵심 함수를 포함합니다.

### 관리 패널

| 기능 | 설명 |
|------|------|
| 링크 관리 | 상품 링크 추가/편집/삭제, 원클릭 URL 정보 가져오기, AI 재생성 |
| 사용자 관리 | 사용자 추가/편집/삭제, 관리자 또는 일반 사용자 역할 설정 |
| 사이트 설정 | 사이트 이름, 설명, 아이콘, 배경, 테마, 글꼴 색상, 연락처, AI 설정, 사용자 정의 CSS 등 |

### AI 설정

관리 패널 → "사이트 설정" → "AI 스마트 설정"에서 구성:

- **API URL**: OpenAI 호환 엔드포인트
  - OpenAI: `https://api.openai.com/v1/chat/completions`
  - DeepSeek: `https://api.deepseek.com/v1/chat/completions`
  - Zhipu BigModel: `https://open.bigmodel.cn/api/paas/v4/chat/completions`
- **API Key**: 서비스 제공업체의 API 키
- **모델 이름**: 예: `gpt-3.5-turbo`, `deepseek-chat` 등

활성화하면 링크 추가 시 AI가 상품 요약, 상세 설명, 태그를 자동 생성합니다.

### 보안

- 업로드 디렉토리 `uploads/`는 `.htaccess`를 통해 이미지 파일만 접근 가능하도록 제한
- 모든 사용자 입력은 `htmlspecialchars`로 이스케이프 처리
- 데이터베이스 작업은 PDO prepared statement를 사용하여 SQL 인젝션 방지
- 폼 작업에는 CSRF 토큰 검증 적용
- 비밀번호는 `password_hash`로 암호화 저장

### 제작자

[standtrain](https://blog.sttr.top)
