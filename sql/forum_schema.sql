-- ============================================================
-- Москва CRMP — Форум (PHP + MySQL)
-- Импорт: mysql -u samp -p samp_rp < sql/forum_schema.sql
-- Набор: utf8mb4
-- Интегрирован с БД samp_rp: вход через accounts, баны через bans
-- ============================================================

SET NAMES utf8mb4;
USE samp_rp;

-- ------------------------------------------------------------
-- Форум: разделы (категории)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(100) NOT NULL,
  `description` TEXT         NULL,
  `position`    INT          NOT NULL DEFAULT 0,
  `parent_id`   INT UNSIGNED NULL COMMENT '0/вложенность, для подфорумов',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Форум: темы
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_topics` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(160) NULL,
  `sticky`      TINYINT(1)   NOT NULL DEFAULT 0,
  `locked`      TINYINT(1)   NOT NULL DEFAULT 0,
  `views`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_post_id` INT UNSIGNED NULL,
  `last_post_at` TIMESTAMP   NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cat` (`category_id`),
  KEY `idx_author` (`account_id`),
  CONSTRAINT `fk_topic_cat`  FOREIGN KEY (`category_id`) REFERENCES `forum_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_topic_user` FOREIGN KEY (`account_id`)  REFERENCES `accounts`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Форум: посты (сообщения)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id`    INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED NOT NULL,
  `body`        MEDIUMTEXT   NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at`   TIMESTAMP    NULL,
  `edited_by`   INT UNSIGNED NULL,
  `ip`          VARCHAR(45)  NULL,
  PRIMARY KEY (`id`),
  KEY `idx_topic` (`topic_id`),
  KEY `idx_user`  (`account_id`),
  CONSTRAINT `fk_post_topic` FOREIGN KEY (`topic_id`)   REFERENCES `forum_topics`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_user`  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Реакции (лайки) на посты
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_post_reactions` (
  `post_id`    INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `reaction`   VARCHAR(16)  NOT NULL DEFAULT 'like',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`,`account_id`),
  CONSTRAINT `fk_react_post` FOREIGN KEY (`post_id`)    REFERENCES `forum_posts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_react_user` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Подписки на темы (доставка уведомлений)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_topic_subscriptions` (
  `topic_id`    INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`topic_id`,`account_id`),
  CONSTRAINT `fk_sub_topic` FOREIGN KEY (`topic_id`)   REFERENCES `forum_topics`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_user`  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Уведомления
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_notifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id`  INT UNSIGNED NOT NULL,
  `type`        VARCHAR(32)  NOT NULL COMMENT 'reply|mention|pm|report',
  `text`        VARCHAR(255) NOT NULL,
  `link`        VARCHAR(255) NULL,
  `read_at`     DATETIME     NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`  (`account_id`,`read_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Личные сообщения (конверсации)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_convos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `initiator_id` INT UNSIGNED NOT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_convo_user` FOREIGN KEY (`initiator_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_convo_participants` (
  `convo_id`    INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED NOT NULL,
  `last_read_id` INT UNSIGNED DEFAULT 0,
  `left_at`     DATETIME     NULL,
  PRIMARY KEY (`convo_id`,`account_id`),
  CONSTRAINT `fk_part_convo` FOREIGN KEY (`convo_id`)   REFERENCES `forum_convos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_part_user`  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forum_convo_messages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `convo_id`    INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED NOT NULL,
  `body`        MEDIUMTEXT   NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_convo` (`convo_id`),
  CONSTRAINT `fk_msg_convo` FOREIGN KEY (`convo_id`)   REFERENCES `forum_convos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_user`  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Отчёты / жалобы на посты
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_reports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`     INT UNSIGNED NOT NULL,
  `reporter_id` INT UNSIGNED NOT NULL,
  `reason`      VARCHAR(255) NOT NULL,
  `status`      ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
  `resolved_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_rep_post`    FOREIGN KEY (`post_id`)     REFERENCES `forum_posts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `accounts`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Персональные настройки интерфейса и профиля на форуме
-- Расширяет accounts, не трогая гейм-мод
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_profiles` (
  `account_id`   INT UNSIGNED NOT NULL,
  `display_name` VARCHAR(48)  NULL,
  `avatar`       VARCHAR(255) NULL,
  `signature`    TEXT         NULL,
  `about`        TEXT         NULL,
  `post_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`account_id`),
  CONSTRAINT `fk_prof_user` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Настройки форума (config вкл./выкл., инфо)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_settings` (
  `skey`    VARCHAR(64)  NOT NULL,
  `svalue`  TEXT         NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `forum_settings` (`skey`,`svalue`) VALUES
  ('site_name','Москва CRMP'),
  ('site_desc','Форум проекта Москва CRMP'),
  ('forum_open','1'),
  ('registration_open','1'),
  ('posts_per_page','20'),
  ('topics_per_page','25'),
  ('max_ban_hours','8760'),
  ('default_avatar',''),
  ('email_from','noreply@mt-rp.example');

-- ------------------------------------------------------------
-- Стартовые разделы
-- ------------------------------------------------------------
INSERT IGNORE INTO `forum_categories` (`title`,`description`,`position`) VALUES
  ('Объявления администрации','Официальные новости и правила сервера',1),
  ('Обсуждения','Общие разговоры о сервере и жизни города',2),
  ('Помощь и поддержка','Вопросы новичков, проблемы с подключением',3),
  ('Купить привилегии','Информация о донате и его статусах',4);