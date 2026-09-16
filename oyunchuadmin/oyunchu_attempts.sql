-- OYUNCHU gündəlik cəhd sistemi
-- 5 pulsuz cəhd + 60/250/350/1000 coin əlavə cəhdlər.
CREATE TABLE IF NOT EXISTS oh_game_attempts (
  user_id INT UNSIGNED NOT NULL,
  game_slug VARCHAR(30) NOT NULL,
  attempt_date DATE NOT NULL,
  attempts_used INT UNSIGNED NOT NULL DEFAULT 0,
  extra_purchased INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_slug, attempt_date),
  KEY idx_game_day (game_slug, attempt_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO es_settings(skey,svalue) VALUES
('oh_daily_free_attempts','5'),
('oh_extra_attempt_cost_1','60'),
('oh_extra_attempt_cost_2','250'),
('oh_extra_attempt_cost_3','350'),
('oh_extra_attempt_cost_4','1000')
ON DUPLICATE KEY UPDATE svalue=VALUES(svalue);
