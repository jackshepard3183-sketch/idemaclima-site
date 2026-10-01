ALTER TABLE contact_submissions ADD COLUMN deleted_at DATETIME NULL;
CREATE INDEX idx_contact_submissions_deleted_at ON contact_submissions (deleted_at);
