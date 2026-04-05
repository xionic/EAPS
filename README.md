# EAPS – Easy Access Pair Storage

EAPS is a lightweight PHP key-value store designed for simple, HTTP-based storage and retrieval of named values. Data is organised under **clients** (identified by a `client_key`) and grouped into named **tags**. Within each tag, any number of **key/value pairs** can be stored; every write creates a new historical record while the most recent value is always quickly accessible.

## Requirements

- PHP 7.4+
- MySQL **or** SQLite
- A web server (Apache, Nginx, …)

## Setup

1. Import the appropriate database schema from the `db/` directory:
   - `db/schema.sql` – SQLite
   - `db/schema-mysql.sql` – MySQL
2. Copy `config.php.example` to `config.php` and fill in your database credentials and preferred driver (`sqlite` or `mysql`).
3. Insert at least one row into `tClient` with a unique `client_key` to start storing data.

---

## REST API

All requests are made to `index.php`. Every request requires two query-string parameters:

| Parameter    | Description                                      |
|--------------|--------------------------------------------------|
| `client_key` | The unique key that identifies the calling client |
| `tag`        | The tag (namespace) to operate on                |
| `action`     | One of `tags`, `keys`, `value`, `values`         |

### Response envelope

Successful responses return JSON:

```json
{
  "keys": ["key_name", ...],
  "data": [
    { "key": "key_name", "value_id": 1, "value": "...", "created": 1712345678 },
    ...
  ]
}
```

Error responses return a non-2xx HTTP status and JSON:

```json
{ "error_message": "description of the problem" }
```

---

### `GET ?action=tags`

Returns all tags that belong to the authenticated client.

**Required parameters:** `client_key`, `tag`

**Example response:**

```json
{
  "keys": null,
  "data": [
    { "tag_id": 1, "tag_name": "sensors" }
  ]
}
```

---

### `GET ?action=keys`

Returns all keys that exist under the given tag.

**Required parameters:** `client_key`, `tag`

**Example response:**

```json
{
  "keys": [
    { "key_id": 1, "key_name": "temperature" }
  ],
  "data": null
}
```

---

### `GET ?action=value&key=<key>`

Returns the **latest** stored value for a single key within the given tag.

**Required parameters:** `client_key`, `tag`, `key`

**Example response:**

```json
{
  "keys": ["temperature"],
  "data": [
    { "key": "temperature", "value_id": 42, "value": "21.5", "created": 1712345678 }
  ]
}
```

If no value has been stored yet, both `keys` and `data` entries will be `null`.

---

### `POST ?action=value&tag=<tag>`

Stores a new value for a key. Tags and keys are created automatically if they do not already exist.

**Required parameters (query string):** `client_key`, `tag`  
**Required parameters (POST body):** `key`, `value`

**Returns:** HTTP 201 with an empty body on success.

---

### `GET ?action=values`

Returns **all historical values** for a tag, optionally filtered by key and/or a start time.

**Required parameters:** `client_key`, `tag`  
**Optional parameters:**

| Parameter | Description |
|-----------|-------------|
| `key`     | Restrict results to a single key name |
| `since`   | Unix timestamp **or** any date string accepted by PHP `strtotime()` (e.g. `"yesterday"`, `"2024-01-01"`) |

Results are ordered by `created` descending (newest first).

**Example response:**

```json
{
  "keys": ["temperature", "humidity"],
  "data": [
    { "key": "temperature", "value_id": 43, "value": "22.0", "created": 1712346000 },
    { "key": "humidity",    "value_id": 31, "value": "55",   "created": 1712345900 }
  ]
}
```

---

## Admin Interface

`admin.php` provides a browser-based interface for querying and managing stored values.

- Filter records by client, tag, key, and date/time range.
- Edit or delete individual value records.
- Export search results as a CSV file.

Access can be restricted to LAN (RFC 1918) addresses and/or protected with HTTP Basic authentication via the `ADMIN_REQUIRE_AUTH`, `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH`, and `ADMIN_LAN_ONLY` constants in `config.php`.

---

## PHP Client Library

A PHP client library is included in `client_libs/php/`.

### Instantiation

```php
require_once("client_libs/php/libEAPS.php");

$client = new EAPS_client("https://example.com/EAPS/", "your-client-key");
```

### Methods

#### `get_value(string $tag, string $key): EAPSValueResponse`

Fetches the latest value for a single key.

```php
$response = $client->get_value("sensors", "temperature");
```

#### `get_values(string $tag [, string $key [, string|int $since]]): EAPSValueResponse`

Fetches all historical values for a tag, with optional key and time filters.

```php
$response = $client->get_values("sensors");
$response = $client->get_values("sensors", "temperature");
$response = $client->get_values("sensors", false, "yesterday");
```

#### `get_keys(string $tag): EAPSKeysResponse`

Fetches all key names for a tag.

```php
$response = $client->get_keys("sensors");
```

#### `add_value(string $tag, string $key, string $value): void`

Stores a new value for a key.

```php
$client->add_value("sensors", "temperature", "21.5");
```

---

## Data Model

```
tClient  (client_id, client_key, client_name)
  └─ tTag  (tag_id, tag_name, client_id)
       └─ tKey  (key_id, key_name, tag_id, newest_value_id)
            └─ tValue  (value_id, key_id, value_data, created)
```

- Each client is isolated; clients cannot access each other's data.
- Tags and keys are created automatically on first write.
- Every write appends a new `tValue` row; `tKey.newest_value_id` always points to the most recent one for fast lookups.

