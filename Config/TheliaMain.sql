
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- product_question
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_question`;

CREATE TABLE `product_question`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `customer_id` INTEGER,
    `locale` VARCHAR(10) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `status` TINYINT DEFAULT 0 NOT NULL,
    `helpful_count` INTEGER DEFAULT 0 NOT NULL,
    `notify_author` TINYINT(1) DEFAULT 1 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_product_question_product_status_locale` (`product_id`, `status`, `locale`),
    INDEX `idx_product_question_status` (`status`),
    INDEX `idx_product_question_customer_id` (`customer_id`),
    CONSTRAINT `fk_product_question_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_question_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_question_answer
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_question_answer`;

CREATE TABLE `product_question_answer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `question_id` INTEGER NOT NULL,
    `customer_id` INTEGER,
    `admin_id` INTEGER,
    `is_official` TINYINT(1) DEFAULT 0 NOT NULL,
    `content` LONGTEXT NOT NULL,
    `status` TINYINT DEFAULT 0 NOT NULL,
    `helpful_count` INTEGER DEFAULT 0 NOT NULL,
    `published_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_product_question_answer_question_status` (`question_id`, `status`),
    INDEX `idx_product_question_answer_status` (`status`),
    INDEX `idx_product_question_answer_customer_id` (`customer_id`),
    INDEX `fi_product_question_answer_admin_id` (`admin_id`),
    CONSTRAINT `fk_product_question_answer_question_id`
        FOREIGN KEY (`question_id`)
        REFERENCES `product_question` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_question_answer_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_product_question_answer_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_question_answer_vote
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_question_answer_vote`;

CREATE TABLE `product_question_answer_vote`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `answer_id` INTEGER NOT NULL,
    `customer_id` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uq_product_question_answer_vote_answer_customer` (`answer_id`, `customer_id`),
    INDEX `idx_product_question_answer_vote_customer_id` (`customer_id`),
    CONSTRAINT `fk_product_question_answer_vote_answer_id`
        FOREIGN KEY (`answer_id`)
        REFERENCES `product_question_answer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_question_answer_vote_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
