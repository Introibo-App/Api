-- Directorium API — access-control schema (#22).
--
-- Holds only tenants, API keys, and aggregate counters — never a row per request.
-- Applied once at deploy against the MySQL database named by INTROIBO_DB_DSN; the
-- PdoKeyStore reads and writes exactly these tables.

CREATE TABLE IF NOT EXISTS tenants (
    id            VARCHAR(64)  NOT NULL,
    name          VARCHAR(255) NOT NULL,
    monthly_quota INT UNSIGNED NULL,               -- NULL = unlimited
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS api_keys (
    id              VARCHAR(64)  NOT NULL,
    tenant_id       VARCHAR(64)  NOT NULL,
    hash            CHAR(64)     NOT NULL,          -- SHA-256 hex of the secret; the secret is never stored
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    rate_per_minute INT UNSIGNED NULL,             -- NULL = service default
    label           VARCHAR(255) NOT NULL DEFAULT '',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_keys_hash (hash),
    KEY idx_api_keys_tenant (tenant_id),
    CONSTRAINT fk_api_keys_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Aggregate monthly usage per tenant (the quota counter).
CREATE TABLE IF NOT EXISTS usage_counters (
    tenant_id VARCHAR(64)         NOT NULL,
    period    CHAR(7)             NOT NULL,         -- YYYY-MM
    count     BIGINT UNSIGNED     NOT NULL DEFAULT 0,
    PRIMARY KEY (tenant_id, period),
    CONSTRAINT fk_usage_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Per-key, per-minute request counters (the rate-limit windows). Old rows are inert;
-- a periodic job may prune windows older than a few minutes to keep the table small:
--   DELETE FROM rate_windows WHERE window_start < DATE_FORMAT(NOW() - INTERVAL 10 MINUTE, '%Y-%m-%d %H:%i');
CREATE TABLE IF NOT EXISTS rate_windows (
    key_id       VARCHAR(64)     NOT NULL,
    window_start CHAR(16)        NOT NULL,          -- YYYY-MM-DD HH:MM
    count        INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (key_id, window_start),
    KEY idx_rate_windows_start (window_start)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
