CREATE TABLE IF NOT EXISTS ai_brand_onboarding_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    brand_route_id BIGINT UNSIGNED NOT NULL,
    question_key VARCHAR(100) NOT NULL,
    question_label VARCHAR(220) NOT NULL,
    answer TEXT NULL,
    is_optional BOOLEAN NOT NULL DEFAULT FALSE,
    answered_by BIGINT UNSIGNED NULL,
    answered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_brand_answer_question (company_id, brand_route_id, question_key),
    INDEX idx_ai_brand_answers_company (company_id, brand_route_id),
    CONSTRAINT fk_ai_brand_answers_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_brand_answers_route FOREIGN KEY (brand_route_id) REFERENCES ai_brand_routes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_brand_answers_user FOREIGN KEY (answered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
