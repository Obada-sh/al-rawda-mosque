-- =====================================================================
--  نظام إدارة الجامع (rawda) — مخطط قاعدة البيانات الكامل
--  MySQL 8.x · 28 جدولاً · utf8mb4
--
--  الإصدار 2 (2026-10-08): أضيف جدولا النشاطات activities و activity_attendances،
--  ومصدر رابع للنقاط (activity) مع العمود activity_attendance_id،
--  والعمود students.photo_path للصورة الشخصية الاختيارية.
--
--  الإصدار 3 (2026-10-12): حضور الأساتذة teacher_attendances، وبطاقة للأستاذ
--  في كل دورة (teacher_cards)، وصورة اختيارية للأستاذ (teachers.photo_path)،
--  وشعار لكل حلقة (circles.logo_path). أُلغي إعداد تصميم البطاقة card_design:
--  البطاقة الآن تصميم الجامع نفسه.
--
--  الإصدار 4 (2026-10-14/15): المصروف قد يكون مبلغاً إجمالياً بلا كمية ولا سعر
--  (expenses.quantity و unit_price تقبلان NULL)، وسوق النقاط: markets و
--  market_items و market_sales و market_sale_lines.
--
--  الإصدار 5 (2026-10-20): اسم الأب الاختياري students.father_name، يميّز
--  الطلاب الذين يحملون الاسم نفسه.
--
--  للاستعراض في MySQL Workbench بدون إنشاء أي شيء على السيرفر:
--    File > Import > Reverse Engineer MySQL Create Script...
--    اختر هذا الملف، فعّل "Place imported objects on a diagram"، ثم Execute.
--
--  ملاحظة: هذا الملف للاستعراض. الجداول الفعلية تُنشئها migrations الخاصة
--  بـ Laravel (المجلد database/migrations)، فلا تشغّله على قاعدة النظام نفسها.
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS `rawda`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `rawda`;

-- ---------------------------------------------------------------------
-- 1) الأشخاص
-- ---------------------------------------------------------------------

CREATE TABLE `users` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255)    NOT NULL,
  `username`       VARCHAR(100)    NOT NULL,
  `password`       VARCHAR(255)    NOT NULL,
  `remember_token` VARCHAR(100)    NULL,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حساب الأدمن. لا حسابات للأساتذة';

CREATE TABLE `tajweed_levels` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)     NOT NULL,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP        NULL,
  `updated_at` TIMESTAMP        NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مستويات التجويد الأربعة';

CREATE TABLE `students` (
  `id`                    BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `full_name`             VARCHAR(255)      NOT NULL,
  `father_name`           VARCHAR(255)      NULL
      COMMENT 'اختياري: يميّز الطلاب الذين يحملون الاسم نفسه',
  `birth_date`            DATE              NULL,
  `phone`                 VARCHAR(30)       NULL,
  `guardian_phone`        VARCHAR(30)       NULL,
  `address`               VARCHAR(255)      NULL,
  `tajweed_level_id`      BIGINT UNSIGNED   NULL,
  `prior_memorized_pages` SMALLINT UNSIGNED NULL
      COMMENT 'المحفوظ قبل دخول النظام، كعدد صفحات',
  `recited_pages_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'عدّاد: مجموع الصفحات الجديدة المسمَّعة في كل الدورات. يحدّثه النظام',
  `notes`                 TEXT              NULL,
  `photo_path`            VARCHAR(255)      NULL
      COMMENT 'اسم ملف الصورة الشخصية (اختيارية) في المجلد الخاص student-photos',
  `is_profile_complete`   TINYINT(1)        NOT NULL DEFAULT 0,
  `archived_at`           TIMESTAMP         NULL,
  `created_at`            TIMESTAMP         NULL,
  `updated_at`            TIMESTAMP         NULL,
  PRIMARY KEY (`id`),
  KEY `students_full_name_index` (`full_name`),
  KEY `students_is_profile_complete_index` (`is_profile_complete`),
  KEY `students_tajweed_level_id_index` (`tajweed_level_id`),
  CONSTRAINT `students_tajweed_level_id_fk`
    FOREIGN KEY (`tajweed_level_id`) REFERENCES `tajweed_levels` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الطلاب. الاسم فقط إلزامي';

CREATE TABLE `teachers` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(255)    NOT NULL,
  `phone`      VARCHAR(30)     NULL,
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `photo_path` VARCHAR(255)    NULL
      COMMENT 'ملف الصورة الاختيارية: teacher-photos/<file>',
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الأساتذة: مشرفون، مساعدون، أساتذة تسميع وسبر';

-- ---------------------------------------------------------------------
-- 2) الدورة والحلقات
-- ---------------------------------------------------------------------

CREATE TABLE `courses` (
  `id`                    BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`                  VARCHAR(255)      NOT NULL,
  `start_date`            DATE              NOT NULL,
  `end_date`              DATE              NULL,
  `points_per_page`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `points_per_attendance` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`                ENUM('active','finished') NOT NULL DEFAULT 'active',
  `created_at`            TIMESTAMP         NULL,
  `updated_at`            TIMESTAMP         NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الدورات. قيم النقاط التلقائية تُضبط لكل دورة';

CREATE TABLE `course_schedules` (
  `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `course_id`  BIGINT UNSIGNED  NOT NULL,
  `weekday`    TINYINT UNSIGNED NOT NULL COMMENT '0 = الأحد ... 6 = السبت',
  `start_time` TIME             NOT NULL,
  `end_time`   TIME             NOT NULL,
  `created_at` TIMESTAMP        NULL,
  `updated_at` TIMESTAMP        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_schedules_course_weekday_unique` (`course_id`, `weekday`),
  CONSTRAINT `course_schedules_course_id_fk`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أيام وساعات الدوام لكل دورة';

CREATE TABLE `course_sessions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`    BIGINT UNSIGNED NOT NULL,
  `session_date` DATE            NOT NULL,
  `status`       ENUM('held','cancelled') NOT NULL DEFAULT 'held',
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_sessions_course_date_unique` (`course_id`, `session_date`),
  CONSTRAINT `course_sessions_course_id_fk`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أيام الدوام الفعلية. مقام نسبة الحضور';

CREATE TABLE `circles` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`             BIGINT UNSIGNED NOT NULL,
  `name`                  VARCHAR(150)    NOT NULL,
  `supervisor_teacher_id` BIGINT UNSIGNED NULL COMMENT 'المشرف الحالي',
  `assistant_teacher_id`  BIGINT UNSIGNED NULL COMMENT 'المساعد الحالي',
  `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
  `logo_path`             VARCHAR(255)    NULL
      COMMENT 'ملف شعار الحلقة الاختياري (يُطبع على البطاقة): circle-logos/<file>',
  `created_at`            TIMESTAMP       NULL,
  `updated_at`            TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `circles_course_name_unique` (`course_id`, `name`),
  KEY `circles_supervisor_teacher_id_index` (`supervisor_teacher_id`),
  KEY `circles_assistant_teacher_id_index` (`assistant_teacher_id`),
  CONSTRAINT `circles_course_id_fk`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `circles_supervisor_teacher_id_fk`
    FOREIGN KEY (`supervisor_teacher_id`) REFERENCES `teachers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `circles_assistant_teacher_id_fk`
    FOREIGN KEY (`assistant_teacher_id`) REFERENCES `teachers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الحلقات. كل حلقة تتبع دورة واحدة';

CREATE TABLE `enrollments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  BIGINT UNSIGNED NOT NULL,
  `course_id`   BIGINT UNSIGNED NOT NULL,
  `circle_id`   BIGINT UNSIGNED NOT NULL
      COMMENT 'الحلقة الحالية. يختارها الأدمن عند التسجيل وتُستبدل عند النقل',
  `card_code`   VARCHAR(20)     NOT NULL
      COMMENT 'محتوى QR على البطاقة، أرقام فقط',
  `enrolled_on` DATE            NOT NULL,
  `left_on`     DATE            NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_student_course_unique` (`student_id`, `course_id`),
  UNIQUE KEY `enrollments_card_code_unique` (`card_code`),
  KEY `enrollments_course_id_index` (`course_id`),
  KEY `enrollments_circle_id_index` (`circle_id`),
  CONSTRAINT `enrollments_student_id_fk`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `enrollments_course_id_fk`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `enrollments_circle_id_fk`
    FOREIGN KEY (`circle_id`) REFERENCES `circles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تسجيل طالب في دورة. صاحب كل السجلات (حضور، تسميع، نقاط)';

CREATE TABLE `circle_transfers` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id`  BIGINT UNSIGNED NOT NULL,
  `from_circle_id` BIGINT UNSIGNED NOT NULL,
  `to_circle_id`   BIGINT UNSIGNED NOT NULL,
  `transferred_on` DATE            NOT NULL
      COMMENT 'يدخله الأدمن يدوياً، بلا قيمة افتراضية',
  `notes`          VARCHAR(255)    NULL,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `circle_transfers_enrollment_id_index` (`enrollment_id`),
  KEY `circle_transfers_from_circle_id_index` (`from_circle_id`),
  KEY `circle_transfers_to_circle_id_index` (`to_circle_id`),
  CONSTRAINT `circle_transfers_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `circle_transfers_from_circle_id_fk`
    FOREIGN KEY (`from_circle_id`) REFERENCES `circles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `circle_transfers_to_circle_id_fk`
    FOREIGN KEY (`to_circle_id`) REFERENCES `circles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل التنقلات. للعرض فقط، لا يدخل في أي إحصائية';

-- ---------------------------------------------------------------------
-- 3) الأحداث: حضور، تسميع، سبر، نشاطات، نقاط
-- ---------------------------------------------------------------------

CREATE TABLE `attendances` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_session_id` BIGINT UNSIGNED NOT NULL,
  `enrollment_id`     BIGINT UNSIGNED NOT NULL,
  `scanned_at`        DATETIME        NULL
      COMMENT 'وقت مسح البطاقة. فارغ للحضور اليدوي',
  `method`            ENUM('qr','manual') NOT NULL DEFAULT 'qr'
      COMMENT 'manual = أضافه الأدمن يدوياً لأي تاريخ',
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_session_enrollment_unique` (`course_session_id`, `enrollment_id`),
  KEY `attendances_enrollment_id_index` (`enrollment_id`),
  CONSTRAINT `attendances_course_session_id_fk`
    FOREIGN KEY (`course_session_id`) REFERENCES `course_sessions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendances_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الحضور. سطر واحد للطالب في الجلسة';

CREATE TABLE `teacher_cards` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`  BIGINT UNSIGNED NOT NULL,
  `teacher_id` BIGINT UNSIGNED NOT NULL,
  `card_code`  VARCHAR(20)     NOT NULL
      COMMENT 'رمز بطاقة حضور الأستاذ في هذه الدورة (أرقام فقط). لا يتكرر مع رموز بطاقات الطلاب',
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_cards_card_code_unique` (`card_code`),
  UNIQUE KEY `teacher_cards_course_id_teacher_id_unique` (`course_id`, `teacher_id`),
  KEY `teacher_cards_teacher_id_index` (`teacher_id`),
  CONSTRAINT `teacher_cards_course_id_fk`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `teacher_cards_teacher_id_fk`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بطاقة حضور الأستاذ في كل دورة: يُسجَّل بها حضوره في تلك الدورة فقط';

CREATE TABLE `teacher_attendances` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_session_id` BIGINT UNSIGNED NOT NULL,
  `teacher_id`        BIGINT UNSIGNED NOT NULL,
  `scanned_at`        DATETIME        NULL
      COMMENT 'وقت مسح بطاقة الأستاذ. فارغ للحضور اليدوي',
  `method`            ENUM('qr','manual') NOT NULL DEFAULT 'qr',
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_attendances_course_session_id_teacher_id_unique` (`course_session_id`, `teacher_id`),
  KEY `teacher_attendances_teacher_id_index` (`teacher_id`),
  CONSTRAINT `teacher_attendances_course_session_id_fk`
    FOREIGN KEY (`course_session_id`) REFERENCES `course_sessions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `teacher_attendances_teacher_id_fk`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حضور الأساتذة (المشرف والمساعد): سطر للأستاذ في كل جلسة من دورات حلقاته';

CREATE TABLE `recitations` (
  `id`              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `enrollment_id`   BIGINT UNSIGNED   NOT NULL
      COMMENT 'يحدد الطالب والدورة التي سمّع فيها',
  `teacher_id`      BIGINT UNSIGNED   NOT NULL COMMENT 'الأستاذ المسمِّع',
  `recited_on`      DATE              NOT NULL,
  `from_page`       SMALLINT UNSIGNED NOT NULL,
  `to_page`         SMALLINT UNSIGNED NOT NULL,
  `pages_count`     SMALLINT UNSIGNED NOT NULL,
  `new_pages_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'الصفحات التي لم يسمّعها الطالب من قبل',
  `points_per_page` SMALLINT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'نقاط الصفحة في الدورة وقت إدخال التسميع، فلا تتغير نقاطه بتغيير نقاط الدورة لاحقاً',
  `notes`           TEXT              NULL,
  `created_at`      TIMESTAMP         NULL,
  `updated_at`      TIMESTAMP         NULL,
  PRIMARY KEY (`id`),
  KEY `recitations_enrollment_date_index` (`enrollment_id`, `recited_on`),
  KEY `recitations_teacher_id_index` (`teacher_id`),
  KEY `recitations_recited_on_index` (`recited_on`),
  CONSTRAINT `recitations_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `recitations_teacher_id_fk`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `recitations_pages_range_chk`
    CHECK (`from_page` >= 1 AND `to_page` >= `from_page` AND `to_page` <= 604)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='التسميع بنطاق صفحات من - إلى';

CREATE TABLE `student_pages` (
  `student_id`    BIGINT UNSIGNED   NOT NULL,
  `page_no`       SMALLINT UNSIGNED NOT NULL,
  `recitation_id` BIGINT UNSIGNED   NOT NULL
      COMMENT 'التسميع الذي حُسبت فيه الصفحة أول مرة: منه الدورة والأستاذ والتاريخ',
  `created_at`    TIMESTAMP         NULL,
  PRIMARY KEY (`student_id`, `page_no`),
  KEY `student_pages_recitation_id_index` (`recitation_id`),
  CONSTRAINT `student_pages_student_id_fk`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `student_pages_recitation_id_fk`
    FOREIGN KEY (`recitation_id`) REFERENCES `recitations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `student_pages_page_no_chk`
    CHECK (`page_no` >= 1 AND `page_no` <= 604)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سطر لكل صفحة حفظها الطالب. يمنع احتساب الصفحة مرتين';

CREATE TABLE `quran_juz` (
  `juz_no`    TINYINT UNSIGNED  NOT NULL,
  `from_page` SMALLINT UNSIGNED NOT NULL,
  `to_page`   SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`juz_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='جدول مرجعي ثابت: حدود الأجزاء الثلاثين';

CREATE TABLE `juz_exams` (
  `id`                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`          BIGINT UNSIGNED  NOT NULL,
  `juz_no`              TINYINT UNSIGNED NOT NULL,
  `enrollment_id`       BIGINT UNSIGNED  NULL
      COMMENT 'الدورة التي اكتمل فيها الجزء',
  `status`              ENUM('pending','done') NOT NULL DEFAULT 'pending',
  `origin`              ENUM('auto','manual') NOT NULL DEFAULT 'auto'
      COMMENT 'auto = فتحه النظام عند اكتمال الجزء، manual = فتحه الأدمن',
  `triggered_on`        DATE             NOT NULL,
  `exam_date`           DATE             NULL,
  `examiner_teacher_id` BIGINT UNSIGNED  NULL,
  `grade`               ENUM('excellent','very_good','good','acceptable','retake') NULL,
  `notes`               TEXT             NULL,
  `created_at`          TIMESTAMP        NULL,
  `updated_at`          TIMESTAMP        NULL,
  PRIMARY KEY (`id`),
  KEY `juz_exams_student_juz_index` (`student_id`, `juz_no`),
  KEY `juz_exams_status_index` (`status`),
  KEY `juz_exams_juz_no_index` (`juz_no`),
  KEY `juz_exams_enrollment_id_index` (`enrollment_id`),
  KEY `juz_exams_examiner_teacher_id_index` (`examiner_teacher_id`),
  CONSTRAINT `juz_exams_student_id_fk`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `juz_exams_juz_no_fk`
    FOREIGN KEY (`juz_no`) REFERENCES `quran_juz` (`juz_no`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `juz_exams_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `juz_exams_examiner_teacher_id_fk`
    FOREIGN KEY (`examiner_teacher_id`) REFERENCES `teachers` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='السبر عند اكتمال جزء';

CREATE TABLE `activities` (
  `id`            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(255)      NOT NULL COMMENT 'اسم النشاط: رحلة، مسابقة، لقاء...',
  `activity_date` DATE              NOT NULL COMMENT 'يدخله الأدمن. لا يكون في المستقبل',
  `circle_id`     BIGINT UNSIGNED   NULL
      COMMENT 'الحلقة التي أقيم لها النشاط. فارغ = نشاط عام للجامع كله',
  `points`        SMALLINT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'ما يناله كل طالب حضر النشاط. صفر = نشاط بلا نقاط',
  `notes`         TEXT              NULL,
  `created_at`    TIMESTAMP         NULL,
  `updated_at`    TIMESTAMP         NULL,
  PRIMARY KEY (`id`),
  KEY `activities_activity_date_index` (`activity_date`),
  KEY `activities_circle_id_index` (`circle_id`),
  CONSTRAINT `activities_circle_id_fk`
    FOREIGN KEY (`circle_id`) REFERENCES `circles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='النشاطات: لحلقة واحدة أو للجامع كله';

CREATE TABLE `activity_attendances` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id`   BIGINT UNSIGNED NOT NULL,
  `enrollment_id` BIGINT UNSIGNED NOT NULL
      COMMENT 'الطالب بصفته طالباً في دورة: تذهب نقاط النشاط إلى تلك الدورة',
  `created_at`    TIMESTAMP       NULL,
  `updated_at`    TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activity_attendances_activity_enrollment_unique` (`activity_id`, `enrollment_id`),
  KEY `activity_attendances_enrollment_id_index` (`enrollment_id`),
  CONSTRAINT `activity_attendances_activity_id_fk`
    FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `activity_attendances_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='من حضر النشاط. سطر واحد للطالب في النشاط';

CREATE TABLE `point_transactions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` BIGINT UNSIGNED NOT NULL
      COMMENT 'يحدد الطالب والدورة التي جمع فيها النقاط',
  `source`        ENUM('attendance','recitation','manual','activity') NOT NULL,
  `attendance_id` BIGINT UNSIGNED NULL,
  `recitation_id` BIGINT UNSIGNED NULL,
  `activity_attendance_id` BIGINT UNSIGNED NULL
      COMMENT 'لنقاط النشاط: سطر الحضور الذي أكسبها. تُحذف النقاط بحذفه',
  `points`        INT             NOT NULL COMMENT 'يقبل السالب (عقوبة)',
  `reason`        VARCHAR(255)    NULL COMMENT 'إلزامي للنقاط اليدوية. ولنقاط النشاط: اسم النشاط',
  `occurred_on`   DATE            NOT NULL,
  `created_at`    TIMESTAMP       NULL,
  `updated_at`    TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `point_transactions_attendance_id_unique` (`attendance_id`),
  UNIQUE KEY `point_transactions_recitation_id_unique` (`recitation_id`),
  UNIQUE KEY `point_transactions_activity_attendance_id_unique` (`activity_attendance_id`),
  KEY `point_transactions_enrollment_date_index` (`enrollment_id`, `occurred_on`),
  KEY `point_transactions_occurred_on_index` (`occurred_on`),
  CONSTRAINT `point_transactions_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `point_transactions_attendance_id_fk`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `point_transactions_recitation_id_fk`
    FOREIGN KEY (`recitation_id`) REFERENCES `recitations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `point_transactions_activity_attendance_id_fk`
    FOREIGN KEY (`activity_attendance_id`) REFERENCES `activity_attendances` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجل حركات النقاط. المجموع يُحسب من الأسطر';

-- ---------------------------------------------------------------------
-- 4) المالية (مصاريف فقط)
-- ---------------------------------------------------------------------

CREATE TABLE `departments` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150)    NOT NULL,
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الأقسام: مشتريات، إطعام، لوجستي، وما يُضاف لاحقاً';

CREATE TABLE `expenses` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` BIGINT UNSIGNED NOT NULL,
  `item_name`     VARCHAR(255)    NOT NULL,
  `quantity`      DECIMAL(12,3)   NULL COMMENT 'Null for an amount paid as a whole (a lump sum)',
  `unit_price`    DECIMAL(14,2)   NULL COMMENT 'Syrian pounds. Null for a lump sum',
  `total`         DECIMAL(16,2)   NOT NULL COMMENT 'Syrian pounds: quantity × unit_price, or the amount paid as a whole',
  `expense_date`  DATE            NOT NULL,
  `notes`         TEXT            NULL,
  `created_at`    TIMESTAMP       NULL,
  `updated_at`    TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_department_date_index` (`department_id`, `expense_date`),
  KEY `expenses_expense_date_index` (`expense_date`),
  CONSTRAINT `expenses_department_id_fk`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='المصاريف';

-- ---------------------------------------------------------------------
-- 5) سوق النقاط
--    يشتري الطالب بنقاطه في الدورة. البيع لا يكتب في point_transactions:
--    الرصيد = مجموع نقاطه في الدورة − مجموع مشترياته من أسواقها.
-- ---------------------------------------------------------------------

CREATE TABLE `markets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`  BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(150)    NOT NULL,
  `is_open`    TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Only an open market sells',
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `markets_course_id_name_unique` (`course_id`, `name`),
  CONSTRAINT `markets_course_id_fk`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أسواق النقاط: لكل دورة ما شاءت منها';

CREATE TABLE `market_items` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_id`    BIGINT UNSIGNED NOT NULL,
  `name`         VARCHAR(150)    NOT NULL,
  `description`  TEXT            NULL,
  `price`        INT UNSIGNED    NOT NULL COMMENT 'In points',
  `stock`        INT UNSIGNED    NULL COMMENT 'What is left to sell; null for no limit',
  `image_path`   VARCHAR(255)    NULL COMMENT 'market-items/<file>',
  `is_available` TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Shown at the sales desk',
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `market_items_market_id_name_unique` (`market_id`, `name`),
  CONSTRAINT `market_items_market_id_fk`
    FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='بضاعة السوق وسعرها بالنقاط';

CREATE TABLE `market_sales` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `market_id`     BIGINT UNSIGNED NOT NULL,
  `enrollment_id` BIGINT UNSIGNED NOT NULL,
  `total_points`  INT UNSIGNED    NOT NULL,
  `sold_at`       DATETIME        NOT NULL,
  `created_at`    TIMESTAMP       NULL,
  `updated_at`    TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `market_sales_market_id_sold_at_index` (`market_id`, `sold_at`),
  KEY `market_sales_sold_at_index` (`sold_at`),
  CONSTRAINT `market_sales_market_id_fk`
    FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `market_sales_enrollment_id_fk`
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عمليات الشراء: طالب في دورة السوق اشترى سلة';

CREATE TABLE `market_sale_lines` (
  `id`             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `market_sale_id` BIGINT UNSIGNED   NOT NULL,
  `market_item_id` BIGINT UNSIGNED   NOT NULL,
  `item_name`      VARCHAR(150)      NOT NULL COMMENT 'As it was sold',
  `price`          INT UNSIGNED      NOT NULL COMMENT 'Points for one, as it was sold',
  `quantity`       SMALLINT UNSIGNED NOT NULL,
  `points`         INT UNSIGNED      NOT NULL COMMENT 'price × quantity',
  `created_at`     TIMESTAMP         NULL,
  `updated_at`     TIMESTAMP         NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `market_sale_lines_sale_item_unique` (`market_sale_id`, `market_item_id`),
  CONSTRAINT `market_sale_lines_market_sale_id_fk`
    FOREIGN KEY (`market_sale_id`) REFERENCES `market_sales` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `market_sale_lines_market_item_id_fk`
    FOREIGN KEY (`market_item_id`) REFERENCES `market_items` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ما في كل عملية شراء، باسمه وسعره وقت البيع';

-- ---------------------------------------------------------------------
-- 6) النظام
-- ---------------------------------------------------------------------

CREATE TABLE `notifications` (
  `id`              CHAR(36)        NOT NULL,
  `type`            VARCHAR(255)    NOT NULL,
  `notifiable_type` VARCHAR(255)    NOT NULL,
  `notifiable_id`   BIGINT UNSIGNED NOT NULL,
  `data`            JSON            NOT NULL,
  `read_at`         TIMESTAMP       NULL,
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_index` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='إشعارات الأدمن داخل اللوحة (جدول Laravel القياسي)';

CREATE TABLE `settings` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(100)    NOT NULL,
  `value`      TEXT            NULL,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='إعدادات النظام: اسم المسجد، فاصل التذكير، تواريخ آخر تذكير ونسخة';
