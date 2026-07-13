-- Mantiene el origen navegable de tareas generadas por automatizaciones.
SET @database_name = DATABASE();

SET @statement = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = @database_name AND table_name = 'crm_tasks' AND column_name = 'source_type'),
        'SELECT 1',
        'ALTER TABLE crm_tasks ADD COLUMN source_type VARCHAR(50) NULL AFTER priority'
    )
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = @database_name AND table_name = 'crm_tasks' AND column_name = 'source_id'),
        'SELECT 1',
        'ALTER TABLE crm_tasks ADD COLUMN source_id BIGINT UNSIGNED NULL AFTER source_type'
    )
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = @database_name AND table_name = 'crm_tasks' AND column_name = 'source_label'),
        'SELECT 1',
        'ALTER TABLE crm_tasks ADD COLUMN source_label VARCHAR(180) NULL AFTER source_id'
    )
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = @database_name AND table_name = 'crm_tasks' AND index_name = 'idx_crm_tasks_source'),
        'SELECT 1',
        'ALTER TABLE crm_tasks ADD INDEX idx_crm_tasks_source (company_id, source_type, source_id)'
    )
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
