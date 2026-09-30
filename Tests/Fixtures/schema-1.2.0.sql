
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
    `answer` LONGTEXT,
    `answered_at` TIMESTAMP NULL,
    `answered_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_product_question_product_status_locale` (`product_id`, `status`, `locale`),
    INDEX `idx_product_question_status` (`status`),
    INDEX `idx_product_question_customer_id` (`customer_id`),
    INDEX `fi_product_question_answered_by` (`answered_by`),
    CONSTRAINT `fk_product_question_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_question_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_product_question_answered_by`
        FOREIGN KEY (`answered_by`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
