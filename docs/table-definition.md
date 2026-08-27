# 欧州サッカー観戦予約アプリ テーブル定義書

## 1. 概要

欧州サッカーの試合情報を Football-Data.org API から取得し、
試合を放映するスポーツバーの観戦席を予約できるWebアプリケーション。

- Framework: Laravel
- Database: MySQL
- Frontend: Blade / JavaScript
- Football API: Football-Data.org
- Map API: Google Maps
- DB上の日時カラム: 全てUTCで保存する（試合日時に限らず、予約受付期間・予約日時なども含む）
- 画面表示日時: JST（Asia/Tokyo）に変換して表示する
- 金額: 日本円（整数）

---

## 2. テーブル一覧

| テーブル | 用途 |
|---|---|
| users | ユーザー情報 |
| competitions | 大会情報 |
| teams | チーム情報 |
| football_matches | 試合情報 |
| shops | スポーツバー店舗情報 |
| seat_types | 店舗の座席種別 |
| broadcasts | 試合の放映情報 |
| broadcast_seat_types | 放映ごとの座席・価格・在庫情報 |
| reservations | 予約情報 |
| favorite_teams | お気に入りチーム |

---

# 3. users

ユーザー情報を管理する。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| name | VARCHAR | ユーザー名 |
| email | VARCHAR | UNIQUE |
| email_verified_at | TIMESTAMP | NULL可 |
| password | VARCHAR | パスワード |
| date_of_birth | DATE | 必須・生年月日 |
| role | VARCHAR | user / shop_owner / admin |
| remember_token | VARCHAR | NULL可 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

# 4. competitions

Football-Data.orgから取得する大会情報。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| external_competition_id | BIGINT | API側大会ID・UNIQUE |
| name | VARCHAR | 大会名 |
| code | VARCHAR | 大会コード |
| type | VARCHAR | 大会種別 |
| emblem_url | VARCHAR | NULL可・大会ロゴ |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

例：

- Premier League
- Bundesliga
- Serie A
- Champions League

---

# 5. teams

Football-Data.orgから取得するクラブ情報。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| external_team_id | BIGINT | API側チームID・UNIQUE |
| name | VARCHAR | チーム名 |
| short_name | VARCHAR | NULL可 |
| tla | VARCHAR | NULL可・略称 |
| crest_url | VARCHAR | NULL可・エンブレム |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

# 6. football_matches

Football-Data.orgから取得する試合情報。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| external_match_id | BIGINT | API側試合ID・UNIQUE |
| competition_id | BIGINT | FK → competitions.id |
| home_team_id | BIGINT | FK → teams.id |
| away_team_id | BIGINT | FK → teams.id |
| match_day | INT | NULL可・節（第何節か） |
| stage | VARCHAR | NULL可・ステージ（グループステージ／決勝トーナメント等） |
| kickoff_at | DATETIME | UTCで保存 |
| status | VARCHAR | 試合状態 |
| home_score | INT | NULL可 |
| away_score | INT | NULL可 |
| venue | VARCHAR | NULL可 |
| last_api_synced_at | DATETIME | 最終API同期日時 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

### 日時ルール

本ルールは `football_matches` に限らず、DB上の全ての日時カラム（`broadcasts.reservation_start_at` / `reservation_end_at`、`reservations.reserved_at` / `cancelled_at` を含む）に共通で適用する。

DB：

```text
UTC
```

画面：

```text
Asia/Tokyo（JST）
```

---

# 7. shops

スポーツバー店舗情報。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| owner_id | BIGINT | FK → users.id |
| name | VARCHAR | 店舗名 |
| description | TEXT | NULL可 |
| address | VARCHAR | 住所 |
| phone | VARCHAR | NULL可 |
| latitude | DECIMAL | NULL可 |
| longitude | DECIMAL | NULL可 |
| google_place_id | VARCHAR | NULL可・UNIQUE |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | SoftDeletes |

Google Mapsと連携して位置情報を表示する。

店舗の削除は原則として論理削除（SoftDeletes）のみとし、関連する座席種別・放映情報・予約履歴を保持するため物理削除は行わない（詳細は13章「削除ポリシー」参照）。

---

# 8. seat_types

店舗ごとの座席種別。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| shop_id | BIGINT | FK → shops.id |
| name | VARCHAR | 座席名 |
| description | TEXT | NULL可 |
| capacity | INT | 座席数 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

例：

```text
カウンター席
テーブル席
センターコート
```

---

# 9. broadcasts

「どの店舗で、どの試合を放映するか」を管理する。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| shop_id | BIGINT | FK → shops.id |
| football_match_id | BIGINT | FK → football_matches.id |
| status | VARCHAR | 放映状態 |
| reservation_start_at | DATETIME | 予約開始日時 |
| reservation_end_at | DATETIME | 予約終了日時 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

# 10. broadcast_seat_types

放映ごとの座席在庫・料金を管理する。

同じ「テーブル席」でも試合によって価格や販売席数を変更できる。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| broadcast_id | BIGINT | FK → broadcasts.id |
| seat_type_id | BIGINT | FK → seat_types.id |
| price | INT | 1人あたり料金（円） |
| capacity | INT | 当該放映で販売する席数 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

# 11. reservations

ユーザーの観戦予約。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| reservation_code | VARCHAR | UNIQUE・予約番号 |
| user_id | BIGINT | FK → users.id |
| broadcast_seat_type_id | BIGINT | FK |
| party_size | INT | 予約人数 |
| unit_price | INT | 予約時点の1人料金 |
| total_price | INT | 予約時点の合計金額 |
| status | VARCHAR | 予約状態 |
| reserved_at | DATETIME | 予約日時 |
| cancelled_at | DATETIME | NULL可 |
| customer_note | TEXT | NULL可 |
| shop_note | TEXT | NULL可 |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

### status

```text
confirmed
cancelled
visited
no_show
```

予約はユーザーが「予約確定」を行った時点で、在庫ロック・確認後にただちに `confirmed` として作成する。MVPでは決済連携を行わないため、店舗承認待ちを表す `pending` は使用しない（将来、決済連携等で承認フローが必要になった場合に再検討する）。

### 金額について

`broadcast_seat_types.price` が後から変更されても予約金額が変化しないよう、

```text
unit_price
total_price
```

を予約時点で保存する。

### 重複・在庫対策

予約確定処理はトランザクション内で実行する。

- **在庫（残席）の算出方法**：`残席 = broadcast_seat_types.capacity − 対象broadcast_seat_typeに紐づく status = confirmed の reservations の人数合計`。`cancelled` / `no_show` の予約は残席計算から除外する。
- **ロック方法**：予約作成時は対象の `broadcast_seat_types` 行を `lockForUpdate()` で行ロックしたうえで残席を算出し、定員内であれば `reservations` をINSERTしてから `confirmed` 状態で確定する。

---

# 12. favorite_teams

ユーザーとお気に入りチームの中間テーブル。

| カラム | 型 | 制約・用途 |
|---|---|---|
| id | BIGINT | PK |
| user_id | BIGINT | FK → users.id |
| team_id | BIGINT | FK → teams.id |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

制約：

```text
UNIQUE(user_id, team_id)
```

1ユーザーにつき最大3チームまで登録可能。

---

# 13. 削除ポリシー

- **shops**：論理削除（SoftDeletes）を基本とする。物理削除は行わず、店舗を非表示にする場合も座席種別・放映情報・予約履歴はそのまま保持する。
- **reservationsへ到達する外部キー**：`reservations.user_id`、`reservations.broadcast_seat_type_id`、およびその先の `broadcast_seat_types.seat_type_id` / `broadcast_seat_types.broadcast_id` / `broadcasts.shop_id` / `broadcasts.football_match_id` など、予約履歴に到達する経路の外部キーは、履歴保護のため **RESTRICT（参照されている間は削除不可）** を基本とする。
- 上記以外（大会・チームなど予約履歴と直接紐づかないマスタ系データ）の削除ポリシーは、個別に検討する。

---

# 14. 主なリレーション

```text
User
 ├─ hasMany Reservations
 ├─ hasMany Shops
 └─ belongsToMany Teams (FavoriteTeams)

Competition
 └─ hasMany FootballMatches

Team
 ├─ hasMany HomeMatches
 └─ hasMany AwayMatches

FootballMatch
 ├─ belongsTo Competition
 ├─ belongsTo HomeTeam
 ├─ belongsTo AwayTeam
 └─ hasMany Broadcasts

Shop
 ├─ belongsTo Owner(User)
 ├─ hasMany SeatTypes
 └─ hasMany Broadcasts

Broadcast
 ├─ belongsTo Shop
 ├─ belongsTo FootballMatch
 └─ hasMany BroadcastSeatTypes

BroadcastSeatType
 ├─ belongsTo Broadcast
 ├─ belongsTo SeatType
 └─ hasMany Reservations

Reservation
 ├─ belongsTo User
 └─ belongsTo BroadcastSeatType
```