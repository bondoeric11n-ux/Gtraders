-- Run once against the application database before deploying this package.
CREATE TABLE IF NOT EXISTS financial_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY financial_ledger_reference (event_type, reference_type, reference_id),
  KEY financial_ledger_user_created (user_id, created_at)
);

ALTER TABLE btc_deposits ADD COLUMN provider_reference VARCHAR(128) NULL;
ALTER TABLE btc_deposits ADD UNIQUE KEY btc_deposits_provider_reference (provider_reference);
