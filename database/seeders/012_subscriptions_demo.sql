UPDATE companies
SET trial_ends_at = COALESCE(trial_ends_at, DATE_ADD(created_at, INTERVAL 14 DAY)),
    subscription_status = IF(status = 'active', 'active', 'trialing'),
    billing_provider = COALESCE(billing_provider, 'manual')
WHERE id > 0;

INSERT IGNORE INTO subscriptions (company_id, plan_id, provider, status, current_period_start, current_period_end, trial_ends_at)
SELECT c.id, c.plan_id, COALESCE(c.billing_provider, 'manual'), c.subscription_status, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH), c.trial_ends_at
FROM companies c
WHERE c.plan_id IS NOT NULL;
