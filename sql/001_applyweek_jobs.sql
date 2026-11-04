CREATE TABLE IF NOT EXISTS applyweek_jobs (
    job_id VARCHAR(32) NOT NULL PRIMARY KEY,
    data JSON NOT NULL,
    collected_at DATETIME NOT NULL
);
