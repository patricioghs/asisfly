INSERT IGNORE INTO ai_provider_settings
  (company_id, provider, model, fallback_provider, fallback_model, api_key_env, temperature, monthly_token_limit, monthly_cost_limit, is_enabled)
SELECT
  c.id,
  'simulated',
  'asisfly-demo-latam',
  'simulated',
  'asisfly-demo-latam',
  'OPENAI_API_KEY',
  0.40,
  CASE p.name
    WHEN 'Starter' THEN 500000
    WHEN 'Pro' THEN 3000000
    WHEN 'Business' THEN 15000000
    ELSE -1
  END,
  CASE p.name
    WHEN 'Starter' THEN 25.00
    WHEN 'Pro' THEN 120.00
    WHEN 'Business' THEN 600.00
    ELSE -1.00
  END,
  TRUE
FROM companies c
LEFT JOIN plans p ON p.id = c.plan_id;
